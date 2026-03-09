<?php

namespace App\Services\Ai;

use App\Services\Ai\Validation\ValidationPipeline;
use Illuminate\Support\Facades\Log;

/**
 * RAG Query Service - End-to-end Retrieval-Augmented Generation with validation.
 *
 * This service orchestrates the complete RAG pipeline:
 * 1. Hybrid search for context retrieval
 * 2. Context assembly for LLM prompt
 * 3. Response generation
 * 4. Validation via Gatekeeper, Auditor, and Strategist nodes
 * 5. Response delivery with confidence scoring
 *
 * The service integrates with existing HybridSearchService and ReRankingService
 * while adding the validation layer for quality control.
 */
class RagQueryService
{
    /**
     * Hybrid search service for context retrieval.
     */
    private HybridSearchService $hybridSearch;

    /**
     * Re-ranking service for result refinement.
     */
    private ReRankingService $reRanker;

    /**
     * Validation pipeline for response quality control.
     */
    private ValidationPipeline $validator;

    /**
     * LLM provider for response generation.
     */
    private $llmProvider;

    /**
     * Default number of context chunks to retrieve.
     */
    private int $defaultContextChunks;

    /**
     * Maximum context length for LLM prompt.
     */
    private int $maxContextLength;

    /**
     * Temperature for response generation.
     */
    private float $temperature;

    /**
     * Maximum tokens for response generation.
     */
    private int $maxTokens;

    /**
     * Create a new RAG query service instance.
     */
    public function __construct(
        ?HybridSearchService $hybridSearch = null,
        ?ReRankingService $reRanker = null,
        ?ValidationPipeline $validator = null,
    ) {
        $this->hybridSearch = $hybridSearch ?? new HybridSearchService();
        $this->reRanker = $reRanker ?? new ReRankingService();
        $this->validator = $validator ?? new ValidationPipeline();

        // Load configuration
        $this->defaultContextChunks = config('ai.rag.context_chunks', 5);
        $this->maxContextLength = config('ai.rag.max_context_length', 4000);
        $this->temperature = config('ai.rag.temperature', 0.7);
        $this->maxTokens = config('ai.rag.max_tokens', 1024);
    }

    /**
     * Execute a RAG query with full validation pipeline.
     *
     * @param string $query The user query
     * @param array<string, mixed> $options Query options
     *   - validate: bool (default: true) - Enable validation
     *   - nodes: array - Specific validation nodes to run
     *   - context_chunks: int - Number of chunks to retrieve
     *   - rerank: bool - Enable re-ranking
     *   - alpha: float - Vector/keyword balance (0.0-1.0)
     * @return array<string, mixed> Query result with response and metadata
     */
    public function query(string $query, array $options = []): array
    {
        $startTime = microtime(true);

        try {
            // Step 1: Retrieve context via hybrid search
            $searchResults = $this->retrieveContext($query, $options);

            if (empty($searchResults)) {
                return $this->buildResult(
                    query: $query,
                    response: "I couldn't find any relevant information to answer your question.",
                    context: [],
                    validation: null,
                    timing: ['retrieval' => microtime(true) - $startTime],
                    error: null,
                );
            }

            $retrievalTime = microtime(true) - $startTime;

            // Step 2: Assemble context for LLM
            $context = $this->assembleContext($searchResults);

            // Step 3: Generate response
            $response = $this->generateResponse($query, $context);

            if ($response === null) {
                return $this->buildResult(
                    query: $query,
                    response: "I apologize, but I encountered an error while generating your response.",
                    context: $searchResults,
                    validation: null,
                    timing: [
                        'retrieval' => $retrievalTime,
                        'generation' => microtime(true) - $startTime - $retrievalTime,
                    ],
                    error: 'Response generation failed',
                );
            }

            $generationTime = microtime(true) - $startTime - $retrievalTime;

            // Step 4: Validate response (if enabled)
            $validation = null;
            if ($options['validate'] ?? true) {
                $validationStart = microtime(true);
                $validation = $this->validateResponse($query, $response, $searchResults, $options);
                $validationTime = microtime(true) - $validationStart;

                // Use validated response if different
                $response = $validation['response'] ?? $response;
            }

            $totalTime = microtime(true) - $startTime;

            return $this->buildResult(
                query: $query,
                response: $response,
                context: $searchResults,
                validation: $validation,
                timing: [
                    'retrieval_ms' => round($retrievalTime * 1000),
                    'generation_ms' => round($generationTime * 1000),
                    'validation_ms' => isset($validationTime) ? round($validationTime * 1000) : 0,
                    'total_ms' => round($totalTime * 1000),
                ],
                error: null,
            );
        } catch (\Exception $e) {
            Log::error('RAG query failed', [
                'query' => $query,
                'message' => $e->getMessage(),
            ]);

            return $this->buildResult(
                query: $query,
                response: "An error occurred while processing your query. Please try again.",
                context: [],
                validation: null,
                timing: ['total_ms' => round((microtime(true) - $startTime) * 1000)],
                error: $e->getMessage(),
            );
        }
    }

    /**
     * Retrieve relevant context via hybrid search.
     *
     * @param string $query The user query
     * @param array<string, mixed> $options Search options
     * @return array<int, array<string, mixed>> Search results
     */
    private function retrieveContext(string $query, array $options): array
    {
        $contextChunks = $options['context_chunks'] ?? $this->defaultContextChunks;
        $rerank = $options['rerank'] ?? true;
        $alpha = $options['alpha'] ?? 0.7;

        // Get more results for re-ranking
        $searchLimit = $rerank ? max(20, $contextChunks * 4) : $contextChunks;

        $results = $this->hybridSearch->search($query, $searchLimit, $alpha);

        if (empty($results)) {
            return [];
        }

        // Re-rank if enabled
        if ($rerank) {
            $results = $this->reRanker->rerank($query, $results, null, $contextChunks);
        } else {
            $results = array_slice($results, 0, $contextChunks);
        }

        return $results;
    }

