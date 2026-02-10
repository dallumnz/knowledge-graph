<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for AI providers used by the
    | Personal Knowledge Graph. The PKG uses LMStudio for local embedding generation
    | and PostgreSQL with pgvector for vector storage and similarity search.
    |
    | pgvector Setup (Production):
    | ----------------------------
    | The vector store uses PostgreSQL's pgvector extension for efficient k-NN search.
    |
    | Installation:
    |   git clone https://github.com/pgvector/pgvector.git
    |   cd pgvector && make && sudo make install
    |
    | Enable in database:
    |   psql -d knowledge_graph -c "CREATE EXTENSION IF NOT EXISTS vector;"
    |
    | Indexing (for production performance):
    |   - HNSW (Hierarchical Navigable Small World): Best quality/speed trade-off
    |   - IVFFlat: Good for very large datasets
    |
    | Example HNSW index:
    |   CREATE INDEX ON embeddings USING hnsw (embedding vector_cosine_ops);
    |
    */

    'default' => env('AI_PROVIDER', 'lmstudio'),

    'providers' => [

        'lmstudio' => [
            'driver' => 'openai_compatible',
            'url' => env('LMSTUDIO_URL', 'http://localhost:1234'),
            'api_key' => env('LMSTUDIO_API_KEY', 'lmstudio'),
            'embedding_model' => env('LMSTUDIO_EMBEDDING_MODEL', 'text-embedding-nomic-embed-text-v2'),
            'dimensions' => (int) env('LMSTUDIO_EMBEDDING_DIMENSIONS', 768),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Embedding Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for embedding generation and storage.
    |
    | Retry Logic:
    | - max_retries: Number of attempts before giving up (default: 3)
    | - retry_delay_ms: Base delay in milliseconds for exponential backoff
    |   Actual delay = retry_delay_ms * (2 ^ (attempt - 1))
    |   Attempt 1: 1000ms, Attempt 2: 2000ms, Attempt 3: 4000ms
    |
    */

    'embeddings' => [
        'model' => env('AI_EMBEDDING_MODEL', 'text-embedding-nomic-embed-text-v2'),
        'dimensions' => (int) env('AI_EMBEDDING_DIMENSIONS', 768),
        'cache' => env('AI_EMBEDDING_CACHE', false),
        'cache_ttl' => (int) env('AI_EMBEDDING_CACHE_TTL', 3600),
        'max_retries' => (int) env('AI_EMBEDDING_MAX_RETRIES', 3),
        'retry_delay_ms' => (int) env('AI_EMBEDDING_RETRY_DELAY_MS', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Vector Store Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for vector similarity search using pgvector extension.
    |
    | Available Metrics:
    | - cosine: Cosine similarity (default, best for semantic search)
    | - l2: Euclidean distance (converted to similarity score)
    | - ip: Inner product similarity
    |
    | The pgvector extension provides efficient k-NN (k-nearest neighbor) search
    | using PostgreSQL's vector operations. Use HNSW indexes for production.
    |
    | Distance Operators:
    |   <=> Cosine distance (lower is more similar)
    |   <-> L2 distance (Euclidean)
    |   <#> Inner product
    |
    */

    'vector_store' => [
        'table' => 'embeddings',
        'embedding_column' => 'embedding',
        'dimensions' => (int) env('AI_EMBEDDING_DIMENSIONS', 768),
        'metric' => env('AI_VECTOR_METRIC', 'cosine'), // cosine, l2, ip
    ],

];
