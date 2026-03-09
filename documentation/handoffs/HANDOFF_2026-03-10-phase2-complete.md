# Handoff: Phase 2 Complete - Hypothetical Question Generation

**Date:** 2026-03-10  
**Project:** knowledge-graph  
**Phase:** 2 of 5 (Hypothetical Question Generation)  
**Commit:** `2682931`

---

## What Was Built

**Hypothetical Question Generation Service**

For each text chunk (Node), automatically generates 3-5 questions the chunk could answer. Stored in Node metadata to improve RAG query matching.

---

## Files Created (2)

1. **`app/Services/Ai/HypotheticalQuestionService.php`**
   - Generates questions using LLM (local-ai Qwen3.5-9B-GGUF)
   - Configurable questions per chunk (default: 4)
   - Graceful error handling (returns empty array on failure)
   - Regex parsing to extract clean question list

2. **`app/Jobs/GenerateHypotheticalQuestions.php`**
   - Queued job for async processing
   - Prevents duplicate generation
   - Stores questions in `Node.metadata['hypothetical_questions']`

---

## Files Modified (3)

1. **`app/Jobs/GenerateEmbedding.php`**
   - Dispatches question generation job after embedding
   - Feature flag conditional execution

2. **`app/Http/Controllers/Api/IngestController.php`**
   - Uses queued jobs for non-blocking ingestion

3. **`app/Livewire/QuickIngest.php`**
   - Uses queued jobs for UI ingestion

---

## Configuration

```bash
# Enable feature
AI_ENABLE_HYPOTHETICAL_QUESTIONS=true

# Questions per chunk (default: 4)
AI_QUESTIONS_PER_CHUNK=4
```

---

## How It Works

```
Document Upload
     ↓
Chunking → Creates Nodes
     ↓
GenerateEmbedding Job
     ↓
GenerateHypotheticalQuestions Job (async)
     ↓
Store in Node.metadata['hypothetical_questions']
```

**Example metadata:**
```json
{
  "hypothetical_questions": [
    "What is the purpose of this feature?",
    "How does the caching mechanism work?",
    "What are the security considerations?"
  ],
  "keywords": ["caching", "security"],
  "summary": "Document covers..."
}
```

---

## Business Value

**For RAG Pipeline:**
- Questions improve query-to-chunk matching
- Semantic search can match "How does X work?" to chunks with that question
- Reduces retrieval failures on complex queries
- Enables better multi-hop reasoning

**For Client Communication:**
- "Automatically extracts key questions from your documents"
- "Improves search accuracy by understanding what users ask"
- "No manual tagging required - fully automated"

---

## Test Results

- ✅ 311 tests passing
- ⚠️ 2 pre-existing test failures (unrelated)
- ✅ Async processing verified
- ✅ Feature flag working

---

## Integration with Phase 1

Uses the provider infrastructure from Phase 1:
```php
$llm = AiProviderFactory::makeLlmProvider();
$questions = HypotheticalQuestionService::generate($chunkContent);
```

---

## What's Next

**Phase 3: Hybrid Search**
- Combine vector + keyword search
- Use hypothetical questions for better ranking
- Re-ranking service

**Phase 4-5:** Query Planner, Validation Nodes, Evaluation Framework

---

## Notes

- Fully async - doesn't block ingestion
- Graceful degradation - ingestion works even if question generation fails
- Questions stored in existing metadata column (no schema changes)
- Feature can be toggled without deployment

---

*Ready for your review. Commit staged and ready to push with Phase 1 commits.*
