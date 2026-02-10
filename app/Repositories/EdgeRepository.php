<?php

namespace App\Repositories;

use App\Models\Edge;
use App\Models\Node;
use Illuminate\Database\Eloquent\Collection;

class EdgeRepository
{
    /**
     * Create a new edge between two nodes.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Edge
    {
        return Edge::create($data);
    }

    /**
     * Create an edge between two nodes with relation.
     */
    public function connect(Node $source, Node $target, string $relation, float $weight = 1.0): Edge
    {
        return Edge::create([
            'source_id' => $source->id,
            'target_id' => $target->id,
            'relation' => $relation,
            'weight' => $weight,
        ]);
    }

    /**
     * Find an edge by ID.
     */
    public function findById(int $id): ?Edge
    {
        return Edge::find($id);
    }

    /**
     * Get all edges for a source node.
     *
     * @return Collection<int, Edge>
     */
    public function getBySource(Node $node): Collection
    {
        return $node->outgoingEdges()->with('target')->get();
    }

    /**
     * Get all edges for a target node.
     *
     * @return Collection<int, Edge>
     */
    public function getByTarget(Node $node): Collection
    {
        return $node->incomingEdges()->with('source')->get();
    }

    /**
     * Get all edges between two nodes.
     *
     * @return Collection<int, Edge>
     */
    public function getBetween(Node $source, Node $target): Collection
    {
        return Edge::where('source_id', $source->id)
            ->where('target_id', $target->id)
            ->get();
    }

    /**
     * Delete an edge.
     */
    public function delete(Edge $edge): ?bool
    {
        return $edge->delete();
    }

    /**
     * Delete all edges for a node.
     */
    public function deleteByNode(Node $node): int
    {
        return Edge::where('source_id', $node->id)
            ->orWhere('target_id', $node->id)
            ->delete();
    }

    /**
     * Find or create an edge between two nodes.
     */
    public function findOrCreate(Node $source, Node $target, string $relation, float $weight = 1.0): Edge
    {
        $existing = Edge::where('source_id', $source->id)
            ->where('target_id', $target->id)
            ->where('relation', $relation)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->connect($source, $target, $relation, $weight);
    }
}
