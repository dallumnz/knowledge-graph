<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for AI providers used by the
    | Personal Knowledge Graph. The PKG uses local-ai or LMStudio for local
    | embedding generation and PostgreSQL with pgvector for vector storage
    | and similarity search.
    |
    | Supported Providers:
    | - local-ai: Default, OpenAI-compatible API at localhost:8080
    | - lmstudio: Legacy support at localhost:1234
    | - openai: Future external provider support
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

    /*
    |--------------------------------------------------------------------------
    | Default Providers
    |--------------------------------------------------------------------------
    |
    | These providers will be used by default when no specific provider is
    | requested. You can override per-call by passing a provider name.
    |
    */

    'default_embedding_provider' => env('AI_EMBEDDING_PROVIDER', 'local-ai'),
    'default_llm_provider' => env('AI_LLM_PROVIDER', 'local-ai'),

    /*
    |--------------------------------------------------------------------------
    | Provider Configurations
    |--------------------------------------------------------------------------
    |
    | Configuration for each supported AI provider. Each provider must have
    | a driver that implements the appropriate interface.
    |
    */

    'providers' => [

        'local-ai' => [
            'driver' => 'local-ai',
            'url' => env('LOCALAI_URL', 'http://localhost:8080'),
            'api_key' => env('LOCALAI_API_KEY', ''),
            'embedding_model' => env('LOCALAI_EMBEDDING_MODEL', 'nomic-embed-text-v1.5'),
            'embedding_dimensions' => (int) env('LOCALAI_EMBEDDING_DIMENSIONS', 768),
            'llm_model' => env('LOCALAI_LLM_MODEL', 'Qwen3.5-9B-GGUF'),
            'timeout' => (int) env('LOCALAI_TIMEOUT', 60),
            'temperature' => (float) env('LOCALAI_TEMPERATURE', 0.7),
            'max_tokens' => (int) env('LOCALAI_MAX_TOKENS', 1024),
        ],

        'lmstudio' => [
            'driver' => 'openai_compatible',
            'url' => env('LMSTUDIO_URL', 'http://localhost:1234'),
            'api_key' => env('LMSTUDIO_API_KEY', 'lmstudio'),
            'embedding_model' => env('LMSTUDIO_EMBEDDING_MODEL', 'text-embedding-nomic-embed-text-v2'),
            'embedding_dimensions' => (int) env('LMSTUDIO_EMBEDDING_DIMENSIONS', 768),
            'timeout' => (int) env('LMSTUDIO_TIMEOUT', 60),
        ],

        'openai' => [
            'driver' => 'openai',
            'api_key' => env('OPENAI_API_KEY'),
            'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
            'embedding_dimensions' => (int) env('OPENAI_EMBEDDING_DIMENSIONS', 1536),
            'llm_model' => env('OPENAI_LLM_MODEL', 'gpt-4o-mini'),
            'timeout' => (int) env('OPENAI_TIMEOUT', 30),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Retry logic for AI provider requests.
    |
    | - max_attempts: Number of attempts before giving up (default: 3)
    | - base_delay_ms: Base delay in milliseconds for exponential backoff
    |   Actual delay = base_delay_ms * (2 ^ (attempt - 1))
    |   Attempt 1: 1000ms, Attempt 2: 2000ms, Attempt 3: 4000ms
    |
    */

    'retry' => [
        'max_attempts' => (int) env('AI_RETRY_MAX_ATTEMPTS', 3),
        'base_delay_ms' => (int) env('AI_RETRY_BASE_DELAY_MS', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Embedding Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for embedding generation and storage.
    |
    | Note: These settings apply to the active provider. Model and dimensions
    | are now configured per-provider above.
    |
    */

    'embeddings' => [
        'cache' => env('AI_EMBEDDING_CACHE', false),
        'cache_ttl' => (int) env('AI_EMBEDDING_CACHE_TTL', 86400), // 24 hours
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
        'cache_enabled' => env('AI_VECTOR_CACHE_ENABLED', false),
        'cache_ttl' => (int) env('AI_VECTOR_CACHE_TTL', 600), // 10 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | LLM Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for LLM (Large Language Model) features like hypothetical
    | question generation and summarization.
    |
    */

    'llm' => [
        'cache' => env('AI_LLM_CACHE', false),
        'cache_ttl' => (int) env('AI_LLM_CACHE_TTL', 86400), // 24 hours
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Toggle features on/off without code changes.
    |
    */

    'features' => [
        'hypothetical_questions' => env('AI_ENABLE_HYPOTHETICAL_QUESTIONS', true),
        'questions_per_chunk' => (int) env('AI_QUESTIONS_PER_CHUNK', 4),
        'embedding_cache' => env('AI_EMBEDDING_CACHE', false),
        'llm_cache' => env('AI_LLM_CACHE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Document Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for document storage and source attribution.
    |
    | Source Types:
    | - file: Uploaded files (PDF, DOC, TXT, etc.)
    | - url: Web pages or external URLs
    | - text: Raw text input
    | - api: External API sources
    |
    | Chunking:
    | - default_chunk_size: Default size for text chunks (in characters)
    | - max_chunk_size: Maximum allowed chunk size
    |
    */

    'documents' => [
        'default_chunk_size' => (int) env('AI_DOCUMENT_CHUNK_SIZE', 500),
        'max_chunk_size' => (int) env('AI_DOCUMENT_MAX_CHUNK_SIZE', 10000),
        'store_full_content' => env('AI_DOCUMENT_STORE_FULL', true),
        'source_types' => ['file', 'url', 'text', 'api'],
    ],

];
