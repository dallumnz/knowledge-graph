# Document Storage Enhancement for Knowledge Graph

**Task:** Add document storage to enable source attribution, version control, and improved RAG

**Location:** ~/projects/knowledge-graph

---

## Current State

The Knowledge Graph stores:
- `nodes` — content chunks with type, content, timestamps
- `embeddings` — vector storage linked to nodes
- `edges` — relationships between nodes
- `metadata` — separate table for key-value metadata

**Problem:** No source document tracking. Chunks exist in isolation with no reference to where they came from.

---

## Requirements

### 1. Database Schema

Add `documents` table:
```sql
CREATE TABLE documents (
    id BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    title VARCHAR(500) NOT NULL,
    source_type VARCHAR(50) NOT NULL, -- 'file', 'url', 'text', 'api'
    source_path TEXT, -- file path, URL, or external ID
    content TEXT, -- full original document content
    metadata JSONB DEFAULT '{}', -- author, date, document_type, tags, etc.
    version INT DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Add document_id to nodes
ALTER TABLE nodes ADD COLUMN document_id BIGINT REFERENCES documents(id) ON DELETE SET NULL;

-- Index for document lookups
CREATE INDEX idx_documents_type ON documents(source_type);
CREATE INDEX idx_documents_active ON documents(is_active);
CREATE INDEX idx_documents_created ON documents(created_at DESC);
```

### 2. Ingestion Updates

Modify `IngestController` to:
- Accept optional document metadata (title, source_type, source_path, document_metadata)
- Create document record first
- Link chunks (nodes) to the document
- Store full document content for reference

**New API endpoint parameters:**
```php
POST /api/ingest
{
    "chunks": [...], // existing
    "document": {
        "title": "Q4 Policy Document",
        "source_type": "file",
        "source_path": "/docs/policies/q4-2024.pdf",
        "metadata": {
            "author": "Legal Team",
            "document_type": "policy",
            "version": 1
        }
    }
}
```

### 3. Document Retrieval Endpoint

Add new endpoint to fetch documents:
```php
GET /api/documents/{id} -- Get single document
GET /api/documents -- List documents with pagination/filtering
GET /api/documents/{id}/chunks -- Get all chunks from a document
```

### 4. Search Response Enhancement

Update `/api/search` to return source attribution:
```json
{
    "results": [
        {
            "node": {
                "id": 123,
                "content": "...",
                "document_id": 456
            },
            "score": 0.85,
            "document": {
                "id": 456,
                "title": "Q4 Policy Document",
                "source_path": "/docs/policies/q4-2024.pdf"
            }
        }
    ]
}
```

### 5. Migration

Create Laravel migration for:
- Create `documents` table
- Add `document_id` to `nodes`
- Seed no-op data for existing nodes (document_id = NULL)

---

## Files to Modify/Create

### New Files
1. `database/migrations/xxxx_add_documents_table.php`
2. `app/Models/Document.php`
3. `app/Http/Controllers/Api/DocumentController.php`
4. `routes/api.php` (add document routes)
5. `app/Services/DocumentService.php`

### Modified Files
1. `app/Http/Controllers/Api/IngestController.php` — accept document metadata
2. `app/Services/IngestionService.php` — create documents, link nodes
3. `app/Services/SearchService.php` — return document info in results
4. `config/ai.php` — add document settings

### Tests to Write
1. `tests/Feature/Api/DocumentsTest.php`
2. `tests/Unit/Services/DocumentServiceTest.php`
3. `tests/Feature/Api/SearchTest.php` — verify document attribution

---

## Implementation Steps

### Step 1: Database
- [ ] Create migration for documents table
- [ ] Run migration
- [ ] Create Document model

### Step 2: Document Service
- [ ] Create DocumentService with CRUD operations
- [ ] Document retrieval
- [ ] Document listing with filters

### Step 3: Update Ingestion
- [ ] Modify IngestController to accept document metadata
- [ ] Update IngestionService to create documents
- [ ] Link nodes to documents during ingestion
- [ ] Write tests

### Step 4: Update Search
- [ ] Modify SearchService to include document info
- [ ] Update API response format
- [ ] Write tests

### Step 5: Documentation
- [ ] Update API docs
- [ ] Add usage examples

---

## Success Criteria

- [ ] Documents can be created and retrieved
- [ ] Chunks (nodes) are linked to source documents
- [ ] Search results include document attribution
- [ ] All existing tests pass
- [ ] New tests for document features pass
- [ ] API is backward compatible (existing ingest still works)

---

## Notes

- Keep backward compatibility — existing `/api/ingest` without document data should still work
- Document content can be large — consider lazy loading or separate endpoint
- Future: Add document versioning for policy updates
- Future: Add document deprecation (is_active flag)

---

## Commands

```bash
# Run migration
php artisan migrate

# Run tests
php artisan test

# Start server
php artisan serve
```

---

## Related Files

- Current ingestion: `app/Http/Controllers/Api/IngestController.php`
- Current search: `app/Services/SearchService.php`
- Models: `app/Models/Node.php`, `app/Models/Embedding.php`
