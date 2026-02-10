# Personal Knowledge Graph Architecture

## Overview
The Personal Knowledge Graph (PKG) is a Laravel 12 application that stores structured knowledge and vector embeddings in PostgreSQL with the pgvector extension. It generates embeddings via LMStudio's `text-embedding-nomic-embed-text-v2` model (768-dimensional vectors) using direct HTTP calls. The system supports ingestion of documents, semantic similarity search, and a web UI for token management and exploration.

## Technology Stack
- **Backend:** Laravel 12, PHP 8.5
- **Database:** PostgreSQL 18.1 with pgvector extension
- **AI:** LMStudio (local inference), `text-embedding-nomic-embed-text-v2` (768 dims)
- **Frontend:** Blade templates, Livewire, Tailwind CSS, Flux UI
- **Authentication:** Laravel Sanctum + Fortify
- **Queue:** Redis/database driver for async embedding generation

## Database Schema

### Tables
| Table | Columns | Notes |
|-------|---------|-------|
| `nodes` | id (UUID), type, content, metadata (jsonb), created_at, updated_at | Entities, concepts, or text chunks |
| `edges` | id (UUID), source_id (FK), target_id (FK), relation, weight | Graph relationships |
| `embeddings` | node_id (FK, PK), embedding (vector(768)), created_at | 768-dimensional vectors |
| `users` | Standard Laravel users table | Managed by Fortify |
| `personal_access_tokens` | Sanctum tokens | API authentication |

### Indexes
- `idx_nodes_type` on `nodes.type`
- `idx_edges_source_target` composite on edges
- `idx_embeddings_node_id` primary key
- HNSW index on `embeddings.embedding` (cosine distance)

## Architecture Diagram
```mermaid
flowchart TD
    subgraph Web UI
        Landing["/ (Landing Page)"]
        Dashboard["/dashboard"]
        Tokens["/user/tokens"]
    end
    
    subgraph API
        Ingest["POST /api/ingest"]
        Search["GET /api/search"]
        TextSearch["GET /api/search/text"]
        Nodes["GET /api/nodes/{id}"]
    end
    
    subgraph Livewire Components
        TokenManager
        DashboardSearch
        DashboardStats
        QuickIngest
    end
    
    subgraph Jobs
        GenerateEmbedding["GenerateEmbedding Job"]
    end
    
    subgraph Services
        EmbeddingService
        VectorStore
    end
    
    Landing --> Tokens
    Landing --> Dashboard
    
    Dashboard --> DashboardSearch
    Dashboard --> DashboardStats
    Dashboard --> QuickIngest
    
    TokenManager -->|"Create/Revoke Tokens"| Sanctum
    
    Ingest -->|"Dispatches"| GenerateEmbedding
    GenerateEmbedding -->|"Generates"| EmbeddingService
    EmbeddingService -->|"Stores"| VectorStore
    VectorStore -->|"Writes"| DB
    
    Search -->|"Searches"| VectorStore
    TextSearch -->|"ILIKE Query"| NodeRepository
    
    DB[(PostgreSQL + pgvector)]
    LMStudio((LMStudio))
    
    EmbeddingService -->|"HTTP POST"| LMStudio
```

## Key Classes & Responsibilities

| Class | Location | Responsibility |
|-------|----------|----------------|
| `IngestController` | app/Http/Controllers/Api/ | Parses text, dispatches embedding jobs |
| `SearchController` | app/Http/Controllers/Api/ | Handles semantic similarity search |
| `NodeController` | app/Http/Controllers/Api/ | CRUD operations on nodes |
| `TokenManager` | app/Livewire/User/ | Create, list, revoke API tokens |
| `DashboardSearch` | app/Livewire/ | Search bar with live results |
| `DashboardStats` | app/Livewire/ | Statistics display |
| `QuickIngest` | app/Livewire/ | Quick content ingestion form |
| `GenerateEmbedding` | app/Jobs/ | Queued job for embedding generation |
| `EmbeddingService` | app/Services/ | Direct HTTP calls to LMStudio |
| `VectorStore` | app/Services/ | pgvector similarity queries |

