<?php

namespace App\Livewire;

use App\Models\Edge;
use App\Models\Embedding;
use App\Models\Node;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class DashboardStats extends Component
{
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

        // Get counts
        $this->nodeCount = Node::count();
        $this->edgeCount = Edge::count();
        $this->embeddingCount = Embedding::count();

        // Get node type distribution
        $this->nodeTypeDistribution = Node::query()
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
        $this->recentNodes = Node::query()
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

        $this->isLoading = false;
    }

    public function refreshStats(): void
    {
        $this->loadStats();
    }

    public function render()
    {
        return view('livewire.dashboard-stats');
    }
}
