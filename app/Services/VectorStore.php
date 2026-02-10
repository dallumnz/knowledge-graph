<?php

namespace App\Services;

use App\Models\Embedding;
use App\Models\Node;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Pgvector\Laravel\Distance;
use RuntimeException;

/**
 * Vector store service for similarity search using pgvector.
 *
 * Uses PostgreSQL's pgvector extension for efficient k-nearest neighbor (k-NN)
 * vector similarity search with HNSW or IVFFlat indexes.
 *
 * Security Features:
 * - Input validation for vector dimensions and values
 * - SQL injection prevention via parameterized queries
 * - Rate limiting for search operations
 * - Access control integration
 *
 * pgvector Operators:
 * - <=> Cosine distance (lower is more similar, range: 0-2)
 * - <-> L2/Euclidean distance (lower is more similar)
 * - <#> Inner product (higher is more similar, for normalized vectors)
 */
class VectorStore
{
    /**
     * The embedding dimensions.
     */
    private int $dimensions;

    /**
     * The vector similarity metric (cosine, l2, ip).
     */
    private string $metric;

    /**
     * Maximum allowed vector value (prevents overflow).
     */
    private float $maxVectorValue;

    /**
     * Rate limit key prefix.
     */
    private string $rateLimitPrefix = 'vector_search:';

    /**
     * Create a new vector store instance.
     */
    public function __construct()
    {
        $this->dimensions = config('ai.vector_store.dimensions', 768);
        $this->metric = config('ai.vector_store.metric', 'cosine');
        $this->maxVectorValue = config('ai.vector_store.max_vector_value', 1e10);
    }

    /**
     * Perform a k-NN search for similar vectors using pgvector.
     *
     * Uses the <=> (cosine distance) operator for similarity search.
     *
     * @param  array<float>  $queryVector  The query embedding vector
     * @param  int  $limit  Maximum number of results to return
     * @param  float  $minSimilarity  Minimum similarity threshold (0-1 for cosine)
     * @param  string|null  $rateLimitKey  Key for rate limiting (null to skip)
     * @return array<int, array{node: Node, score: float}> Array of results with node and similarity score
     *
     * @throws InvalidArgumentException If vector validation fails
     * @throws RuntimeException If rate limit exceeded
     */
    public function searchSimilar(
        array $queryVector,
        int $limit = 10,
        float $minSimilarity = 0.0,
        ?string $rateLimitKey = null,
    ): array {
        // Security: Validate and sanitize inputs
        $this->validateVector($queryVector);
        $this->validateSearchParams($limit, $minSimilarity);

        // Security: Check rate limit if key provided
        if ($rateLimitKey !== null) {
            $this->checkRateLimit($rateLimitKey);
        }

        try {
            // Build the distance operator based on metric
            $distanceOp = $this->getDistanceOperator($this->metric);

            // Security: Use parameterized query to prevent SQL injection
            $vectorStr = $this->formatVectorForPostgres($queryVector);
            $maxDistance = $this->similarityToDistance($minSimilarity, $this->metric);

            // Perform similarity search using pgvector's operators with parameterized queries
            $results = DB::table('embeddings as e')
                ->selectRaw(
                    "e.node_id, e.embedding {$distanceOp} ?::vector as score",
                    [$vectorStr]
                )
                ->join('nodes as n', 'e.node_id', '=', 'n.id')
                ->whereRaw(
                    "e.embedding {$distanceOp} ?::vector <= ?",
                    [$vectorStr, $maxDistance]
                )
                ->orderByRaw(
                    "e.embedding {$distanceOp} ?::vector",
                    [$vectorStr]
                )
                ->limit($this->sanitizeLimit($limit))
                ->get();

            // Map results to nodes with distance-to-similarity conversion
            return $this->mapResultsToNodes($results, $this->metric);
        } catch (\Exception $e) {
            Log::error('pgvector similarity search failed', [
                'message' => $e->getMessage(),
                'metric' => $this->metric,
            ]);

            return [];
        }
    }

