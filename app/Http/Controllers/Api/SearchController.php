<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\NodeRepository;
use App\Services\Ai\HybridSearchService;
use App\Services\Ai\ReRankingService;
use App\Services\DocumentService;
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
        private DocumentService $documentService,
        private ?HybridSearchService $hybridSearchService = null,
        private ?ReRankingService $reRankingService = null,
    ) {
        $this->hybridSearchService ??= new HybridSearchService($vectorStore, $embeddingService);
        $this->reRankingService ??= new ReRankingService();
    }

    /**
     * Search for nodes by query string using vector similarity.
     *
     * @deprecated Use hybrid() for better results
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

        // Format and return results
        return $this->formatSearchResults($query, $results, 'vector');
    }

    /**
     * Hybrid search combining vector, keyword, and hypothetical question matching.
     *
     * GET /api/search/hybrid?q={query}&limit=5&rerank=true&alpha=0.7
     *
     * Parameters:
     * - q: Search query (required)
     * - limit: Number of final results (default: 5)
     * - rerank: Enable LLM re-ranking (default: true)
     * - alpha: Weight for vector vs keyword (0.0-1.0, default: 0.7)
     */
    public function hybrid(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:1',
            'limit' => 'nullable|integer|min:1|max:50',
            'rerank' => 'nullable|boolean',
            'alpha' => 'nullable|numeric|min:0|max:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = $request->input('q');
        $limit = $request->input('limit', config('ai.search.final_results', 5));
        $rerank = $request->boolean('rerank', true);
        $alpha = $request->input('alpha', 0.7);

        // Perform hybrid search
        $hybridResults = $this->hybridSearchService->search(
            $query,
            $rerank ? config('ai.search.rerank_top_n', 20) : $limit,
            $alpha
        );

        if (empty($hybridResults)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'query' => $query,
                    'results' => [],
                    'count' => 0,
                    'method' => 'hybrid',
                    'reranked' => false,
                ],
            ]);
        }

        // Re-rank if enabled
        if ($rerank) {
            $finalResults = $this->reRankingService->rerank($query, $hybridResults, null, $limit);
            $method = 'hybrid+rerank';
        } else {
            $finalResults = array_slice($hybridResults, 0, $limit);
            // Add metadata structure for consistency
            $finalResults = array_map(fn ($r) => [
                'node' => $r['node'],
                'score' => $r['score'],
                'relevance_score' => $r['score'],
                'metadata' => [
                    'sources' => $r['sources'] ?? [],
                    'vector_score' => $r['vector_score'] ?? 0,
                    'keyword_score' => $r['keyword_score'] ?? 0,
                    'question_score' => $r['question_score'] ?? 0,
                ],
            ], $finalResults);
            $method = 'hybrid';
        }

        // Collect document IDs for eager loading
        $documentIds = [];
        foreach ($finalResults as $result) {
            if ($result['node']->document_id !== null) {
                $documentIds[] = $result['node']->document_id;
            }
        }

        // Eager load documents
        $documents = [];
        if (! empty($documentIds)) {
            $documents = $this->documentService->getAll(['ids' => array_unique($documentIds)])
                ->keyBy('id')
                ->all();
        }

        // Format results
        $formattedResults = array_map(function ($result) use ($documents) {
            $node = $result['node'];
            $document = null;

            if ($node->document_id !== null && isset($documents[$node->document_id])) {
                $doc = $documents[$node->document_id];
                $document = [
                    'id' => $doc->id,
                    'title' => $doc->title,
                    'source_type' => $doc->source_type,
                    'source_path' => $doc->source_path,
                ];
            }

            return [
                'node' => [
                    'id' => $node->id,
                    'type' => $node->type,
                    'content' => $node->content,
                    'document_id' => $node->document_id,
                    'metadata' => [
                        'hypothetical_questions' => $node->metadata['hypothetical_questions'] ?? [],
                        'keywords' => $node->metadata['keywords'] ?? [],
                    ],
                    'created_at' => $node->created_at->toIso8601String(),
                ],
                'score' => $result['score'],
                'relevance_score' => $result['relevance_score'] ?? $result['score'],
                'sources' => $result['metadata']['sources'] ?? [],
                'document' => $document,
            ];
        }, $finalResults);

        return response()->json([
            'success' => true,
            'data' => [
                'query' => $query,
                'results' => $formattedResults,
                'count' => count($formattedResults),
                'method' => $method,
                'reranked' => $rerank,
                'alpha' => $alpha,
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
                'metadata' => $node->metadata,
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

    /**
     * Format search results with document attribution.
     *
     * @param string $query The search query
     * @param array<int, array{node: \App\Models\Node, score: float}> $results Search results
     * @param string $method The search method used
     * @return JsonResponse
     */
    private function formatSearchResults(string $query, array $results, string $method): JsonResponse
    {
        // Collect document IDs for eager loading
        $documentIds = [];
        foreach ($results as $result) {
            if ($result['node']->document_id !== null) {
                $documentIds[] = $result['node']->document_id;
            }
        }

        // Eager load documents
        $documents = [];
        if (! empty($documentIds)) {
            $documents = $this->documentService->getAll(['ids' => array_unique($documentIds)])
                ->keyBy('id')
                ->all();
        }

        // Format results with document attribution
        $formattedResults = array_map(function ($result) use ($documents) {
            $node = $result['node'];
            $document = null;

            if ($node->document_id !== null && isset($documents[$node->document_id])) {
                $doc = $documents[$node->document_id];
                $document = [
                    'id' => $doc->id,
                    'title' => $doc->title,
                    'source_type' => $doc->source_type,
                    'source_path' => $doc->source_path,
                ];
            }

            return [
                'node' => [
                    'id' => $node->id,
                    'type' => $node->type,
                    'content' => $node->content,
                    'document_id' => $node->document_id,
                    'created_at' => $node->created_at->toIso8601String(),
                ],
                'score' => round($result['score'], 4),
                'document' => $document,
            ];
        }, $results);

        return response()->json([
            'success' => true,
            'data' => [
                'query' => $query,
                'results' => $formattedResults,
                'count' => count($formattedResults),
                'method' => $method,
            ],
        ]);
    }
}
