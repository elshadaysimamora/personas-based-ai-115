<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Documents\DocumentChunkingService;
use App\Services\Documents\TextExactorService;
use App\Services\Embeddings\EmbeddingService;
use App\Services\Search\VectorSearchService;
use App\Services\Search\HybridSearchService;
use App\Services\Chat\ChatGeneratorService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */

    public function register(): void
    {
        $this->app->bind(DocumentChunkingService::class, function ($app) {
            return new DocumentChunkingService(
                $app->make(TextExactorService::class),
                $app->make(EmbeddingService::class)
            );
        });

        $this->app->bind(VectorSearchService::class, function ($app) {
            return new VectorSearchService(
                $app->make(EmbeddingService::class)
            );
        });

        $this->app->bind(HybridSearchService::class, function ($app) {
            return new HybridSearchService(
                $app->make(EmbeddingService::class)
            );
        });
        
        $this->app->singleton(ChatGeneratorService::class, function ($app) {
            return new ChatGeneratorService(
                vectorSearchService: $app->make(VectorSearchService::class),
                hybridSearchService: $app->make(HybridSearchService::class)
                
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
