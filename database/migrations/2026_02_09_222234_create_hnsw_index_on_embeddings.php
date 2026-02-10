<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Create HNSW index for efficient vector similarity search.
 *
 * HNSW (Hierarchical Navigable Small World) provides excellent
 * search performance with high recall for pgvector.
 *
 * Parameters:
 * - m = 16: Maximum number of connections per layer (higher = better recall, more memory)
 * - ef_construction = 64: Size of dynamic candidate list during construction
 *
 * Note: Run this after the embeddings table has data for optimal performance.
 * For datasets < 1000 rows, index may not be necessary.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if embeddings table exists and has data
        if (! Schema::hasTable('embeddings')) {
            return;
        }

        $rowCount = DB::table('embeddings')->count();

        // Only create index if we have enough data to benefit from it
        // HNSW is beneficial for datasets > 1000 rows
        if ($rowCount < 1000) {
            // For small datasets, create index with conservative parameters
            $this->createHnswIndex(16, 64);
        } else {
            // For larger datasets, use more aggressive parameters
            $m = $rowCount < 10000 ? 16 : ($rowCount < 100000 ? 32 : 64);
            $efConstruction = $rowCount < 10000 ? 64 : ($rowCount < 100000 ? 128 : 256);
            $this->createHnswIndex($m, $efConstruction);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop HNSW indexes if they exist
        $indexes = [
            'idx_embeddings_hnsw_cosine',
            'idx_embeddings_hnsw_l2',
            'idx_embeddings_hnsw_ip',
        ];

        foreach ($indexes as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }
    }

    /**
     * Create HNSW index with specified parameters.
     */
    private function createHnswIndex(int $m, int $efConstruction): void
    {
        // Drop existing index if it exists
        DB::statement('DROP INDEX IF EXISTS idx_embeddings_hnsw_cosine');

        // Create HNSW index for cosine distance (most common for embeddings)
        // Uses vector_cosine_ops operator class
        DB::statement(
            "CREATE INDEX idx_embeddings_hnsw_cosine ON embeddings 
             USING hnsw (embedding vector_cosine_ops) 
             WITH (m = {$m}, ef_construction = {$efConstruction})"
        );
    }
};
