# Handoff: Phase 3 Complete - Hybrid Search with Re-ranking

**Date:** 2026-03-10  
**Project:** knowledge-graph  
**Phase:** 3 of 5 (Hybrid Search + Re-ranking)  
**Commit:** `f36f5e7`

---

## What Was Built

**Hybrid Search Service** combining three search strategies for better RAG retrieval accuracy:
1. **Vector search** - Semantic similarity using pgvector
2. **Keyword search** - PostgreSQL full-text search (tsvector/tsrank)
3. **Hypothetical questions** - Match queries against generated questions from Phase 2

**Re-ranking Service** using LLM to score result relevance:
- Takes top N results from hybrid search (default: 20)
- LLM scores each result 0-10 for relevance to query
- Returns top K re-ranked results (default: 5)

---

## Files Created (2)

### 1. `app/Services/Ai/HybridSearchService.php`
- Combines results from 3 search sources
- Weighted scoring with configurable weights
- Alpha parameter for vector vs keyword balance
- Deduplication and result merging
- Boosts results appearing in multiple sources

### 2. `app/Services/Ai/ReRankingService.php`
- LLM-based relevance scoring
- Configurable top_n and final_results
- Fallback ranking when LLM unavailable
- Truncates long content for LLM efficiency

---

## Files Modified (3)

### 1. `app/Http/Controllers/Api/SearchController.php`
- Added `hybrid()` method for new endpoint
- Returns detailed result metadata (scores, sources)
- Includes hypothetical questions in response
- Backward compatible with existing `search()` endpoint

### 2. `routes/api.php`
- Added route: `GET /api/search/hybrid`

### 3. `config/ai.php`
- Added `search` configuration section:
  - `vector_weight` (default: 0.6)
  - `keyword_weight` (default: 0.3)
  - `question_weight` (default: 0.1)
  - `rerank_top_n` (default: 20)
  - `final_results` (default: 5)
  - `enable_reranking` (default: true)

---

## API Usage

### Endpoint
```
GET /api/search/hybrid?q={query}&limit=5&rerank=true&alpha=0.7
```

### Parameters
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| q | string | required | Search query |
| limit | int | 5 | Number of final results |
| rerank | bool | true | Enable LLM re-ranking |
| alpha | float | 0.7 | Vector vs keyword weight (0.0-1.0) |

### Response
```json
{
  "success": true,
  "data": {
    "query": "how does caching work",
    "results": [
      {
        "node": {
          "id": 123,
          "type": "chunk",
          "content": "...",
          "metadata": {
            "hypothetical_questions": ["How does the caching mechanism work?"],
            "keywords": ["caching", "performance"]
          }
        },
        "score": 0.92,
        "relevance_score": 0.95,
        "sources": ["vector", "hypothetical_questions"],
        "document": {
          "id": 45,
          "title": "Architecture Guide"
        }
      }
    ],
    "count": 5,
    "method": "hybrid+rerank",
    "reranked": true,
    "alpha": 0.7
  }
}
```

---

## Configuration

```bash
# Search weights (must sum to ~1.0)
AI_SEARCH_VECTOR_WEIGHT=0.6
AI_SEARCH_KEYWORD_WEIGHT=0.3
AI_SEARCH_QUESTION_WEIGHT=0.1

# Re-ranking settings
AI_SEARCH_RERANK_TOP_N=20
AI_SEARCH_FINAL_RESULTS=5
AI_SEARCH_ENABLE_RERANKING=true
```

---

## How It Works

```
User Query
    ↓
Hybrid Search (3 sources in parallel)
  ├─ Vector: embedding similarity
  ├─ Keyword: PostgreSQL ts_rank  
  └─ Questions: metadata->hypothetical_questions ILIKE
    ↓
Merge & Weight (deduplicate, score)
    ↓
[If rerank=true]
  LLM scores top 20 for relevance
  Sort by combined score
    ↓
Return top 5 results with metadata
```

---

## Test Results

- ✅ 311 tests passing
- ⚠️ 2 pre-existing test failures (unrelated)
- ✅ All new services compile without errors
- ✅ Routes registered correctly
- ✅ Backward compatible with existing `/api/search`

---

## Integration with Previous Phases

**Phase 1 (Provider Infrastructure):**
- Uses `AiProviderFactory::makeLlmProvider()` for re-ranking
- Uses `AiProviderFactory::makeEmbeddingProvider()` for vector search

**Phase 2 (Hypothetical Questions):**
- Leverages `metadata['hypothetical_questions']` for matching
- Questions generated asynchronously during ingestion

---

## Business Value

**For RAG Pipeline:**
- 3x search strategies = better recall
- Re-ranking improves precision
- Alpha parameter tunes for keyword vs semantic
- Shows which sources contributed to each result

**For Client Communication:**
- "Multi-strategy search finds relevant content"
- "AI-powered re-ranking for best results"
- "Transparent scoring shows why results match"

---

## What's Next

**Phase 4:** Query Planner / Reasoning Engine
- Break complex queries into steps
- Multi-agent coordination
- Tool selection

**Phase 5:** Validation Nodes
- Gatekeeper (answers the question?)
- Auditor (anti-hallucination)
- Strategist (business sense)

---

## Notes

- Fully backward compatible - existing `/api/search` unchanged
- Re-ranking can be disabled via parameter or config
- Graceful fallback if LLM unavailable
- Results include source attribution for transparency
- Weights configurable without deployment

---

*Ready for Phase 4 implementation.*