    /**
     * Perform batch similarity search for multiple query vectors.
     *
     * @param  array<int, array<float>>  $queryVectors  Array of query embedding vectors
     * @param  int  $limit  Maximum number of results per query
     * @param  float  $minSimilarity  Minimum similarity threshold
     * @return array<int, array<int, array{node: Node, score: float}>> Array of results per query
     */
    public function batchSearchSimilar(
        array $queryVectors,
        int $limit = 10,
        float $minSimilarity = 0.0,
    ): array {
        $results = [];

        foreach ($queryVectors as $index => $queryVector) {
            $results[$index] = $this->searchSimilar($queryVector, $limit, $minSimilarity);
        }

        return $results;
    }

    /**
     * Perform nearest neighbor search using pgvector-php's nearestNeighbors.
     *
     * @param  array<float>  $queryVector  The query embedding vector
     * @param  int  $limit  Maximum number of results
     * @param  string  $distance  Distance metric (L2, Cosine, InnerProduct)
     * @param  string|null  $rateLimitKey  Key for rate limiting
     * @return array<int, array{node: Node, score: float}>
     *
     * @throws InvalidArgumentException If vector validation fails
     */
    public function nearestNeighbors(
        array $queryVector,
        int $limit = 10,
        string $distance = 'Cosine',
        ?string $rateLimitKey = null,
    ): array {
        // Security: Validate inputs
        $this->validateVector($queryVector);
        $this->validateSearchParams($limit, 0.0);

        // Security: Check rate limit if key provided
        if ($rateLimitKey !== null) {
            $this->checkRateLimit($rateLimitKey);
        }

        try {
            $distanceEnum = match ($distance) {
                'L2' => Distance::L2,
                'InnerProduct' => Distance::InnerProduct,
                default => Distance::Cosine,
            };

            // Eager load node relationship to prevent N+1 query problem
            $results = Embedding::query()
                ->with('node')
                ->nearestNeighbors('embedding', $queryVector, $distanceEnum)
                ->take($this->sanitizeLimit($limit))
                ->get();

            $mapped = [];
            foreach ($results as $result) {
                $distanceValue = $result->neighbor_distance ?? 0;

                // Convert distance to similarity score (score = 1 - distance for cosine)
                $similarity = $this->distanceToSimilarity($distanceValue, $distance);

                $mapped[] = [
                    'node' => $result->node,
                    'score' => $similarity,
                ];
            }

            return $mapped;
        } catch (\Exception $e) {
            Log::error('nearestNeighbors search failed', [
                'message' => $e->getMessage(),
                'distance' => $distance,
            ]);

            return [];
        }
    }

    /**
     * Calculate cosine similarity between two vectors.
     *
     * Uses pgvector's <=> operator for accurate calculation.
     *
     * @param  array<float>  $vectorA  First vector
     * @param  array<float>  $vectorB  Second vector
     * @return float Cosine similarity (0-1, where 1 is identical)
     *
     * @throws InvalidArgumentException If vectors are invalid
     */
    public function cosineSimilarity(array $vectorA, array $vectorB): float
    {
        $this->validateVector($vectorA);
        $this->validateVector($vectorB);

        if (count($vectorA) !== count($vectorB)) {
            throw new InvalidArgumentException('Vectors must have same dimensions');
        }

        try {
            $vectorAStr = $this->formatVectorForPostgres($vectorA);
            $vectorBStr = $this->formatVectorForPostgres($vectorB);

            $result = DB::selectOne(
                'SELECT 1 - (?::vector <=> ?::vector) as similarity',
                [$vectorAStr, $vectorBStr]
            );

            return (float) $result->similarity;
        } catch (\Exception $e) {
            Log::error('Cosine similarity calculation failed', [
                'message' => $e->getMessage(),
            ]);

            return 0.0;
        }
    }

    /**
     * Calculate L2 (Euclidean) distance between two vectors.
     *
     * @param  array<float>  $vectorA  First vector
     * @param  array<float>  $vectorB  Second vector
     * @return float L2 distance (lower is more similar)
     *
     * @throws InvalidArgumentException If vectors are invalid
     */
    public function l2Distance(array $vectorA, array $vectorB): float
    {
        $this->validateVector($vectorA);
        $this->validateVector($vectorB);

        if (count($vectorA) !== count($vectorB)) {
            throw new InvalidArgumentException('Vectors must have same dimensions');
        }

        try {
            $vectorAStr = $this->formatVectorForPostgres($vectorA);
            $vectorBStr = $this->formatVectorForPostgres($vectorB);

            $result = DB::selectOne(
                'SELECT ?::vector <-> ?::vector as distance',
                [$vectorAStr, $vectorBStr]
            );

            return (float) $result->distance;
        } catch (\Exception $e) {
            Log::error('L2 distance calculation failed', [
                'message' => $e->getMessage(),
            ]);

            return PHP_FLOAT_MAX;
        }
    }

