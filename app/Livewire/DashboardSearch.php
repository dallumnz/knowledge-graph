<?php

namespace App\Livewire;

use App\Services\EmbeddingService;
use App\Services\VectorStore;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class DashboardSearch extends Component
{
    public string $query = '';

    public array $results = [];

    public bool $isSearching = false;

    public bool $hasSearched = false;

    public string $errorMessage = '';

    public function updatedQuery(): void
    {
        $this->hasSearched = false;
        $this->results = [];
        $this->errorMessage = '';
    }

    public function search(): void
    {
        if (trim($this->query) === '') {
            $this->errorMessage = 'Please enter a search query';

            return;
        }

        $this->isSearching = true;
        $this->errorMessage = '';
        $this->hasSearched = true;

        try {
            $embeddingService = app(EmbeddingService::class);
            $vectorStore = app(VectorStore::class);

            // Generate embedding for the query
            $queryVector = $embeddingService->generateEmbedding($this->query);

            if ($queryVector === null) {
                $this->errorMessage = 'Failed to generate search embedding';
                $this->isSearching = false;

                return;
            }

            // Search for similar vectors
            $searchResults = $vectorStore->searchSimilar($queryVector, 10, 0.0);

            // Format results
            $this->results = array_map(function ($result) {
                return [
                    'id' => $result['node']->id,
                    'type' => $result['node']->type,
                    'content' => $result['node']->content,
                    'score' => round($result['score'], 4),
                    'created_at' => $result['node']->created_at->diffForHumans(),
                ];
            }, $searchResults);
        } catch (\Exception $e) {
            Log::error('Dashboard search failed', [
                'message' => $e->getMessage(),
                'query' => $this->query,
            ]);
            $this->errorMessage = 'Search failed. Please try again.';
        }

        $this->isSearching = false;
    }

    public function clear(): void
    {
        $this->query = '';
        $this->results = [];
        $this->hasSearched = false;
        $this->errorMessage = '';
    }

    public function render()
    {
        return view('livewire.dashboard-search');
    }
}
