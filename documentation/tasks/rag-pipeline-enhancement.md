# RAG Pipeline Enhancement - Knowledge Graph

## Context
The Personal Knowledge Graph currently has basic RAG:
- Fixed token chunking (500 chars)
- Basic embeddings (nomic-embed-text-v2)
- Minimal metadata (source, title)
- Vector storage via pgvector

## Goal
Design and implement an enhanced RAG pipeline with:
1. Rich metadata per chunk (summaries, keywords, structure)
2. Smart chunking (respect document boundaries)
3. Document restructuring (understand headings, tables, lists)
4. Weighted keyword extraction (for SEO, filtering, faceted search)

## Implementation Order (3 → 2 → 1 → 4)

### Phase 1: Metadata Creation (Priority: HIGH)
**Start here** — Adds immediate value with existing chunking.

Requirements:
- Generate summary for each chunk (80-120 chars)
- Extract keywords/tags per chunk (3-5 significant terms)
- Store as JSON in node.metadata
- Update IngestController to call metadata service
- Add tests for metadata generation

Deliverables:
- `MetadataService` class
- Tests for metadata extraction
- Updated IngestController

### Phase 2: Smart Chunking
Improve chunking to respect document boundaries.

Requirements:
- Split on headers (Markdown `#`, HTML `<h1-6>`)
- Preserve paragraph integrity
- Handle lists as atomic units
- Configurable chunk size with overlap

Deliverables:
- Enhanced `chunkText()` method or new `DocumentChunker` class
- Tests for boundary-aware chunking
- Backwards compatible with existing API

### Phase 3: Document Restructuring
Deep parsing to understand document structure.

Requirements:
- Parse Markdown/HTML structure
- Identify headings, tables, code blocks, lists
- Create structural metadata (hierarchy depth, section type)
- Handle nested structures

Deliverables:
- `DocumentParser` or `StructureAnalyzer` class
- Structural metadata schema
- Tests for various document types

### Phase 4: Weighted Keyword Extraction
Extract and weight keywords for filtering/SEO.

Requirements:
- TF-IDF or embedding-based keyword extraction
- Keyword weighting by position, frequency
- Support for domain-specific vocabulary
- Expose weights in search API

Deliverables:
- `KeywordExtractor` service
- Weighted search endpoint
- Dashboard integration for keyword management

## Technical Considerations

### Database
- PostgreSQL with pgvector (already in use)
- May need new columns/tables for structural metadata

### Embeddings
- Model: nomic-embed-text-v2 (768 dims, already configured)
- Cache: Valkey (already configured)

### API Compatibility
- Maintain backward compatibility with existing `/api/ingest`
- Add optional parameters for new features
- Version endpoints if breaking changes needed

### Performance
- Async processing for large documents
- Chunking should be fast (<100ms for typical doc)
- Metadata generation can be async/queued

## Testing Strategy

1. Unit tests for each component
2. Integration tests for full pipeline
3. Performance benchmarks for chunking
4. Search quality evaluation (retrieval accuracy)

## Senior-Architect Deliverables

For this task, Senior-Architect should produce:

1. **Architecture Diagram** — Component relationships, data flow
2. **Class Structure** — Service classes, their responsibilities, interfaces
3. **Database Schema Changes** — Any new columns, tables, indexes
4. **API Changes** — Endpoint modifications, new parameters
5. **Implementation Plan** — Step-by-step approach for phases 1-4
6. **Risks & Mitigations** — What could go wrong, how to handle

## Current Codebase State

- `IngestController::chunkText()` — Current basic chunking (sentence-based)
- `DocumentService` — For document CRUD (recently added)
- `EmbeddingService` — Vector operations (cached)
- 258 passing tests — Good test coverage to maintain

## Commands

```bash
# Run tests
cd ~/projects/knowledge-graph && php artisan test

# Check current chunking
cd ~/projects/knowledge-graph && php artisan tinker --execute="echo (new \App\Http\Controllers\Api\IngestController())->chunkText('Your test text here', 500);"
```

## Success Criteria

1. Each phase can be implemented independently
2. Existing API remains compatible
3. Search quality improves measurably
4. New features add clear value (SEO, filtering, attribution)
5. Tests pass throughout