## API Design

### Endpoints
| Method | Endpoint | Body/Query | Response |
|--------|----------|------------|----------|
| POST | `/api/ingest` | `{"text": "...", "tags": [...]}` | `201` with node IDs |
| GET | `/api/search` | `?q=query&limit=10` | Nodes with similarity scores |
| GET | `/api/search/text` | `?q=keyword&limit=10` | Full-text matched nodes |
| GET | `/api/nodes/{id}` | — | Node with edges |
| DELETE | `/api/nodes/{id}` | — | `204` |

### Authentication
- All API endpoints require `Authorization: Bearer <token>` header
- Tokens created via TokenManager UI or `php artisan token:create`
- Sanctum handles token hashing and validation

## Data Flow

### Ingestion
1. User submits text via Dashboard → QuickIngest or API
2. Text chunked into sentences/paragraphs
3. Each chunk creates a `Node` (type: `text_chunk`)
4. `GenerateEmbedding` job dispatched per node
5. Job calls LMStudio, stores vector in `embeddings` table
6. Optional: tag nodes via `tags` parameter

### Semantic Search
1. Query text sent to `/api/search`
2. Query vector generated via LMStudio
3. `VectorStore::searchSimilar()` finds nearest neighbors
4. Results returned with cosine similarity scores (0-1)

## User Interface

### Landing Page (`/`)
- Project description and features
- API documentation with curl examples
- Login/Register buttons

### Dashboard (`/dashboard`) - Authenticated
- **Stats cards:** Total nodes, embeddings, edges
- **Search bar:** Real-time semantic search
- **Quick Ingest:** Add new content
- **Recent Nodes:** Latest additions with embedding status

### Token Management (`/user/tokens`)
- Create named API tokens
- Copy token to clipboard
- Revoke tokens with confirmation

## Testing
- **181 tests passing** (453 assertions)
- Coverage: API endpoints, services, Livewire components, jobs
- Tests use PostgreSQL connection (see `phpunit.xml`)

## Known Issues / Technical Debt
| Issue | Priority | Status |
|-------|----------|--------|
| Copy to clipboard button JS | Low | Minor UI fix needed |
| HNSW index creation automation | Medium | Helper methods exist, not automated |

## Commands
```bash
# Run tests
php artisan test

# Create API token
php artisan tinker --execute="\$user = App\Models\User::first(); echo \$user->createToken('name')->plainTextToken;"

# Run queue worker
php artisan queue:work

# Rebuild HNSW index (manual)
php artisan tinker --execute="\$s = new App\Services\VectorStore; \$s->createHnswIndex('cosine');"
```

## File Structure
```
app/
├── Http/
│   └── Controllers/Api/
│       ├── IngestController.php
│       ├── SearchController.php
│       └── NodeController.php
├── Jobs/
│   └── GenerateEmbedding.php
├── Livewire/
│   ├── DashboardSearch.php
│   ├── DashboardStats.php
│   ├── QuickIngest.php
│   └── User/
│       └── TokenManager.php
├── Services/
│   ├── EmbeddingService.php
│   └── VectorStore.php
└── Models/
    ├── Node.php
    ├── Edge.php
    └── Embedding.php

resources/views/
├── welcome.blade.php          # Landing page
├── dashboard.blade.php         # Dashboard
├── user/
│   └── tokens.blade.php        # Token management
└── livewire/
    ├── dashboard-search.blade.php
    ├── dashboard-stats.blade.php
    ├── quick-ingest.blade.php
    └── user/
        └── token-manager.blade.php
```

## Recent Changes (2026-02-10)
- Migrated from SQLite to PostgreSQL + pgvector
- Added TokenManager for API token management
- Implemented queued embedding jobs (3 retries, 120s timeout)
- Built landing page with API examples
- Created dashboard with search, ingest, and stats
- 181 tests passing