    /**
     * Assemble context from search results for LLM prompt.
     *
     * @param array<int, array<string, mixed>> $results Search results
     * @return string Formatted context
     */
    private function assembleContext(array $results): string
    {
        $contextParts = [];
        $totalLength = 0;

        foreach ($results as $index => $result) {
            $node = $result['node'];
            $content = $node->content ?? '';
            $source = $result['document']['title'] ?? 'Unknown source';

            $chunkText = "[Source: {$source}]\n{$content}";

            // Check length limit
            if ($totalLength + strlen($chunkText) > $this->maxContextLength) {
                break;
            }

            $contextParts[] = $chunkText;
            $totalLength += strlen($chunkText);
        }

        return implode("\n\n---\n\n", $contextParts);
    }

    /**
     * Generate response using LLM.
     *
     * @param string $query The user query
     * @param string $context The assembled context
     * @return string|null Generated response or null on failure
     */
    private function generateResponse(string $query, string $context): ?string
    {
        try {
            $this->llmProvider = AiProviderFactory::makeLlmProvider();

            if (!$this->llmProvider->isAvailable()) {
                Log::warning('RAG: LLM provider not available');
                return null;
            }

            $prompt = $this->buildGenerationPrompt($query, $context);

            $response = $this->llmProvider->generate($prompt, [
                'temperature' => $this->temperature,
                'max_tokens' => $this->maxTokens,
            ]);

            return $response;
        } catch (\Exception $e) {
            Log::error('RAG response generation failed', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Build the prompt for response generation.
     *
     * @param string $query The user query
     * @param string $context The assembled context
     * @return string The formatted prompt
     */
    private function buildGenerationPrompt(string $query, string $context): string
    {
        return <<<PROMPT
You are a helpful assistant answering questions based on the provided context. Answer the question using only the information in the context below. If the context doesn't contain enough information to answer the question fully, say so.

Context:
---
{$context}
---

Question: {$query}

Answer:
PROMPT;
    }

    /**
     * Validate the generated response.
     *
     * @param string $query The original query
     * @param string $response The generated response
     * @param array<int, array<string, mixed>> $searchResults The search results
     * @param array<string, mixed> $options Validation options
     * @return array<string, mixed> Validation results
     */
    private function validateResponse(string $query, string $response, array $searchResults, array $options): array
    {
        // Configure validation nodes if specified
        if (isset($options['nodes'])) {
            $this->validator->setConfig(['nodes' => $options['nodes']]);
        }

        // Prepare context for validation
        $validationContext = [
            'chunks' => $searchResults,
            'documents' => $this->extractDocumentMetadata($searchResults),
        ];

        return $this->validator->validate($query, $response, $validationContext);
    }

    /**
     * Extract document metadata from search results.
     *
     * @param array<int, array<string, mixed>> $results Search results
     * @return array<int, array<string, mixed>> Document metadata
     */
    private function extractDocumentMetadata(array $results): array
    {
        $documents = [];

        foreach ($results as $result) {
            if (isset($result['document'])) {
                $documents[] = $result['document'];
            }
        }

        return $documents;
    }

    /**
     * Build the final result structure.
     *
     * @param string $query The original query
     * @param string $response The final response
     * @param array<int, array<string, mixed>> $context Source context
     * @param array<string, mixed>|null $validation Validation results
     * @param array<string, float> $timing Timing information
     * @param string|null $error Error message if any
     * @return array<string, mixed>
     */
    private function buildResult(
        string $query,
        string $response,
        array $context,
        ?array $validation,
        array $timing,
        ?string $error,
    ): array {
        $confidenceScore = $validation['confidence_score'] ?? 0.5;

        return [
            'success' => $error === null,
            'query' => $query,
            'response' => $response,
            'confidence_score' => $confidenceScore,
            'sources' => array_map(fn ($r) => [
                'id' => $r['node']->id ?? null,
                'content' => $r['node']->content ?? '',
                'document' => $r['document'] ?? null,
                'relevance_score' => $r['relevance_score'] ?? $r['score'] ?? 0,
            ], $context),
            'validation' => $validation ? [
                'pass' => $validation['pass'],
                'confidence_score' => $validation['confidence_score'],
                'issues' => $validation['issues'] ?? [],
                'recommendations' => $validation['recommendations'] ?? [],
                'node_results' => $validation['results'] ?? [],
            ] : null,
            'timing' => $timing,
            'error' => $error,
        ];
    }

    /**
     * Quick query without validation (for internal use).
     *
     * @param string $query The user query
     * @param int $contextChunks Number of context chunks
     * @return string|null Response or null on failure
     */
    public function quickQuery(string $query, int $contextChunks = 3): ?string
    {
        $result = $this->query($query, [
            'validate' => false,
            'context_chunks' => $contextChunks,
            'rerank' => false,
        ]);

        return $result['success'] ? $result['response'] : null;
    }

    /**
     * Get service configuration.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return [
            'context_chunks' => $this->defaultContextChunks,
            'max_context_length' => $this->maxContextLength,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
            'validation' => $this->validator->getConfig(),
        ];
    }
}
