# Personal Knowledge Graph (PKG)

A Laravel-based Personal Knowledge Graph system with vector embeddings and semantic search capabilities. Store your notes, documents, and ideas as interconnected nodes with AI-powered similarity search.

## Features

- **Knowledge Graph Storage**: Store content as nodes with typed relationships (edges)
- **Vector Embeddings**: Generate 768-dimensional embeddings using local AI (LMStudio)
- **Semantic Search**: Find similar content using vector similarity (cosine, euclidean, dot)
- **Text Chunking**: Automatically split long documents into manageable chunks
- **RESTful API**: Full API with authentication via Laravel Sanctum
- **Rate Limiting**: Protected with configurable throttling (60 req/min default)

## Requirements

- PHP 8.2+
- SQLite 3.35+ (with optional vec0 extension for vector search)
- LMStudio or compatible OpenAI-compatible embedding API
- Composer
- Node.js & NPM (for frontend assets)

## Installation

### 1. Clone and Install Dependencies

```bash
git clone <repository-url>
cd knowledge-graph
composer install
npm install
```

### 2. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

Configure your `.env`:

```env
# Database (SQLite default)
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database.sqlite

# LMStudio Configuration
LMSTUDIO_URL=http://localhost:1234
LMSTUDIO_API_KEY=lmstudio
LMSTUDIO_EMBEDDING_MODEL=text-embedding-nomic-embed-text-v2
LMSTUDIO_EMBEDDING_DIMENSIONS=768

# AI Configuration
AI_PROVIDER=lmstudio
AI_EMBEDDING_MODEL=text-embedding-nomic-embed-text-v2
AI_EMBEDDING_DIMENSIONS=768
AI_EMBEDDING_MAX_RETRIES=3
AI_EMBEDDING_RETRY_DELAY_MS=1000
AI_VECTOR_METRIC=cosine

# Rate Limiting (optional)
RATE_LIMIT_PER_MINUTE=60
```

### 3. Database Setup

```bash
touch database/database.sqlite
php artisan migrate
```

### 4. vec0 Extension (Optional but Recommended)

The vec0 SQLite extension enables fast vector similarity search. Without it, the system falls back to PHP-based calculations.

#### Ubuntu/Debian

```bash
# Download vec0 extension
wget https://github.com/asg017/sqlite-vec/releases/download/v0.1.0/vec0-linux-x86_64.so \
    -O /usr/lib/sqlite3/vec0.so

# Or place in project directory
wget https://github.com/asg017/sqlite-vec/releases/download/v0.1.0/vec0-linux-x86_64.so \
    -O vec0.so
```

#### macOS

```bash
# Download for macOS
wget https://github.com/asg017/sqlite-vec/releases/download/v0.1.0/vec0-macos-x86_64.dylib \
    -O vec0.dylib
```

#### Docker

```dockerfile
# Add to Dockerfile
RUN wget https://github.com/asg017/sqlite-vec/releases/download/v0.1.0/vec0-linux-x86_64.so \
    -O /usr/lib/sqlite3/vec0.so
```

#### Loading the Extension

The application automatically attempts to load the vec0 extension. To manually verify:

```bash
sqlite3 database/database.sqlite -cmd ".load ./vec0" "SELECT vec0_version();"
```

### 5. LMStudio Setup

1. Download and install [LMStudio](https://lmstudio.ai/)
2. Download an embedding model (e.g., `nomic-embed-text-v2`)
3. Start the local server (default: http://localhost:1234)
4. Verify it's running: `curl http://localhost:1234/v1/models`

### 6. Build Assets

```bash
npm run build
# Or for development
npm run dev
```

### 7. Start the Application

```bash
php artisan serve
# Or use Laravel Sail
./vendor/bin/sail up
```

## API Documentation

### Authentication

All API endpoints require authentication via Laravel Sanctum. Obtain a token through the login endpoint or create a personal access token.

```bash
# Example: Create a personal access token
POST /api/tokens/create
{
    "name": "API Token"
}
```

Use the token in requests:

```bash
curl -H "Authorization: Bearer {token}" \
     -H "Accept: application/json" \
     https://your-domain.com/api/search?q=your+query
```

### Endpoints

All endpoints are prefixed with `/api` and require the `auth:sanctum` middleware.

#### Ingest Content

```http
POST /api/ingest
Content-Type: application/json

{
    "text": "Your content here...",
    "tags": ["tag1", "tag2"],
    "chunk_size": 500
}
```

**Response:**

```json
{
    "success": true,
    "message": "Content ingested successfully",
    "data": {
        "node_ids": [1, 2, 3],
        "chunk_count": 3
    }
}
```

#### Semantic Search

```http
GET /api/search?q=your+query&limit=10&min_similarity=0.7
```

**Parameters:**

- `q` (required): Search query string
- `limit` (optional): Maximum results (1-100, default: 10)
- `min_similarity` (optional): Minimum similarity threshold 0-1 (default: 0.0)

**Response:**

```json
{
    "success": true,
    "data": {
        "query": "your query",
        "results": [
            {
                "id": 1,
                "type": "text_chunk",
                "content": "Matching content...",
                "score": 0.9234,
                "created_at": "2024-01-15T10:30:00Z"
            }
        ],
        "count": 1
    }
}
```

#### Text Search (Non-Vector)

```http
GET /api/search/text?q=keyword&type=text_chunk
```

**Parameters:**

- `q` (required): Search keyword
- `type` (optional): Filter by node type

#### Get Node Details

```http
GET /api/nodes/{id}
```

**Response:**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "type": "text_chunk",
        "content": "Node content...",
        "created_at": "2024-01-15T10:30:00Z",
        "updated_at": "2024-01-15T10:30:00Z",
        "has_embedding": true,
        "outgoing_edges": [...],
        "incoming_edges": [...]
    }
}
```

### Rate Limiting

API endpoints are rate-limited to 60 requests per minute per authenticated user. Exceeding this limit returns a `429 Too Many Requests` response.

## Architecture

### Data Model

```
┌─────────────┐       ┌─────────────┐       ┌─────────────┐
│    Nodes    │◄──────┤   Edges     │──────►│    Nodes    │
├─────────────┤       ├─────────────┤       ├─────────────┤
│ id          │       │ id          │       │ id          │
│ type        │       │ source_id   │       │ type        │
│ content     │       │ target_id   │       │ content     │
│ created_at  │       │ relation    │       │ created_at  │
│ updated_at  │       │ weight      │       │ updated_at  │
└─────────────┘       └─────────────┘       └─────────────┘
        │
        │ 1:1
        ▼
