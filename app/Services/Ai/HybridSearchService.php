<?php

namespace App\Services\Ai;

use App\Models\Node;
use App\Services\EmbeddingService;
use App\Services\VectorStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Hybrid Search Service for combining multiple search strategies.
 *
 * Combines results from:
 * - Vector similarity search (semantic matching)
 * - Keyword/full-text search (exact matches)
 * - Hypothetical questions (question-to-question matching)
 *
 * Uses weighted scoring to merge and rank results from all sources.
 */
class HybridSearchService
{
    /**
     * Vector store for semantic search.
     */
    private VectorStore $vectorStore;

    /**
     * Embedding service for query vectorization.
     */
    private EmbeddingService $embeddingService;

    /**
     * Weight for vector search results (0.0-1.0).
     */
    private float $vectorWeight;

    /**
     * Weight for keyword search results (0.0-1.0).
     */
    private float $keywordWeight;

    /**
     * Weight for hypothetical question matching (0.0-1.0).
     */
    private float $questionWeight;

    /**
     * Number of results to fetch from each source.
     */
    private int $sourceLimit;

    /**
     * Create a new hybrid search service instance.
     */
    public function __construct(
        ?VectorStore $vectorStore = null,
        ?EmbeddingService $embeddingService = null,
    ) {
        $this->vectorStore = $vectorStore ?? new VectorStore();
        $this->embeddingService = $embeddingService ?? new EmbeddingService();

        // Load weights from config
        $this->vectorWeight = config('ai.search.vector_weight', 0.6);
        $this->keywordWeight = config('ai.search.keyword_weight', 0.3);
        $this->questionWeight = config('ai.search.question_weight', 0.1);

        // Number of results to fetch from each source before merging
        $this->sourceLimit = max(20, config('ai.search.rerank_top_n', 20));
    }

    /**
     * Perform hybrid search combining multiple search strategies.
     *
     * @param string $query The search query
     * @param int $limit Maximum number of results to return
     * @param float $alpha Weight for vector vs keyword (0.0=keyword only, 1.0=vector only)
     * @return array<int, array{node: Node, score: float, sources: array<string>}> Ranked results
     */
    public function search(string $query, int $limit = 10, float $alpha = 0.7): array
    {
        if (empty(trim($query))) {
            Log::warning('Empty query provided for hybrid search');
            return [];
        }

        // Normalize alpha to adjust weights dynamically
        $alpha = max(0.0, min(1.0, $alpha));

        // Adjust weights based on alpha parameter
        // Alpha controls vector vs keyword balance
        $adjustedVectorWeight = $alpha * $this->vectorWeight;
        $adjustedKeywordWeight = (1 - $alpha) * $this->keywordWeight;
        $adjustedQuestionWeight = $this->questionWeight; // Questions are independent

        // Normalize weights to sum to 1.0
        $totalWeight = $adjustedVectorWeight + $adjustedKeywordWeight + $adjustedQuestionWeight;
        if ($totalWeight > 0) {
            $adjustedVectorWeight /= $totalWeight;
            $adjustedKeywordWeight /= $totalWeight;
            $adjustedQuestionWeight /= $totalWeight;
        }

        Log::debug('Hybrid search weights', [
            'alpha' => $alpha,
            'vector' => $adjustedVectorWeight,
            'keyword' => $adjustedKeywordWeight,
            'question' => $adjustedQuestionWeight,
        ]);

        // Collect results from all sources
        $allResults = [];

        // 1. Vector search (semantic similarity)
        $vectorResults = $this->searchVector($query, $this->sourceLimit);
        foreach ($vectorResults as $result) {
            $nodeId = $result['node']->id;
            if (!isset($allResults[$nodeId])) {
                $allResults[$nodeId] = [
                    'node' => $result['node'],
                    'vector_score' => 0.0,
                    'keyword_score' => 0.0,
                    'question_score' => 0.0,
                    'sources' => [],
                ];
            }
            $allResults[$nodeId]['vector_score'] = $result['score'];
            $allResults[$nodeId]['sources'][] = 'vector';
        }

        // 2. Keyword search (full-text)
        $keywordResults = $this->searchKeyword($query, $this->sourceLimit);
        foreach ($keywordResults as $result) {
            $nodeId = $result['node']->id;
            if (!isset($allResults[$nodeId])) {
                $allResults[$nodeId] = [
                    'node' => $result['node'],
                    'vector_score' => 0.0,
                    'keyword_score' => 0.0,
                    'question_score' => 0.0,
                    'sources' => [],
                ];
            }
            $allResults[$nodeId]['keyword_score'] = $result['score'];
            $allResults[$nodeId]['sources'][] = 'keyword';
        }

        // 3. Hypothetical questions search
        $questionResults = $this->searchHypotheticalQuestions($query, $this->sourceLimit);
        foreach ($questionResults as $result) {
            $nodeId = $result['node']->id;
            if (!isset($allResults[$nodeId])) {
                $allResults[$nodeId] = [
                    'node' => $result['node'],
                    'vector_score' => 0.0,
                    'keyword_score' => 0.0,
                    'question_score' => 0.0,
                    'sources' => [],
                ];
            }
            $allResults[$nodeId]['question_score'] = $result['score'];
            $allResults[$nodeId]['sources'][] = 'hypothetical_questions';
        }

        // Calculate weighted scores and sort
        $rankedResults = [];
        foreach ($allResults as $nodeId => $data) {
            $weightedScore = (
                $data['vector_score'] * $adjustedVectorWeight +
                $data['keyword_score'] * $adjustedKeywordWeight +
                $data['question_score'] * $adjustedQuestionWeight
            );

            // Boost results that appear in multiple sources
            $sourceCount = count(array_unique($data['sources']));
            if ($sourceCount > 1) {
                $weightedScore *= (1 + 0.1 * $sourceCount); // 10% boost per additional source
            }

            $rankedResults[] = [
                'node' => $data['node'],
                'score' => round($weightedScore, 4),
                'sources' => array_unique($data['sources']),
                'vector_score' => round($data['vector_score'], 4),
                'keyword_score' => round($data['keyword_score'], 4),
                'question_score' => round($data['question_score'], 4),
            ];
        }

        // Sort by score descending
        usort($rankedResults, fn ($a, $b) => $b['score'] <=> $a['score']);

        // Return top N results
        return array_slice($rankedResults, 0, $limit);
    }

