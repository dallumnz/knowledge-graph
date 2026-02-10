<?php

namespace App\Repositories;

use App\Models\Node;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class NodeRepository
{
    /**
     * Create a new node.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Node
    {
        return Node::create($data);
    }

    /**
     * Find a node by ID with optional eager loading.
     *
     * @param  array<string>  $with
     */
    public function findById(int $id, array $with = []): ?Node
    {
        return Node::with($with)->find($id);
    }

    /**
     * Get all nodes with optional filtering by type.
     *
     * @param  array<string>  $with
     */
    public function getAll(?string $type = null, array $with = []): Collection
    {
        $query = Node::with($with);

        if ($type !== null) {
            $query->where('type', $type);
        }

        return $query->get();
    }

    /**
     * Get paginated nodes.
     *
     * @param  array<string>  $with
     */
    public function paginate(int $perPage = 15, ?string $type = null, array $with = []): LengthAwarePaginator
    {
        $query = Node::with($with);

        if ($type !== null) {
            $query->where('type', $type);
        }

        return $query->paginate($perPage);
    }

    /**
     * Update a node.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Node $node, array $data): bool
    {
        return $node->update($data);
    }

    /**
     * Delete a node.
     */
    public function delete(Node $node): ?bool
    {
        return $node->delete();
    }

    /**
     * Find nodes by IDs.
     *
     * @param  array<int>  $ids
     * @param  array<string>  $with
     * @return Collection<int, Node>
     */
    public function findByIds(array $ids, array $with = []): Collection
    {
        return Node::with($with)->whereIn('id', $ids)->get();
    }

    /**
     * Search nodes by content.
     *
     * @param  array<string>  $with
     * @return Collection<int, Node>
     */
    public function searchByContent(string $query, array $with = []): Collection
    {
        return Node::with($with)
            ->where('content', 'like', '%'.$query.'%')
            ->get();
    }
}
