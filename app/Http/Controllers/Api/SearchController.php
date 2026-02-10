<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\NodeRepository;
use App\Services\EmbeddingService;
use App\Services\VectorStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SearchController extends Controller
{
    public function __construct(
        private NodeRepository $nodeRepository,
        private EmbeddingService $embeddingService,
        private VectorStore $vectorStore,
    ) {}

    /**
     * Search for nodes by query string using vector similarity.
     */
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
            'min_similarity' => 'nullable|numeric|min:0|max:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = $request->input('q');
        $limit = $request->input('limit', 10);
        $minSimilarity = $request->input('min_similarity', 0.0);

        // Generate embedding for the query
        $queryVector = $this->embeddingService->generateEmbedding($query);

        if ($queryVector === null) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate query embedding',
            ], 500);
        }

        // Search for similar vectors
        $results = $this->vectorStore->searchSimilar($queryVector, $limit, $minSimilarity);

        // Format results
        $formattedResults = array_map(function ($result) {
            return [
                'id' => $result['node']->id,
                'type' => $result['node']->type,
                'content' => $result['node']->content,
                'score' => round($result['score'], 4),
                'created_at' => $result['node']->created_at->toIso8601String(),
            ];
        }, $results);

        return response()->json([
            'success' => true,
            'data' => [
                'query' => $query,
                'results' => $formattedResults,
                'count' => count($formattedResults),
            ],
        ]);
    }

    /**
     * Get a specific node by ID with its edges.
     */
    public function show(int $id): JsonResponse
    {
        $node = $this->nodeRepository->findById($id, ['embedding', 'outgoingEdges.target', 'incomingEdges.source']);

        if ($node === null) {
            return response()->json([
                'success' => false,
                'message' => 'Node not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $node->id,
                'type' => $node->type,
                'content' => $node->content,
                'created_at' => $node->created_at->toIso8601String(),
                'updated_at' => $node->updated_at->toIso8601String(),
                'has_embedding' => $node->embedding !== null,
                'outgoing_edges' => $node->outgoingEdges->map(function ($edge) {
                    return [
                        'id' => $edge->id,
                        'relation' => $edge->relation,
                        'weight' => $edge->weight,
                        'target_id' => $edge->target_id,
                        'target_content' => $edge->target->content ?? null,
                    ];
                }),
                'incoming_edges' => $node->incomingEdges->map(function ($edge) {
                    return [
                        'id' => $edge->id,
                        'relation' => $edge->relation,
                        'weight' => $edge->weight,
                        'source_id' => $edge->source_id,
                        'source_content' => $edge->source->content ?? null,
                    ];
                }),
            ],
        ]);
    }

    /**
     * Simple text search (non-vector) for nodes.
     */
    public function textSearch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:1',
            'type' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = $request->input('q');
        $type = $request->input('type');

        $nodes = $this->nodeRepository->searchByContent($query);

        if ($type !== null) {
            $nodes = $nodes->where('type', $type);
        }

        $results = $nodes->map(function ($node) {
            return [
                'id' => $node->id,
                'type' => $node->type,
                'content' => $node->content,
                'created_at' => $node->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'query' => $query,
                'results' => $results,
                'count' => $results->count(),
            ],
        ]);
    }
}
