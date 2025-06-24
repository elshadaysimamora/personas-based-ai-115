<?php

namespace App\Services\Chat;

use App\Models\Conversation;
use BladeUI\Icons\Factory;
use OpenAI\Laravel\Facades\OpenAI;
use App\Services\Search\HybridSearchService;
use App\Services\Search\VectorSearchService;
use Illuminate\Support\Collection;
use App\Enums\ModelType;
use App\Exceptions\AIServiceException;
use Illuminate\Support\Facades\Log;

class ChatGeneratorService
{
    private VectorSearchService $vectorSearchService;
    private HybridSearchService $hybridSearchService;

    private const DEFAULT_MODEL = "chatgpt-4o-latest";
    private const DEFAULT_TEMPERATURE = 0.7;
    private const DEFAULT_MAX_TOKENS = 2000;
    private const TITLE_MAX_TOKENS = 60;
    private const MAX_RELEVANT_DOCS = 3;

    public function __construct(VectorSearchService $vectorSearchService, HybridSearchService $hybridSearchService)
    {
        $this->vectorSearchService = $vectorSearchService;
        $this->hybridSearchService = $hybridSearchService;
    }


    /**
     * Generate response with RAG and Persona context
     */

    public function generateWithRAGPersona(Conversation $conversation, array $personaContext)
    {
        try {
            $lastMessage = $conversation->messages->last();
            $query = $lastMessage->content;

            // Dapatkan dokumen yang relevan
            $relevantDocuments = $this->getRelevantDocuments($query, self::MAX_RELEVANT_DOCS);

            // Siapkan prompt konteks untuk RAG
            $ragContext = $this->prepareRAGContext($relevantDocuments);

            // Dapatkan pesan dari percakapan
            $conversationMessages = $this->getConversationMessages($conversation);

            // Gabungkan semua pesan dalam urutan yang benar
            $allMessages = array_merge(
                $personaContext,
                [['role' => 'system', 'content' => $ragContext['prompt']]],
                [['role' => 'system', 'content' => $ragContext['documents']]],
                $conversationMessages
            );

            return $this->createStreamResponse($allMessages);
        } catch (\Exception $e) {
            Log::error('Error in generateWithRAGPersona: ' . $e->getMessage(), [
                'conversation_id' => $conversation->id,
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception("Failed to generate AI response with RAG and Persona: " . $e->getMessage());
        }
    }

    /**
     * Generate response with RAG only
     */

    public function generateWithRAG(Conversation $conversation)
    {
        try {
            $lastMessage = $conversation->messages->last();
            $query = $lastMessage->content;

            // Dapatkan dokumen yang relevan
            $relevantDocuments = $this->getRelevantDocuments($query, 3);
            // Siapkan prompt konteks untuk RAG
            $ragContext = $this->prepareRAGContext($relevantDocuments);

            // Dapatkan pesan dari percakapan
            $conversationMessages = $this->getConversationMessages($conversation);

            // Gabungkan semua pesan dalam urutan yang benar
            $allMessages = array_merge(
                [['role' => 'system', 'content' => $ragContext['prompt']]],
                [['role' => 'system', 'content' => $ragContext['documents']]],
                $conversationMessages
            );

            return $this->createStreamResponse($allMessages);
        } catch (\Exception $e) {
            Log::error('Error in generateWithRAG: ' . $e->getMessage(), [
                'conversation_id' => $conversation->id,
                'trace' => $e->getTraceAsString()
            ]);

            throw new \Exception("Failed to generate AI response with RAG: " . $e->getMessage());
        }
    }

    /**
     * Generate standard AI response
     */

    public function generateResponseAI(Conversation $conversation)
    {
        try {
            $conversationMessages = $this->getConversationMessages($conversation);
            return $this->createStreamResponse($conversationMessages);
        } catch (\Exception $e) {
            Log::error('Error in generateResponseAI: ' . $e->getMessage(), [
                'conversation_id' => $conversation->id,
                'trace' => $e->getTraceAsString()
            ]);

            throw new \Exception("Gagal menghasilkan respons AI standar: " . $e->getMessage());
        }
    }

    /**
     * Create streaming response with error handling
     */

    private function createStreamResponse(array $messages)
    {
        try {
            $client = \OpenAI::factory()->withApiKey(config('services.openai.api_key'))->make();

            $stream = $client->chat()->createStreamed([
                'model' => self::DEFAULT_MODEL,
                'messages' => $messages,
                'stream' => true,
            ]); 

            return $stream;

        } catch (\Exception $e) {
            Log::error('Error creating stream response: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            throw new \Exception("Koneksi ke layanan AI gagal: " . $e->getMessage());
        }
    }

    /**
     * Generate a title for the conversation
     */

    public function generateTitle(array $messages): string
    {
        try {
            // Filter pesan sistem
            $userMessages = array_filter($messages, function ($message) {
                return $message['role'] !== 'system';
            });

            // Jika tidak ada pesan pengguna, kembalikan default
            if (empty($userMessages)) {
                return 'New Chat';
            }

            $prompt = [
                'role' => 'system',
                'content' => 'Create a short and descriptive title (max. 60 characters) based on this conversation. Return only the title - no quotes, no prefixes.'
            ];

            $messagesWithInstruction = array_merge([$prompt], array_values($userMessages));

            // Batasi jumlah pesan yang dikirim untuk efisiensi
            if (count($messagesWithInstruction) > 5) {
                $messagesWithInstruction = array_merge(
                    [$prompt],
                    array_slice($userMessages, 0, 4)
                );
            }

            $response = OpenAI::chat()->create([
                'model' => self::DEFAULT_MODEL,
                'messages' => $messagesWithInstruction,
                'temperature' => 0.5, // Lebih rendah untuk konsistensi
                'max_tokens' => self::TITLE_MAX_TOKENS,
            ]);

            if (!empty($response->choices)) {
                $title = trim($response->choices[0]->message->content);
                return !empty($title) ? $title : 'New Chat';
            }

            return 'New Chat';
        } catch (\Exception $e) {
            Log::warning('Failed to generate title: ' . $e->getMessage());
            return 'New Chat'; // Fallback jika gagal
        }
    }

    /**
     * Helper: Get conversation messages formatted for AI
     */
    private function getConversationMessages(Conversation $conversation): array
    {
        //log pesan dari percakapan
        return $conversation->messages->map(function ($message) {
            return [
                'role' => $message->is_user_message ? 'user' : 'assistant',
                'content' => $message->content
            ];
        })->toArray();
    }

    /**
     * Helper: Get relevant documents from vector search
     */
    private function getRelevantDocuments(string $query, int $limit): Collection
    {
        //$documents = $this->vectorSearchService->findRelevantDocumentsWithContext($query, $limit);
        $documents = $this->hybridSearchService->findRelevantDocumentsWithContext($query, $limit);

        return $documents->map(function ($doc) {
            return [
                'id' => $doc['id'],
                'document_id' => $doc['document_id'],
                'title' => $doc['title'],
                'content' => $doc['content'],
                'metadata' => $doc['metadata'],
                'similarity_score' => $doc['similarity_score'],
                'chunk_order' => $doc['chunk_order'],
            ];
        });
    }

    /**
     * Helper: Prepare RAG context from relevant documents
     */

    private function prepareRAGContext(Collection $relevantContent): array
    {
        $promptPath = public_path('prompt/rag_prompt.txt');
        $prompt = file_exists($promptPath) ? file_get_contents($promptPath) : 'Default prompt text if file not found';
        $documents = $relevantContent->pluck('content')->implode("\n\n");

        return [
            'prompt' => $prompt,
            'documents' => $documents
        ];
    }
}