┌─────────────┐
│  Embeddings │
├─────────────┤
│ node_id (PK)│
│ vector      │ 768-dim float32 blob
│ created_at  │
│ updated_at  │
└─────────────┘
```

### Services

#### EmbeddingService

Generates vector embeddings using the Laravel AI SDK with LMStudio:

- **Retry Logic**: 3 attempts with exponential backoff (1s, 2s, 4s delays)
- **Caching**: Optional result caching via `AI_EMBEDDING_CACHE`
- **Validation**: Dimension verification (default: 768)

```php
use App\Services\EmbeddingService;

$embeddingService = app(EmbeddingService::class);
$vector = $embeddingService->generateEmbedding("Your text here");
$embedding = $embeddingService->createEmbeddingForNode($node);
```

#### VectorStore

Performs k-NN similarity search:

- **vec0 Extension**: Native SQLite vector operations when available
- **Fallback**: PHP-based cosine similarity calculation
- **Metrics**: Cosine (default), Euclidean, Dot product

```php
use App\Services\VectorStore;

$vectorStore = app(VectorStore::class);
$results = $vectorStore->searchSimilar($queryVector, limit: 10, minSimilarity: 0.7);

// Results format:
// [
//     ['node' => Node, 'score' => 0.9234],
//     ...
// ]
```

### Configuration

All settings are in `config/ai.php`:

| Key | Environment Variable | Default | Description |
|-----|---------------------|---------|-------------|
| `embeddings.model` | `AI_EMBEDDING_MODEL` | `text-embedding-nomic-embed-text-v2` | Embedding model name |
| `embeddings.dimensions` | `AI_EMBEDDING_DIMENSIONS` | `768` | Vector dimensions |
| `embeddings.max_retries` | `AI_EMBEDDING_MAX_RETRIES` | `3` | Retry attempts |
| `embeddings.retry_delay_ms` | `AI_EMBEDDING_RETRY_DELAY_MS` | `1000` | Base retry delay |
| `vector_store.metric` | `AI_VECTOR_METRIC` | `cosine` | Similarity metric |

## Testing

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --filter=SearchTest

# Run with coverage
php artisan test --coverage
```

## Development

### Code Style

This project uses Laravel Pint for code formatting:

```bash
# Check code style
./vendor/bin/pint --test

# Fix code style
./vendor/bin/pint
```

### Frontend Development

```bash
# Hot reload development server
npm run dev

# Build for production
npm run build
```

## Troubleshooting

### Embedding Generation Fails

1. Verify LMStudio is running: `curl http://localhost:1234/v1/models`
2. Check model is loaded in LMStudio
3. Review logs: `storage/logs/laravel.log`
4. Increase retry attempts in config if network is unstable

### vec0 Extension Not Available

1. Verify extension file exists and is loadable
2. Check SQLite version: `sqlite3 --version` (need 3.35+)
3. Test manually: `sqlite3 database.sqlite ".load ./vec0" "SELECT 1"`
4. System falls back to PHP calculations (slower but functional)

### Rate Limiting

If you hit rate limits frequently:

1. Adjust `throttle:60,1` in `routes/api.php`
2. Implement client-side request queuing
3. Consider caching search results

## License

[MIT License](LICENSE)

## Contributing

Contributions are welcome! Please follow the existing code style and include tests with your changes.

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Run tests and linting
5. Submit a pull request

## Credits

- [Laravel](https://laravel.com) - The PHP framework
- [LMStudio](https://lmstudio.ai/) - Local AI model hosting
- [sqlite-vec](https://github.com/asg017/sqlite-vec) - Vector search extension for SQLite
- [Laravel AI SDK](https://github.com/laravel/ai) - AI/ML integration
