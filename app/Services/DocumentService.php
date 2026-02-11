<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing documents in the knowledge graph.
 *
 * Provides CRUD operations for documents and handles document-related
 * business logic including filtering and pagination.
 */
class DocumentService
{
    /**
     * Create a new document.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Document
    {
        $document = Document::create([
            'title' => $data['title'],
            'source_type' => $data['source_type'],
            'source_path' => $data['source_path'] ?? null,
            'content' => $data['content'] ?? null,
            'metadata' => $data['metadata'] ?? [],
            'version' => $data['version'] ?? 1,
            'is_active' => $data['is_active'] ?? true,
        ]);

        Log::info('Document created', [
            'document_id' => $document->id,
            'title' => $document->title,
        ]);

        return $document;
    }

    /**
     * Find a document by ID.
     */
    public function find(int $id): ?Document
    {
        return Document::find($id);
    }

    /**
     * Find a document by ID with its nodes loaded.
     */
    public function findWithNodes(int $id): ?Document
    {
        return Document::with('nodes')->find($id);
    }

    /**
     * List documents with optional filters and pagination.
     *
     * @param  array<string, mixed>|null  $filters
     */
    public function list(?array $filters = null): LengthAwarePaginator
    {
        $query = Document::query();

        if ($filters !== null) {
            // Filter by IDs
            if (isset($filters['ids']) && is_array($filters['ids'])) {
                $query->whereIn('id', $filters['ids']);
            }

            // Filter by source_type
            if (isset($filters['source_type'])) {
                $query->where('source_type', $filters['source_type']);
            }

            // Filter by is_active
            if (isset($filters['is_active'])) {
                $query->where('is_active', $filters['is_active']);
            }

            // Filter by title (partial match)
            if (isset($filters['title'])) {
                $query->where('title', 'like', '%'.$filters['title'].'%');
            }

            // Filter by metadata key-value pairs
            if (isset($filters['metadata']) && is_array($filters['metadata'])) {
                foreach ($filters['metadata'] as $key => $value) {
                    $query->whereJsonContains('metadata', [$key => $value]);
                }
            }
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get all documents without pagination.
     *
     * @param  array<string, mixed>|null  $filters
     * @return Collection<int, Document>
     */
    public function getAll(?array $filters = null): Collection
    {
        $query = Document::query();

        if ($filters !== null) {
            // Filter by IDs
            if (isset($filters['ids']) && is_array($filters['ids'])) {
                $query->whereIn('id', $filters['ids']);
            }

            if (isset($filters['source_type'])) {
                $query->where('source_type', $filters['source_type']);
            }

            if (isset($filters['is_active'])) {
                $query->where('is_active', $filters['is_active']);
            }
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Update a document.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Document $document, array $data): bool
    {
        $updated = $document->update($data);

        if ($updated) {
            Log::info('Document updated', [
                'document_id' => $document->id,
            ]);
        }

        return $updated;
    }

    /**
     * Delete a document.
     *
     * Note: Nodes will have their document_id set to NULL due to onDelete('set null').
     */
    public function delete(Document $document): ?bool
    {
        $id = $document->id;
        $deleted = $document->delete();

        if ($deleted) {
            Log::info('Document deleted', [
                'document_id' => $id,
            ]);
        }

        return $deleted;
    }

    /**
     * Get chunks (nodes) for a document.
     *
     * @return Collection<int, \App\Models\Node>
     */
    public function getChunks(int $documentId): Collection
    {
        $document = $this->findWithNodes($documentId);

        if ($document === null) {
            return new Collection;
        }

        return $document->nodes;
    }

    /**
     * Increment document version.
     */
    public function incrementVersion(Document $document): bool
    {
        return $document->update([
            'version' => $document->version + 1,
        ]);
    }

    /**
     * Activate or deactivate a document.
     */
    public function setActive(Document $document, bool $active): bool
    {
        return $document->update([
            'is_active' => $active,
        ]);
    }
}
