<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * API Controller for document management.
 *
 * Provides endpoints for CRUD operations on documents and retrieving
 * document chunks (nodes).
 */
class DocumentController extends Controller
{
    public function __construct(
        private DocumentService $documentService,
    ) {}

    /**
     * List all documents with optional filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [];

        if ($request->has('source_type')) {
            $filters['source_type'] = $request->input('source_type');
        }

        if ($request->has('is_active')) {
            $filters['is_active'] = $request->boolean('is_active');
        }

        if ($request->has('title')) {
            $filters['title'] = $request->input('title');
        }

        if ($request->has('per_page')) {
            $filters['per_page'] = $request->integer('per_page');
        }

        $documents = $this->documentService->list($filters);

        return response()->json([
            'success' => true,
            'data' => [
                'documents' => $documents->items(),
                'pagination' => [
                    'current_page' => $documents->currentPage(),
                    'last_page' => $documents->lastPage(),
                    'per_page' => $documents->perPage(),
                    'total' => $documents->total(),
                ],
            ],
        ]);
    }

    /**
     * Get a single document by ID.
     */
    public function show(int $id): JsonResponse
    {
        $document = $this->documentService->find($id);

        if ($document === null) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'document' => $document,
            ],
        ]);
    }

    /**
     * Get all chunks (nodes) for a document.
     */
    public function chunks(int $id): JsonResponse
    {
        $document = $this->documentService->find($id);

        if ($document === null) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found',
            ], 404);
        }

        $chunks = $this->documentService->getChunks($id);

        return response()->json([
            'success' => true,
            'data' => [
                'document' => [
                    'id' => $document->id,
                    'title' => $document->title,
                ],
                'chunks' => $chunks->map(function ($chunk) {
                    return [
                        'id' => $chunk->id,
                        'type' => $chunk->type,
                        'content' => $chunk->content,
                        'created_at' => $chunk->created_at->toIso8601String(),
                    ];
                }),
                'count' => $chunks->count(),
            ],
        ]);
    }

    /**
     * Create a new document.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:500',
            'source_type' => 'required|string|max:50|in:file,url,text,api',
            'source_path' => 'nullable|string',
            'content' => 'nullable|string',
            'metadata' => 'nullable|array',
            'version' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $document = $this->documentService->create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Document created successfully',
            'data' => [
                'document' => $document,
            ],
        ], 201);
    }

    /**
     * Update an existing document.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $document = $this->documentService->find($id);

        if ($document === null) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:500',
            'source_type' => 'sometimes|string|max:50|in:file,url,text,api',
            'source_path' => 'sometimes|nullable|string',
            'content' => 'sometimes|nullable|string',
            'metadata' => 'sometimes|array',
            'version' => 'sometimes|integer|min:1',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $this->documentService->update($document, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Document updated successfully',
            'data' => [
                'document' => $document->fresh(),
            ],
        ]);
    }

    /**
     * Delete a document.
     */
    public function destroy(int $id): JsonResponse
    {
        $document = $this->documentService->find($id);

        if ($document === null) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found',
            ], 404);
        }

        $this->documentService->delete($document);

        return response()->json([
            'success' => true,
            'message' => 'Document deleted successfully',
        ]);
    }
}