    /**
     * Perform vector similarity search.
     *
     * @param string $query The search query
     * @param int $limit Maximum number of results
     * @return array<int, array{node: Node, score: float}>
     */
    private function searchVector(string $query, int $limit): array
    {
        try {
            $queryVector = $this->embeddingService->generateEmbedding($query);

            if ($queryVector === null) {
                Log::warning('Failed to generate query embedding for vector search');
                return [];
            }

            return $this->vectorStore->searchSimilar($queryVector, $limit, 0.0);
        } catch (\Exception $e) {
            Log::error('Vector search failed', [
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Perform keyword/full-text search using PostgreSQL tsvector.
     *
     * @param string $query The search query
     * @param int $limit Maximum number of results
     * @return array<int, array{node: Node, score: float}>
     */
    private function searchKeyword(string $query, int $limit): array
    {
        try {
            // Use PostgreSQL full-text search with ts_rank
            $results = DB::table('nodes')
                ->whereRaw(
                    "to_tsvector('english', content) @@ plainto_tsquery('english', ?)",
                    [$query]
                )
                ->select(
                    '*',
                    DB::raw("ts_rank(to_tsvector('english', content), plainto_tsquery('english', ?)) as rank")
                )
                ->setBindings([$query, $query], 'select')
                ->orderByDesc('rank')
                ->limit($limit)
                ->get();

            // Convert to Node models and normalize scores
            $maxRank = $results->max('rank') ?: 1.0;

            $mapped = [];
            foreach ($results as $result) {
                $node = new Node((array) $result);
                $node->id = $result->id;
                $node->exists = true;

                // Normalize rank to 0-1 scale
                $normalizedScore = min(1.0, $result->rank / $maxRank);

                $mapped[] = [
                    'node' => $node,
                    'score' => $normalizedScore,
                ];
            }

            return $mapped;
        } catch (\Exception $e) {
            Log::error('Keyword search failed', [
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Search against hypothetical questions stored in node metadata.
     *
     * @param string $query The search query
     * @param int $limit Maximum number of results
     * @return array<int, array{node: Node, score: float}>
     */
    private function searchHypotheticalQuestions(string $query, int $limit): array
    {
        try {
            // Search for nodes where any hypothetical question matches the query
            // Using ILIKE for case-insensitive partial matching
            $searchPattern = '%' . $query . '%';

            $results = Node::whereRaw(
                "EXISTS (
                    SELECT 1 FROM jsonb_array_elements_text(metadata->'hypothetical_questions') as q
                    WHERE q ILIKE ?
                )",
                [$searchPattern]
            )
                ->limit($limit * 2) // Fetch more to handle scoring
                ->get();

            $mapped = [];
            foreach ($results as $node) {
                $questions = $node->metadata['hypothetical_questions'] ?? [];

                // Calculate score based on best matching question
                $bestScore = 0.0;
                $queryLower = strtolower($query);

                foreach ($questions as $question) {
                    $questionLower = strtolower($question);

                    // Exact match gets highest score
                    if ($questionLower === $queryLower) {
                        $bestScore = 1.0;
                        break;
                    }

                    // Contains query gets good score
                    if (str_contains($questionLower, $queryLower)) {
                        $bestScore = max($bestScore, 0.8);
                    }

                    // Query contains question word gets partial score
                    similar_text($queryLower, $questionLower, $similarity);
                    $similarityScore = $similarity / 100;
                    $bestScore = max($bestScore, $similarityScore * 0.6);
                }

                if ($bestScore > 0) {
                    $mapped[] = [
                        'node' => $node,
                        'score' => $bestScore,
                    ];
                }
            }

            // Sort by score and limit
            usort($mapped, fn ($a, $b) => $b['score'] <=> $a['score']);

            return array_slice($mapped, 0, $limit);
        } catch (\Exception $e) {
            Log::error('Hypothetical questions search failed', [
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get the configured weights.
     *
     * @return array<string, float>
     */
    public function getWeights(): array
    {
        return [
            'vector' => $this->vectorWeight,
            'keyword' => $this->keywordWeight,
            'question' => $this->questionWeight,
        ];
    }

    /**
     * Update weights dynamically.
     *
     * @param float $vectorWeight Vector search weight
     * @param float $keywordWeight Keyword search weight
     * @param float $questionWeight Question search weight
     */
    public function setWeights(float $vectorWeight, float $keywordWeight, float $questionWeight): void
    {
        $this->vectorWeight = max(0.0, min(1.0, $vectorWeight));
        $this->keywordWeight = max(0.0, min(1.0, $keywordWeight));
        $this->questionWeight = max(0.0, min(1.0, $questionWeight));
    }
}
