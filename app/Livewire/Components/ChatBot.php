<?php

namespace App\Livewire\Components;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Personas;
use Livewire\Component;
use OpenAI\Laravel\Facades\OpenAI;
use Spatie\LaravelMarkdown\MarkdownRenderer;
use App\Services\Search\VectorSearchService;
use App\Services\Search\HybridSearchService;
use App\Services\Chat\ChatGeneratorService;
use Illuminate\Support\Facades\Log;

class ChatBot extends Component
{
    public Conversation $conversation;
    public $messages = [];
    public string $model;
    public $user;
    public $answer = null;
    public bool $responding = false;
    protected $listeners = [
        'messageAdded' => 'loadMessages',
        'conversationListUpdated' => 'refreshConversation',
        'modelUpdated' => 'updateModel'
    ];

    public string $errorMessage = '';

    private VectorSearchService $vectorSearchService;
    private HybridSearchService $hybridSearchService;

    private ChatGeneratorService $chatGeneratorService;

    public function boot(
        VectorSearchService $vectorSearchService,
        ChatGeneratorService $chatGeneratorService,
        HybridSearchService $hybridSearchService
    ) {
        $this->vectorSearchService = $vectorSearchService;
        $this->chatGeneratorService = $chatGeneratorService;
        $this->hybridSearchService = $hybridSearchService;
    }

    public function mount(Conversation $conversation)
    {
        session(['current_conversation_uuid' => $conversation->uuid]);
        $this->conversation = $conversation;
        $this->loadMessages();
        $this->resetChat();
        $this->user = auth()->user();
        $this->model = $conversation->model ?? 'gpt-rag';
    }

    /**
     * Muat pesan dengan rendering markdown
     */

    // public function loadMessages(): void
    // {
    //     $this->messages = $this->conversation->messages()
    //         ->oldest()
    //         ->get()
    //         ->map(function (Message $message) {
    //             // Render markdown untuk tampilan yang lebih baik
    //             $message->content = app(MarkdownRenderer::class)
    //                 ->highlightTheme('github-dark')
    //                 ->toHtml($message->content);

    //             return $message;
    //         });
    // }
    public function loadMessages(): void
    {
        $this->messages = $this->conversation->messages()
            ->oldest()
            ->get()
            ->map(function (Message $message) {
                // Cek jika pesan berasal dari AI
                if (!$message->is_user_message) {
                    // Render markdown hanya untuk pesan dari AI
                    $message->content = app(MarkdownRenderer::class)
                        ->highlightTheme('github-dark')
                        ->toHtml($message->content);
                }

                return $message;
            });
    }

    /**
     * Perbarui model AI yang digunakan
     */

    public function updateModel($model)
    {
        $this->model = $model;
        $this->conversation->update(['model' => $model]);
    }
    /**
     * Refresh percakapan saat ini
     */
    public function refreshConversation()
    {
        // Pastikan percakapan yang dipilih masih ada
        if (!auth()->user()->conversations()->find($this->conversation->id)) {
            $this->conversation = auth()->user()->conversations()->create([]);
            return redirect()->route('chat.show', $this->conversation);
        }
        $this->loadMessages();
        $this->resetChat();
    }
    /**
     * Kirim pesan pengguna ke database
     */

    // public function sendMessage($text): void
    // {
    //     if (empty(trim($text))) {
    //         return;
    //     }

    //     $this->conversation->messages()->create([
    //         'content' => $text,
    //         'is_user_message' => true
    //     ]);

    //     // Refresh percakapan setelah mengirim pesan
    //     $this->conversation->refresh();
    // }

    public function sendMessage($text): void
    {
        try {
            Log::info('cek sendmessage', ['text' => $text]);
            // Cek apakah pesan kosong
            if (empty(trim($text))) {
                return;
            }

            // Simpan pesan dalam percakapan
            $this->conversation->messages()->create([
                'content' => $text,
                'is_user_message' => true
            ]);

            // Refresh percakapan setelah mengirim pesan
            $this->conversation->refresh();
        } catch (\Exception $e) {
            // Log error jika terjadi exception
            Log::error('Failed to send message: ' . $e->getMessage(), [
                'error' => $e,
                'text' => $text,
                'conversation_id' => $this->conversation->id ?? null
            ]);
        }
    }

