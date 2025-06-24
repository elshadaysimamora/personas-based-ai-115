<?php

namespace App\Services\Search;

use App\Models\DocumentChunk;
use App\Models\Documents;
use App\Services\Embeddings\EmbeddingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Pgvector\Laravel\Distance;


class VectorSearchService
{
    private const SIMILARITY_THRESHOLD = 0.4;

    public function __construct(
        private EmbeddingService $embeddingService
    ) {}

    public function findRelevantDocuments(string $query, int $topK = 5): Collection
    {
        try {
            // Validasi dan preprocessing query
            $processedQuery = $this->preprocessQuery($query);
            
            // Buat embedding untuk query
            $queryEmbedding = $this->embeddingService->createEmbedding($processedQuery);
            if (empty($queryEmbedding)) {
                throw new \Exception("Gagal membuat embedding untuk query");
            }
            
            // Konversi embedding ke format JSON
            $embeddingJson = json_encode($queryEmbedding);
            
            // Gunakan indeks HNSW dengan cosine distance
            $relevantChunks = DB::table('document_chunks')
                ->select([
                    'document_chunks.id',
                    'document_chunks.document_id',
                    'document_chunks.content',
                    'document_chunks.metadata',
                    'document_chunks.chunk_order',
                    'documents.title',
                    DB::raw('1 - (embedding <=> ?) as similarity')
                ])
                ->join('documents', 'document_chunks.document_id', '=', 'documents.id')
                ->orderByRaw('embedding <=> ?')
                ->limit($topK * 2) // Ambil lebih banyak untuk difilter
                ->setBindings([$embeddingJson, $embeddingJson], 'select')
                ->get();
            
            // Filter hasil berdasarkan threshold
            $filteredResults = $relevantChunks->filter(function ($chunk) {
                return $chunk->similarity >= self::SIMILARITY_THRESHOLD;
            })->take($topK);
            
            // Jika tidak ada hasil yang memenuhi threshold, ambil hasil terbaik saja
            if ($filteredResults->isEmpty() && $relevantChunks->isNotEmpty()) {
                Log::info('Tidak ada hasil di atas threshold, mengembalikan hasil terbaik', [
                    'query' => $query, 
                    'top_similarity' => $relevantChunks->first()->similarity
                ]);
                
                return $this->postProcessResults($relevantChunks->take($topK));
            }
            
            return $this->postProcessResults($filteredResults);
        } catch (\Exception $e) {
            Log::error('Document Retrieval Error', [
                'query' => $query,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception("Gagal mencari dokumen relevan: " . $e->getMessage());
        }
    }


    private function preprocessQuery(string $query): string
    {
        if (empty(trim($query))) {
            throw new \Exception("Query tidak boleh kosong");
        }
        return preg_replace('/\s+/', ' ', trim($query));
    }

    private function postProcessResults(Collection $chunks): Collection
    {
        return $chunks->map(function ($chunk) {
            $metadata = json_decode($chunk->metadata, true) ?? [];

            return [
                'id' => $chunk->id,
                'document_id' => $chunk->document_id,
                'title' => $chunk->title,
                'content' => $chunk->content,
                'similarity_score' => round(num: $chunk->similarity * 100, precision: 2),
                'chunk_order' => $chunk->chunk_order,
                'metadata' => $metadata,
            ];
        });
    }

    public function findRelevantDocumentsWithContext(string $query, int $topK = 3): Collection
    {
        $baseResults = $this->findRelevantDocuments($query, $topK);
        return $this->addContextToResults($baseResults);
    }

    private function addContextToResults(Collection $results): Collection
    {
        return $results->map(function ($result) {
            // Ambil chunk sebelum dan sesudah
            $surroundingChunks = DB::table('document_chunks')
                ->where('document_id', $result['document_id'])
                ->whereBetween('chunk_order', [
                    $result['chunk_order'] - 1,
                    $result['chunk_order'] + 1
                ])
                ->orderBy('chunk_order')
                ->get();

            $result['context'] = [
                'previous' => $surroundingChunks->where('chunk_order', '<', $result['chunk_order'])->first(),
                'next' => $surroundingChunks->where('chunk_order', '>', $result['chunk_order'])->first(),
            ];

            return $result;
        });
    }
}