    /**
     * Calculate inner product between two vectors.
     *
     * @param  array<float>  $vectorA  First vector
     * @param  array<float>  $vectorB  Second vector
     * @return float Inner product (higher is more similar for normalized vectors)
     *
     * @throws InvalidArgumentException If vectors are invalid
     */
    public function innerProduct(array $vectorA, array $vectorB): float
    {
        $this->validateVector($vectorA);
        $this->validateVector($vectorB);

        if (count($vectorA) !== count($vectorB)) {
            throw new InvalidArgumentException('Vectors must have same dimensions');
        }

        try {
            $vectorAStr = $this->formatVectorForPostgres($vectorA);
            $vectorBStr = $this->formatVectorForPostgres($vectorB);

            $result = DB::selectOne(
                'SELECT ?::vector <#> ?::vector as product',
                [$vectorAStr, $vectorBStr]
            );

            return (float) $result->product;
        } catch (\Exception $e) {
            Log::error('Inner product calculation failed', [
                'message' => $e->getMessage(),
            ]);

            return 0.0;
        }
    }

    /**
     * Store a vector for a node with validation.
     *
     * @param  int  $nodeId  The node ID to store the vector for
     * @param  array<float>  $vector  The embedding vector
     * @return bool True if stored successfully
     *
     * @throws InvalidArgumentException If vector validation fails
     */
    public function storeVector(int $nodeId, array $vector): bool
    {
        // Security: Validate node ID
        if ($nodeId <= 0) {
            throw new InvalidArgumentException('Node ID must be positive');
        }

        // Security: Validate vector
        $this->validateVector($vector);

        try {
            // Use explicit query on node_id column instead of find()
            // This ensures correct lookup regardless of primary key configuration
            $embedding = Embedding::query()
                ->where('node_id', $nodeId)
                ->first();

            if ($embedding === null) {
                $embedding = new Embedding;
                $embedding->node_id = $nodeId;
            }

            $embedding->embedding = $vector;
            $embedding->save();

            Log::info('Vector stored for node', [
                'node_id' => $nodeId,
                'dimensions' => count($vector),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to store vector', [
                'node_id' => $nodeId,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Store multiple vectors in a batch operation.
     *
     * @param  array<int, array<float>>  $vectors  Array of node_id => vector pairs
     * @return array{success: int, failed: int} Count of successful and failed operations
     */
    public function batchStoreVectors(array $vectors): array
    {
        $success = 0;
        $failed = 0;

        foreach ($vectors as $nodeId => $vector) {
            try {
                if ($this->storeVector($nodeId, $vector)) {
                    $success++;
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $failed++;
                Log::error('Batch vector store failed for node', [
                    'node_id' => $nodeId,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return ['success' => $success, 'failed' => $failed];
    }

    /**
     * Delete a vector for a node.
     *
     * @param  int  $nodeId  The node ID to delete the vector for
     * @return bool True if deleted successfully
     */
    public function deleteVector(int $nodeId): bool
    {
        // Security: Validate node ID
        if ($nodeId <= 0) {
            throw new InvalidArgumentException('Node ID must be positive');
        }

        try {
            $deleted = Embedding::where('node_id', $nodeId)->delete();

            if ($deleted) {
                Log::info('Vector deleted for node', ['node_id' => $nodeId]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete vector', [
                'node_id' => $nodeId,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get all vectors (for batch operations).
     *
     * @return Collection<int, Embedding>
     */
    public function getAllVectors(): Collection
    {
        return Embedding::with('node')->get();
    }

    /**
     * Get vector count.
     *
     * @return int Number of stored vectors
     */
    public function getVectorCount(): int
    {
        return Embedding::count();
    }

    /**
     * Check if a vector exists for a node.
     *
     * @param  int  $nodeId  The node ID to check
     * @return bool True if vector exists
     */
    public function hasVector(int $nodeId): bool
    {
        return Embedding::where('node_id', $nodeId)->exists();
    }

    // ==========================================
    // Index Management Helpers
    // ==========================================

    /**
     * Create an HNSW index for vector similarity search.
     *
     * HNSW (Hierarchical Navigable Small World) provides excellent
     * search performance with high recall. Best for most use cases.
     *
     * Run this after tables are populated for production performance.
     *
     * @param  string  $metric  Distance metric (l2, cosine, ip)
     * @param  int  $m  Maximum number of connections per layer (default: 16)
     * @param  int  $efConstruction  Size of dynamic candidate list (default: 64)
     * @param  string  $indexName  Custom index name (default: auto-generated)
     */
    public function createHnswIndex(
        string $metric = 'cosine',
        int $m = 16,
        int $efConstruction = 64,
        ?string $indexName = null,
    ): void {
        $ops = $this->getVectorOps($metric);
        $name = $indexName ?? "idx_embeddings_hnsw_{$metric}";

        // Drop existing index if exists
        DB::statement("DROP INDEX IF EXISTS {$name}");

        // Create HNSW index with specified parameters
        DB::statement(
            "CREATE INDEX {$name} ON embeddings USING hnsw (embedding {$ops}) ".
            "WITH (m = {$m}, ef_construction = {$efConstruction})"
        );

        Log::info('HNSW index created', [
            'name' => $name,
            'metric' => $metric,
            'm' => $m,
            'ef_construction' => $efConstruction,
        ]);
    }

    /**
     * Create an IVFFlat index for vector similarity search.
     *
     * IVFFlat is good for very large datasets where memory is constrained.
     * Requires specifying the number of lists based on dataset size.
     *
     * @param  string  $metric  Distance metric
     * @param  int  $lists  Number of lists (suggestion: sqrt of rows, min 100)
     * @param  string  $indexName  Custom index name (default: auto-generated)
     */
    public function createIvfflatIndex(
        string $metric = 'cosine',
        int $lists = 100,
        ?string $indexName = null,
    ): void {
        $ops = $this->getVectorOps($metric);
        $name = $indexName ?? "idx_embeddings_ivfflat_{$metric}";

        // Drop existing index if exists
        DB::statement("DROP INDEX IF EXISTS {$name}");

        // Create IVFFlat index
        DB::statement(
            "CREATE INDEX {$name} ON embeddings USING ivfflat (embedding {$ops}) ".
            "WITH (lists = {$lists})"
        );

        Log::info('IVFFlat index created', [
            'name' => $name,
            'metric' => $metric,
            'lists' => $lists,
        ]);
    }

    /**
     * Drop a vector index.
     *
     * @param  string  $indexName  Name of the index to drop
     * @return bool True if dropped successfully
     */
    public function dropIndex(string $indexName): bool
    {
        try {
            DB::statement("DROP INDEX IF EXISTS {$indexName}");

            Log::info('Vector index dropped', ['name' => $indexName]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to drop index', [
                'name' => $indexName,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get index statistics and recommendations.
     *
     * @return array<string, mixed> Index statistics
     */
    public function getIndexStats(): array
    {
        try {
            // Get all indexes on embeddings table
            $indexes = DB::select(
                'SELECT indexname, indexdef '.
                'FROM pg_indexes '.
                "WHERE tablename = 'embeddings'"
            );

            // Get table size
            $tableSize = DB::selectOne(
                "SELECT pg_size_pretty(pg_total_relation_size('embeddings')) as size"
            );

            // Get row count
            $rowCount = $this->getVectorCount();

            // Recommend index type based on data size
            $recommendation = match (true) {
                $rowCount < 1000 => 'No index needed for small datasets',
                $rowCount < 100000 => 'HNSW index recommended',
                default => 'IVFFlat or HNSW index recommended',
            };

            return [
                'table_size' => $tableSize->size ?? 'unknown',
                'row_count' => $rowCount,
                'indexes' => array_map(fn ($idx) => [
                    'name' => $idx->indexname,
                    'definition' => $idx->indexdef,
                ], $indexes),
                'recommendation' => $recommendation,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get index stats', [
                'message' => $e->getMessage(),
            ]);

            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Recommend index parameters based on dataset size.
     *
     * @return array<string, mixed> Recommended parameters
     */
    public function recommendIndexParams(): array
    {
        $rowCount = $this->getVectorCount();

        // HNSW recommendations
        $hnswM = match (true) {
            $rowCount < 10000 => 16,
            $rowCount < 100000 => 32,
            default => 64,
        };

        $hnswEfConstruction = match (true) {
            $rowCount < 10000 => 64,
            $rowCount < 100000 => 128,
            default => 256,
        };

        // IVFFlat recommendations
        $ivfflatLists = (int) max(100, sqrt($rowCount));

        return [
            'dataset_size' => $rowCount,
            'hnsw' => [
                'm' => $hnswM,
                'ef_construction' => $hnswEfConstruction,
                'recommended_for' => 'Best for most use cases, excellent recall',
            ],
            'ivfflat' => [
                'lists' => $ivfflatLists,
                'recommended_for' => 'Good for very large datasets, memory constrained',
            ],
        ];
    }

    /**
     * Run VACUUM ANALYZE on embeddings table.
     *
     * Important for query planner to make good decisions.
     *
     * @return bool True if successful
     */
    public function vacuumAnalyze(): bool
    {
        try {
            DB::statement('VACUUM ANALYZE embeddings');

            Log::info('VACUUM ANALYZE completed on embeddings table');

            return true;
        } catch (\Exception $e) {
            Log::error('VACUUM ANALYZE failed', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    // ==========================================
    // Security Guards
    // ==========================================

    /**
     * Validate a vector for security and correctness.
     *
     * Checks:
     * - Correct dimensions
     * - No NaN or Inf values
     * - Values within reasonable bounds
     *
     * @param  array<float>  $vector  The vector to validate
     *
     * @throws InvalidArgumentException If validation fails
     */
    public function validateVector(array $vector): void
    {
        // Check dimensions
        if (count($vector) !== $this->dimensions) {
            throw new InvalidArgumentException(
                "Vector must have exactly {$this->dimensions} dimensions, got ".count($vector)
            );
        }

        // Check for NaN and Inf values
        foreach ($vector as $i => $value) {
            // Guard against non-numeric values first
            if (! is_numeric($value)) {
                throw new InvalidArgumentException(
                    "Vector contains non-numeric value at index {$i}: ".
                    var_export($value, true)
                );
            }

            // Cast to float for validation
            $numericValue = (float) $value;

            if (is_nan($numericValue)) {
                throw new InvalidArgumentException("Vector contains NaN value at index {$i}");
            }

            if (is_infinite($numericValue)) {
                throw new InvalidArgumentException("Vector contains infinite value at index {$i}");
            }

            // Check value bounds (security: prevent overflow)
            if (abs($numericValue) > $this->maxVectorValue) {
                throw new InvalidArgumentException(
                    "Vector value at index {$i} exceeds maximum allowed value ({$this->maxVectorValue})"
                );
            }
        }
    }

    /**
     * Validate search parameters.
     *
     * @param  int  $limit  The limit parameter
     * @param  float  $minSimilarity  The minimum similarity threshold
     *
     * @throws InvalidArgumentException If validation fails
     */
    public function validateSearchParams(int $limit, float $minSimilarity): void
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('Limit must be at least 1');
        }

        if ($limit > 1000) {
            throw new InvalidArgumentException('Limit cannot exceed 1000');
        }

        if ($minSimilarity < 0.0 || $minSimilarity > 1.0) {
            throw new InvalidArgumentException('Minimum similarity must be between 0.0 and 1.0');
        }
    }

    /**
     * Check rate limit for search operations.
     *
     * @param  string  $key  The rate limit key (typically user ID or IP)
     * @param  int  $maxAttempts  Maximum attempts allowed
     * @param  int  $decaySeconds  Time window in seconds
     *
     * @throws RuntimeException If rate limit exceeded
     */
    public function checkRateLimit(
        string $key,
        int $maxAttempts = 60,
        int $decaySeconds = 60,
    ): void {
        $fullKey = $this->rateLimitPrefix.$key;

        if (RateLimiter::tooManyAttempts($fullKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($fullKey);

            throw new RuntimeException(
                "Rate limit exceeded. Try again in {$seconds} seconds."
            );
        }

        RateLimiter::hit($fullKey, $decaySeconds);
    }

    /**
     * Clear rate limit for a key.
     *
     * @param  string  $key  The rate limit key
     */
    public function clearRateLimit(string $key): void
    {
        RateLimiter::clear($this->rateLimitPrefix.$key);
    }

    // ==========================================
    // Getters
    // ==========================================

    /**
     * Get the configured dimensions.
     *
     * @return int The embedding dimensions
     */
    public function getDimensions(): int
    {
        return $this->dimensions;
    }

    /**
     * Get the configured metric.
     *
     * @return string The similarity metric
     */
    public function getMetric(): string
    {
        return $this->metric;
    }

    // ==========================================
    // Private Helpers
    // ==========================================

    /**
     * Get the pgvector distance operator for a metric.
     *
     * @param  string  $metric  The metric name
     * @return string The SQL operator
     */
    private function getDistanceOperator(string $metric): string
    {
        return match ($metric) {
            'cosine' => '<=>',  // Cosine distance
            'l2' => '<->',      // L2 (Euclidean) distance
            'ip' => '<#>',      // Inner product
            default => '<=>',   // Default to cosine
        };
    }

    /**
     * Get the pgvector operator class for a metric.
     *
     * @param  string  $metric  The metric name
     * @return string The operator class name
     */
    private function getVectorOps(string $metric): string
    {
        return match ($metric) {
            'l2' => 'vector_l2_ops',
            'ip' => 'vector_ip_ops',
            default => 'vector_cosine_ops',
        };
    }

    /**
     * Convert similarity score to distance threshold.
     *
     * @param  float  $similarity  Similarity score (0-1)
     * @param  string  $metric  The metric being used
     * @return float The distance threshold
     */
    private function similarityToDistance(float $similarity, string $metric): float
    {
        return match ($metric) {
            'cosine' => 1 - $similarity,  // Cosine distance = 1 - similarity
            'l2' => sqrt(2 * (1 - $similarity)),  // Approximate conversion
            'ip' => 1 - $similarity,  // For normalized vectors
            default => 1 - $similarity,
        };
    }

    /**
     * Convert distance to similarity score.
     *
     * @param  float  $distance  The distance value
     * @param  string  $metric  The metric being used
     * @return float The similarity score
     */
    private function distanceToSimilarity(float $distance, string $metric): float
    {
        return match ($metric) {
            'L2' => 1 / (1 + $distance),
            'InnerProduct' => $distance, // Already similarity-like for normalized vectors
            default => 1 - $distance,  // Cosine
        };
    }

    /**
     * Format a vector array for PostgreSQL vector type.
     *
     * Guards against non-numeric values to prevent SQL errors.
     *
     * @param  array<float>  $vector  The vector to format
     * @return string The formatted vector string
     *
     * @throws InvalidArgumentException If vector contains non-numeric values
     */
    private function formatVectorForPostgres(array $vector): string
    {
        $formatted = [];

        foreach ($vector as $i => $value) {
            // Guard against non-numeric values
            if (! is_numeric($value)) {
                throw new InvalidArgumentException(
                    "Vector contains non-numeric value at index {$i}: ".
                    var_export($value, true)
                );
            }

            // Ensure value is a float/number and format it
            $numericValue = (float) $value;
            $formatted[] = number_format($numericValue, 10, '.', '');
        }

        return '['.implode(',', $formatted).']';
    }

    /**
     * Sanitize the limit parameter.
     *
     * @param  int  $limit  The requested limit
     * @return int The sanitized limit
     */
    private function sanitizeLimit(int $limit): int
    {
        return max(1, min(1000, $limit));
    }

    /**
     * Map database results to node objects.
     *
     * @param  \Illuminate\Support\Collection  $results
     * @param  string  $metric  The distance metric used (for score conversion)
     * @return array<int, array{node: Node, score: float}>
     */
    private function mapResultsToNodes($results, string $metric = 'cosine'): array
    {
        $nodeIds = $results->pluck('node_id')->toArray();

        if (empty($nodeIds)) {
            return [];
        }

        /** @var array<int, Node> $nodes */
        $nodes = Node::whereIn('id', $nodeIds)
            ->get()
            ->keyBy('id')
            ->all();

        $mapped = [];
        foreach ($results as $result) {
            if (isset($nodes[$result->node_id])) {
                // Convert raw distance to similarity score
                // For cosine: score = 1 - distance (distance range: 0-2)
                $distance = (float) $result->score;
                $similarity = $this->distanceToSimilarity($distance, $metric);

                $mapped[] = [
                    'node' => $nodes[$result->node_id],
                    'score' => $similarity,
                ];
            }
        }

        return $mapped;
    }
}