    /**
     * Dapatkan konteks persona pengguna
     */
    private function getPersonaContext(): array
    {
        $user = auth()->user();
        if ($user && $user->persona_id) {
            $persona = Personas::find($user->persona_id);

            if ($persona) {
                return [
                    [
                        'role' => 'system',
                        'content' => $persona->ai_prompt . $persona->description . "\n"
                    ]
                ];
            }
        }
        return [];
    }

    /**
     * Generate judul percakapan jika diperlukan
     */
    private function generateTitleIfNeeded(): void
    {
        if (is_null($this->conversation->title)) {
            try {
                $conversationMessages = $this->conversation->messages->map(function ($message) {
                    return [
                        'role' => $message->is_user_message ? 'user' : 'assistant',
                        'content' => $message->content
                    ];
                })->toArray();

                $title = $this->chatGeneratorService->generateTitle($conversationMessages);
                $this->js("document.title = '{$title}'");
                $this->conversation->update(['title' => $title]);
            } catch (\Exception $e) {
                Log::warning("Failed to generate title: {$e->getMessage()}");
                // Fallback ke judul default jika gagal
                $this->conversation->update(['title' => 'New Chat']);
            }
        }
    }

    /**
     * Respond to user message with streaming
     */
    public function respond(): void
    {
        $this->errorMessage = '';
        $this->responding = true;

        try {
            // Generate judul jika diperlukan
            $this->generateTitleIfNeeded();

            // Pilih metode generasi berdasarkan model
            $stream = match ($this->model) {
                'gpt-rag-persona' => $this->chatGeneratorService->generateWithRAGPersona(
                    conversation: $this->conversation,
                    personaContext: $this->getPersonaContext()
                ),
                'gpt-rag' => $this->chatGeneratorService->generateWithRAG($this->conversation),
                default => $this->chatGeneratorService->generateResponseAI($this->conversation)
            };

            // // Kumpulkan seluruh respons dari stream
            // $allContent = '';
            // foreach ($stream as $chunk) {
            //     $content = $chunk->choices[0]->delta->content ?? '';
            //     $allContent .= $content;
            // }

            // dd($allContent); // Ini akan menampilkan konten respons lengkap

            // $entireMessage = '';
            // // Proses stream token per token
            // foreach ($stream as $response) {
            //     $content = $response->choices[0]->delta->content ?? '';
            //     if ($content) {
            //         $entireMessage .= $content;
            //         $this->answer = $content;
            //         $this->stream(to: 'response', content: $content);
            //     }
            // }

            $entireMessage = '';
            foreach ($stream as $response) {
                $content = $response->choices[0]->delta->content ?? '';
                // Periksa apakah content adalah string dan bukan null
                if (isset($content) || $content === "0") {
                    $entireMessage .= $content;
                    $this->answer = $content;
                    $this->stream(to: 'response', content: $content);
                }
            }

            // Simpan pesan lengkap setelah streaming selesai
            if (!empty($entireMessage)) {
                $this->conversation->messages()->create([
                    'content' => $entireMessage,
                    'is_user_message' => false
                ]);
            }

            // Trigger event bahwa pesan telah ditambahkan
            $this->dispatch('messageAdded');
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();

            // Kirim pesan error yang lebih ramah pengguna
            $this->errorMessage = "Terjadi kesalahan saat menghasilkan respons. Silakan coba lagi.";
            $this->dispatch('aiError', ['message' => $this->errorMessage]);
        } finally {
            $this->responding = false;
        }
    }
    /**
     * Reset state chat
     */
    public function clearState(): void
    {
        $this->responding = false;
        $this->answer = null;
        $this->errorMessage = '';
    }

    /**
     * Reset seluruh chat
     */
    public function resetChat(): void
    {
        $this->answer = null;
        $this->responding = false;
        $this->errorMessage = '';
        $this->loadMessages();
    }

    public function render()
    {
        return view('livewire.components.chat-bot');
    }
}
