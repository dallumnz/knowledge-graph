<?php

namespace App\Livewire;

use App\Models\Edge;
use App\Models\Embedding;
use App\Models\Node;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class DashboardStats extends Component
{
    use CacheableComponent;

    public int $nodeCount = 0;

    public int $edgeCount = 0;

    public int $embeddingCount = 0;

    public array $nodeTypeDistribution = [];

    public array $recentNodes = [];

    public bool $isLoading = true;

    protected $listeners = ['node-created' => 'onNodeCreated'];

    public function mount(): void
    {
        $this->loadStats();
    }

    public function onNodeCreated(): void
    {
        $this->loadStats();
    }

    public function loadStats(): void
    {
        $this->isLoading = true;

        // Use cached stats for faster loads
        $stats = $this->getCachedStats();

        $this->nodeCount = $stats['nodeCount'];
        $this->edgeCount = $stats['edgeCount'];
        $this->embeddingCount = $stats['embeddingCount'];
        $this->nodeTypeDistribution = $stats['nodeTypeDistribution'];
        $this->recentNodes = $stats['recentNodes'];

        $this->isLoading = false;
    }

    /**
     * Get cached statistics.
     *
     * @return array
     */
    protected function getCachedStats(): array
    {
        return $this->getCachedData('stats', function () {
            return $this->computeStats();
        }, 60); // Cache for 1 minute
    }

    /**
     * Compute statistics (uncached).
     *
     * @return array
     */
    protected function computeStats(): array
    {
        // Get counts
        $nodeCount = Node::count();
        $edgeCount = Edge::count();
        $embeddingCount = Embedding::count();

        // Get node type distribution
        $nodeTypeDistribution = Node::query()
            ->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn ($item) => [
                'type' => $item->type,
                'count' => $item->count,
            ])
            ->toArray();

        // Get recent nodes
        $recentNodes = Node::query()
            ->with('embedding')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($node) => [
                'id' => $node->id,
                'type' => $node->type,
                'content' => str($node->content)->limit(100),
                'has_embedding' => $node->embedding !== null,
                'created_at' => $node->created_at->diffForHumans(),
            ])
            ->toArray();

        return [
            'nodeCount' => $nodeCount,
            'edgeCount' => $edgeCount,
            'embeddingCount' => $embeddingCount,
            'nodeTypeDistribution' => $nodeTypeDistribution,
            'recentNodes' => $recentNodes,
        ];
    }

    public function refreshStats(): void
    {
        $this->clearComponentCache('stats');
        $this->loadStats();
        $this->dispatch('stats-refreshed');
    }

    public function render()
    {
        return view('livewire.dashboard-stats');
    }
}
