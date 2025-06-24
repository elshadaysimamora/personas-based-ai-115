<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\Personas;
use App\Services\Chat\ChatGeneratorService;
use App\Services\Search\HybridSearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ChatTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:test 
                            {model=gpt-rag : Model yang digunakan (gpt-rag, gpt-rag-persona, atau default)} 
                            {--persona-id= : ID persona untuk gpt-rag-persona}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Uji coba respons AI dengan RAG atau RAG-Persona melalui terminal';

    /**
     * @var ChatGeneratorService
     */
    protected $chatGeneratorService;

    /**
     * @var HybridSearchService
     */
    protected $hybridSearchService;

    /**
     * Create a new command instance.
     */
    public function __construct(
        ChatGeneratorService $chatGeneratorService,
        HybridSearchService $hybridSearchService
    ) {
        parent::__construct();
        $this->chatGeneratorService = $chatGeneratorService;
        $this->hybridSearchService = $hybridSearchService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $model = $this->argument('model');

        // Hardcode prompt untuk uji coba
        $prompt = "Explain in detail the concept of a pointer to a function in the C programming language. Provide an explanation of how a pointer to a function works, the benefits of its use, and situations where this concept is used in programming.";

        // Buat percakapan baru di database
        $conversation = $this->createConversation($model);

        // Tambahkan pesan pengguna ke database
        $conversation->messages()->create([
            'content' => $prompt,
            'is_user_message' => true
        ]);
        $conversation->refresh();

        // Koleksi semua prompt untuk digabungkan
        $allPrompts = [
            'user_query' => $prompt
        ];

        // Dapatkan konteks persona jika menggunakan gpt-rag-persona
        $personaContext = [];
        if ($model === 'gpt-rag-persona') {
            $personaContext = $this->getPersonaContext();
            if (!empty($personaContext)) {
                $allPrompts['persona'] = $personaContext[0]['content'] ?? '';
            }
        }

        // Dapatkan dokumen yang relevan jika menggunakan RAG
        $relevantDocuments = null;
        if (in_array($model, ['gpt-rag', 'gpt-rag-persona'])) {
            $relevantDocuments = $this->getRelevantDocuments($prompt);
            $allPrompts['documents'] = $this->formatDocumentsForDisplay($relevantDocuments);
            
            // Ambil prompt RAG dari file
            $promptPath = public_path('prompt/rag_prompt.txt');
            $ragPrompt = file_exists($promptPath) ? file_get_contents($promptPath) : 'Default RAG prompt text';
            $allPrompts['rag_prompt'] = $ragPrompt;
            
            $this->info("Model: $model");
            $this->line("\n");
        }

        // Tampilkan gabungan semua prompt sebelum respons
        $this->displayAllPrompts($allPrompts);

        try {
            // Pilih metode berdasarkan model
            $stream = match ($model) {
                'gpt-rag-persona' => $this->chatGeneratorService->generateWithRAGPersona($conversation, $personaContext),
                'gpt-rag' => $this->chatGeneratorService->generateWithRAG($conversation),
                default => $this->chatGeneratorService->generateResponseAI($conversation)
            };

            // Tampilkan respons di terminal
            $this->line("\n=== Response ===\n");
            $responseText = '';
            foreach ($stream as $response) {
                $content = $response->choices[0]->delta->content ?? '';
                if (isset($content) && $content !== null) {
                    $responseText .= $content;
                    $this->output->write($content);
                }
            }
            $this->line("\n\n=== End Response ===\n");

            // Simpan respons ke database
            if (!empty($responseText)) {
                $conversation->messages()->create([
                    'content' => $responseText,
                    'is_user_message' => false
                ]);
            }

            $this->info("Conversation ID: " . $conversation->id . " (simpan untuk melanjutkan percakapan)");
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
        }
    }

    /**
     * Membuat percakapan baru
     */
    private function createConversation(string $model): Conversation
    {
        $conversation = new Conversation();
        $conversation->model = $model;
        $conversation->uuid = (string) Str::uuid();
        $conversation->user_id = Auth::id() ?? 1;
        $conversation->save();
        
        return $conversation;
    }

    /**
     * Mendapatkan konteks persona
     */
    private function getPersonaContext(): array
    {
        $personaId = $this->option('persona-id');

        if ($personaId) {
            $persona = Personas::find($personaId);
            if ($persona) {
                $this->info("Using persona: " . $persona->name);
                return [
                    [
                        'role' => 'system',
                        'content' => $persona->ai_prompt . $persona->description . "\n"
                    ]
                ];
            }
        }

        $this->warn("No persona specified or found. Using empty persona context.");
        return [];
    }

    /**
     * Mendapatkan dokumen yang relevan
     */
    private function getRelevantDocuments(string $query, int $limit = 3): Collection
    {
        try {
            $documents = $this->hybridSearchService->findRelevantDocumentsWithContext($query, $limit);

            return $documents->map(function ($doc) {
                return [
                    'content' => $doc['content'],
                ];
            });
        } catch (\Exception $e) {
            $this->warn("Error retrieving relevant documents: " . $e->getMessage());
            return collect([]);
        }
    }

    /**
     * Format dokumen untuk display
     */
    private function formatDocumentsForDisplay(Collection $documents): string
    {
        if ($documents->isEmpty()) {
            return "No relevant documents found.";
        }
        
        $formatted = "";
        foreach ($documents as $document) {
            $formatted .= $document['content'] . "\n\n";
        }
        $formatted .= "";
        return $formatted;
    }

    /**
     * Menampilkan gabungan semua prompt
     */
    private function displayAllPrompts(array $allPrompts): void
    {
        $this->line("\n=== Combined Prompts ===\n");
        
        if (isset($allPrompts['persona'])) {
            $this->line("--- Persona Context ---");
            $this->line($allPrompts['persona']);
            $this->line("\n");
        }
        
        if (isset($allPrompts['rag_prompt'])) {
            $this->line("--- RAG Prompt ---");
            $this->line($allPrompts['rag_prompt']);
            $this->line("\n");
        }
        
        if (isset($allPrompts['documents'])) {
            $this->line("--- Relevant Documents ---");
            $this->line($allPrompts['documents']);
            $this->line("\n");
        }
        
        $this->line("--- User Query ---");
        $this->info($allPrompts['user_query']);
        $this->line("\n");
    }
}

//php artisan chat:test default
//php artisan chat:test gpt-rag
//php artisan chat:test gpt-rag-persona --persona-id=1