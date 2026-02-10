# Personal Knowledge Graph (PKG)

A Laravel-based Personal Knowledge Graph system with vector embeddings and semantic search. Store content as interconnected nodes, search using AI-powered similarity, and manage everything through a clean web UI.

## Features

- **Knowledge Graph Storage** — Nodes with typed relationships (edges)
- **Vector Embeddings** — 768-dimensional embeddings via LMStudio
- **Semantic Search** — Find similar content using cosine similarity (pgvector)
- **Web UI** — Landing page, dashboard, and token management
- **Queued Ingestion** — Async embedding generation with retry logic
- **RESTful API** — Full API with Sanctum authentication

## Tech Stack

- Laravel 12, PHP 8.5
- PostgreSQL 18.1 + pgvector extension
- LMStudio (local inference)
- Livewire, Tailwind CSS, Flux UI
- Laravel Sanctum + Fortify

## Quick Start

### Prerequisites

- PostgreSQL 18.1 with pgvector extension
- LMStudio running locally (http://localhost:1234)
- PHP 8.5, Composer, Node.js

### Installation

```bash
git clone https://github.com/dallumnz/knowledge-graph.git
cd knowledge-graph
composer install
npm install
npm run build
```

### Environment

```bash
cp .env.example .env
php artisan key:generate
```

Configure `.env`:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=knowledge_graph
DB_USERNAME=your_user
DB_PASSWORD=your_password

LMSTUDIO_URL=http://localhost:1234
LMSTUDIO_API_KEY=lmstudio
LMSTUDIO_EMBEDDING_MODEL=text-embedding-nomic-embed-text-v2
LMSTUDIO_EMBEDDING_DIMENSIONS=768
```

### Database

```bash
sudo -u postgres createuser --interactive your_user
sudo -u postgres createdb -O your_user knowledge_graph
psql -U your_user -d knowledge_graph -c "CREATE EXTENSION IF NOT EXISTS vector;"

php artisan migrate
```

### Run

```bash
php artisan serve
```

Visit http://localhost:8000

## Web UI

### Landing Page (`/`)
Project overview, features, and API documentation with curl examples.

### Dashboard (`/dashboard`) — Authenticated
- **Search** — Semantic search with live results
- **Quick Ingest** — Add new content
- **Stats** — Node/embedding counts, recent activity

### Token Management (`/user/tokens`)
Create and manage API tokens for programmatic access.

## API

### Authentication

All API requests require a Bearer token:

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
     http://localhost:8000/api/search?q=your+query
```

### Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/ingest` | Ingest text content |
| GET | `/api/search?q=` | Semantic similarity search |
| GET | `/api/search/text?q=` | Full-text search |
| GET | `/api/nodes/{id}` | Get node with edges |

### Example: Ingest

```bash
curl -X POST http://localhost:8000/api/ingest \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"text": "Your content here", "tags": ["tag1", "tag2"]}'
```

Response:
```json
{
    "success": true,
    "data": {
        "node_ids": [1, 2, 3],
        "chunk_count": 3
    }
}
```

### Example: Search

```bash
curl "http://localhost:8000/api/search?q=your+query&limit=10" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Response:
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
                "created_at": "2026-02-10T12:00:00Z"
            }
        ],
        "count": 1
    }
}
```

## Architecture

```
┌─────────────┐       ┌─────────────┐       ┌─────────────┐
│    Nodes    │◄──────┤   Edges     │──────►│    Nodes    │
├─────────────┤       ├─────────────┤       ├─────────────┤
│ id          │       │ id          │       │ id          │
│ type        │       │ source_id   │       │ type        │
│ content     │       │ target_id   │       │ content     │
│ metadata    │       │ relation    │       │             │
└─────────────┘       │ weight      │       └─────────────┘
        │              └─────────────┘              │
        │ 1:1                                  │
        ▼                                       │
┌─────────────┐                                  │
│ Embeddings  │◄─────────────────────────────────┘
├─────────────┤
│ node_id (PK)│
│ embedding   │ vector(768)
│ created_at  │
└─────────────┘
```

## Testing

```bash
php artisan test
```

**181 tests passing** (453 assertions)

## Commands

```bash
# Create API token
php artisan tinker --execute="\$user = App\Models\User::first(); echo \$user->createToken('name')->plainTextToken;"

# Rebuild HNSW index
php artisan tinker --execute="\$s = new App\Services\VectorStore; \$s->createHnswIndex('cosine');"

# Run queue worker
php artisan queue:work
```

## License

MIT

## Contributing

Built with Laravel, pgvector, and LMStudio.
