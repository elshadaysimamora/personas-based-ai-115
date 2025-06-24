<?php

namespace App\Services\Search;

use App\Models\Document;
use App\Services\Embeddings\EmbeddingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HybridSearchService
{
    private const SIMILARITY_THRESHOLD = 0.4;
    private const TITLE_FILTER_THRESHOLD = 3; // Minimum jumlah dokumen untuk difilter berdasarkan judul
    private const MAX_FILTERED_DOCS = 10;      // Maksimum jumlah dokumen dari keyword filtering

    public function __construct(
        private EmbeddingService $embeddingService
    ) {}

    public function search(string $query, int $topK = 5): Collection
    {
        try {
            $processedQuery = $this->preprocessQuery($query);
            $relevantDocumentIds = $this->getRelevantDocumentIdsByTitle($processedQuery);
            if (count(value: $relevantDocumentIds) < self::TITLE_FILTER_THRESHOLD) {
                return $this->vectorSearch(query: $processedQuery, topK: $topK); 
            }
            return $this->vectorSearchWithFilter(query: $processedQuery, documentIds: $relevantDocumentIds, topK: $topK);
        } catch (\Exception $e) {
            throw new \Exception(message: "Gagal melakukan pencarian hybrid: " . $e->getMessage());
        }
    }

    private function getRelevantDocumentIdsByTitle(string $query): array
    {
        // Simple sanitization
        $sanitizedQuery = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $query);
        $sanitizedQuery = trim(preg_replace('/\s+/', ' ', $sanitizedQuery));

        if (empty($sanitizedQuery)) {
            return [];
        }

        // Extract important keywords for searching
        $keywords = preg_split('/\s+/', $sanitizedQuery, -1, PREG_SPLIT_NO_EMPTY);

        /**
         * Strategy 1: Try exact phrase matching with plainto_tsquery
         * berhasil ketika query sama persis dengan judul dokumen
         */
        try {
            $documents = Document::whereRaw("tsv_title @@ plainto_tsquery('simple', ?)", [$sanitizedQuery])
                ->orderByRaw("ts_rank(tsv_title, plainto_tsquery('simple', ?)) DESC", [$sanitizedQuery])
                ->take(self::MAX_FILTERED_DOCS)
                ->pluck('id')
                ->toArray();

            if (!empty($documents)) {
                return $documents;
            }
        } catch (\Exception $e) {
            Log::error("Exact phrase search error: " . $e->getMessage());
        }

        /**
         * Strategy 2: Try with OR conditions for more flexible matching
         * berhasil ketika query memiliki kata kunci yang sama dengan judul dokumen
         */
        try {
            $filteredKeywords = array_filter($keywords, function ($word) {
                return strlen($word) >= 3 && !in_array(
                    strtolower($word),
                    ['dan', 'atau', 'the', 'and', 'for', 'with', 'dalam', 'pada']
                );
            });

            if (!empty($filteredKeywords)) {
                // Build a tsquery with OR operators
                $tsQueryTerms = implode(' | ', $filteredKeywords);

                $documents = Document::whereRaw("tsv_title @@ to_tsquery('simple', ?)", [$tsQueryTerms])
                    ->orderByRaw("ts_rank(tsv_title, to_tsquery('simple', ?)) DESC", [$tsQueryTerms])
                    ->take(self::MAX_FILTERED_DOCS)
                    ->pluck('id')
                    ->toArray();

                if (!empty($documents)) {
                    return $documents;
                }
            }
        } catch (\Exception $e) {
            Log::error("OR-based search error: " . $e->getMessage());
        }

        /**
         * Strategy 3: Try with stemming wildcards for partial matches
         * berhasil ketika query memiliki kata kunci yang mirip dengan judul dokumen, misalnya "makan" dan "makanan"
         */
        try {
            if (!empty($filteredKeywords)) {
                // Build a tsquery with OR operators and stemming
                $tsQueryTerms = implode(' | ', array_map(function ($term) {
                    return $term . ':*';
                }, $filteredKeywords));

                $documents = Document::whereRaw("tsv_title @@ to_tsquery('simple', ?)", [$tsQueryTerms])
                    ->orderByRaw("ts_rank(tsv_title, to_tsquery('simple', ?)) DESC", [$tsQueryTerms])
                    ->take(self::MAX_FILTERED_DOCS)
                    ->pluck('id')
                    ->toArray();
                if (!empty($documents)) {
                    return $documents;
                }
            }
        } catch (\Exception $e) {
            Log::error("Stemming-based search error: " . $e->getMessage());
        }

        // Final fallback to ILIKE - more permissive
        return $this->fallbackTitleSearch($sanitizedQuery);
    }

    private function fallbackTitleSearch(string $query): array
    {
        $keywords = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($keywords)) {
            return [];
        }

        // Filter out short keywords
        $keywords = array_filter($keywords, function ($word) {
            return strlen($word) >= 3;
        });

        if (empty($keywords)) {
            return [];
        }

        $titleQuery = Document::query();

        // Build the query with each keyword as an OR condition
        $titleQuery->where(function ($q) use ($keywords) {
            foreach ($keywords as $i => $keyword) {
                $method = ($i === 0) ? 'where' : 'orWhere';
                $q->$method('title', 'ILIKE', "%$keyword%");
            }
        });

        return $titleQuery->take(self::MAX_FILTERED_DOCS)->pluck('id')->toArray();
    }

    private function vectorSearch(string $query, int $topK): Collection
    {
        // Buat embedding untuk query
        $queryEmbedding = $this->embeddingService->createEmbedding($query);
        $embeddingJson = json_encode($queryEmbedding);

        // Search tanpa filter dokumen
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
            ->limit($topK * 2)
            ->setBindings([$embeddingJson, $embeddingJson], 'select')
            ->get();

        // Filter hasil berdasarkan threshold
        $filteredResults = $relevantChunks->filter(function ($chunk) {
            return $chunk->similarity >= self::SIMILARITY_THRESHOLD;
        })->take($topK);

        // Jika tidak ada hasil di atas threshold, ambil hasil terbaik
        // if ($filteredResults->isEmpty() && $relevantChunks->isNotEmpty()) {
        //     return $this->postProcessResults($relevantChunks->take($topK));
        // }

        return $this->postProcessResults($filteredResults);
    }

    private function vectorSearchWithFilter(string $query, array $documentIds, int $topK): Collection
    {
        try {
            // Buat embedding untuk query
            $queryEmbedding = $this->embeddingService->createEmbedding($query);
            $embeddingJson = json_encode($queryEmbedding);

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
                ->whereIn('document_chunks.document_id', $documentIds)
                ->orderByRaw('embedding <=> ?')
                ->limit($topK * 2)
                ->addBinding($embeddingJson, 'select')  // Untuk similarity di select
                ->addBinding($embeddingJson, 'order')   // Untuk orderByRaw
                ->get();

            // Filter hasil berdasarkan threshold
            $filteredResults = $relevantChunks->filter(function ($chunk) {
                return $chunk->similarity >= self::SIMILARITY_THRESHOLD;
            })->take($topK);

            // Jika tidak ada hasil di atas threshold, ambil hasil terbaik
            // if ($filteredResults->isEmpty() && $relevantChunks->isNotEmpty()) {
            //     return $this->postProcessResults($relevantChunks->take($topK));
            // }

            return $this->postProcessResults($filteredResults);
        } catch (\Exception $e) {
            Log::error('Vector search detailed error', [
                'query' => $query,
                'documentIds' => $documentIds,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
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
        $baseResults = $this->search($query, $topK);
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
