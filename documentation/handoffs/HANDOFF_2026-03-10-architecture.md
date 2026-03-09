# Handoff: LMStudio → local-ai Migration & Hypothetical Question Generation

**Date:** 2026-03-10  
**From:** Architecture Subagent  
**To:** Implementation Team  
**Priority:** High  
**Status:** Ready for Implementation

---

## Summary

This handoff documents the architecture for migrating the Knowledge Graph system from LMStudio to local-ai, and introduces hypothetical question generation as a Phase 1 Production RAG enhancement.

---

## Background

### Current State
- **Embedding Provider:** LMStudio at `http://localhost:1234`
- **Model:** nomic-embed-text-v2
- **Dimensions:** 768
- **Problem:** Tight coupling to LMStudio, no clean path to external APIs

### Target State
- **Embedding Provider:** local-ai at `http://localhost:8080`
- **Embedding Model:** nomic-embed-text-v1.5 (768-dim)
- **LLM Model:** Qwen3.5-9B-GGUF (for question generation)
- **Benefit:** Configurable provider system, supports future OpenAI/Anthropic migration

---

## Architecture Decisions

### 1. Provider Abstraction Pattern

**Decision:** Use a factory pattern with interface-based providers.

**Rationale:**
- Clean separation between embedding and LLM concerns
- Runtime provider switching via configuration
- Easy to add new providers (OpenAI, Anthropic) later
- LMStudio can be kept as legacy adapter for rollback

**Trade-offs:**
- More files/classes than direct HTTP calls
- Worth it for long-term maintainability

### 2. Separate Embedding vs LLM Services

**Decision:** Create distinct `EmbeddingService` and `LlmService` classes.

**Rationale:**
- Different retry patterns (embeddings are idempotent, LLM calls may not be)
- Different caching strategies
- Different provider configurations (may use OpenAI for LLM but local-ai for embeddings)

### 3. Hypothetical Questions in Node Metadata

**Decision:** Store questions in the existing `metadata` JSON column on nodes table.

**Rationale:**
- No schema changes required
- Flexible structure (can add more metadata later)
- Already indexed via GIN if needed

**Trade-offs:**
- Questions not queryable via SQL without JSON operators
- Acceptable for initial implementation

### 4. Synchronous Question Generation

**Decision:** Generate questions synchronously during ingestion (not queued).

**Rationale:**
- Simpler implementation
- Questions are small, fast to generate (3-5 per chunk)
- Can be moved to async later if performance issues arise

**Trade-offs:**
- Ingestion latency increases slightly
- Mitigate by making question generation configurable (feature flag)

---

## Implementation Checklist

### Phase 1: Provider Infrastructure

- [ ] Create `app/Contracts/Ai/EmbeddingProviderInterface.php`
- [ ] Create `app/Contracts/Ai/LlmProviderInterface.php`
- [ ] Create `app/Services/Ai/AiProviderFactory.php`
- [ ] Create `app/Services/Ai/Providers/LocalAiEmbeddingProvider.php`
- [ ] Create `app/Services/Ai/Providers/LocalAiLlmProvider.php`
- [ ] Create `app/Services/Ai/Providers/LmStudioEmbeddingProvider.php` (legacy)
- [ ] Update `config/ai.php` with new structure
- [ ] Update `.env` with new variables

### Phase 2: Service Refactoring

- [ ] Refactor `app/Services/EmbeddingService.php` to use provider
- [ ] Create `app/Services/Ai/LlmService.php`
- [ ] Add provider availability checks
- [ ] Update `GenerateEmbedding` job if needed

### Phase 3: Question Generation

- [ ] Create `app/Services/Questions/HypotheticalQuestionService.php`
- [ ] Design prompt template for question generation
- [ ] Implement question parsing from LLM response
- [ ] Add feature flag configuration
- [ ] Create `app/Jobs/GenerateHypotheticalQuestions.php` (optional)

### Phase 4: Pipeline Integration

- [ ] Update `IngestController::store()` to call question service
- [ ] Update `IngestController::quickIngest()` similarly
- [ ] Ensure questions stored in node metadata
- [ ] Test end-to-end ingestion flow

### Phase 5: Testing & Verification

- [ ] Unit tests for each provider
- [ ] Integration test for full ingestion pipeline
- [ ] Verify local-ai connectivity
- [ ] Test question quality from Qwen3.5-9B-GGUF
- [ ] Verify embedding dimensions (768)

---

## Configuration Migration

### New .env Variables

```bash
# Primary provider settings
AI_EMBEDDING_PROVIDER=local-ai
AI_LLM_PROVIDER=local-ai

# local-ai endpoints
LOCALAI_URL=http://localhost:8080
LOCALAI_API_KEY=
LOCALAI_EMBEDDING_MODEL=nomic-embed-text-v1.5
LOCALAI_EMBEDDING_DIMENSIONS=768
LOCALAI_LLM_MODEL=Qwen3.5-9B-GGUF

# Feature flags
AI_ENABLE_HYPOTHETICAL_QUESTIONS=true
AI_QUESTIONS_PER_CHUNK=4
AI_QUESTION_CACHE=true
```

### Backward Compatibility

LMStudio settings are kept for rollback:
```bash
LMSTUDIO_URL=http://localhost:1234
LMSTUDIO_API_KEY=lmstudio
```

To rollback, simply change:
```bash
AI_EMBEDDING_PROVIDER=lmstudio
```

---

## Code Structure

### Directory Layout

```
app/
├── Contracts/
│   └── Ai/
│       ├── EmbeddingProviderInterface.php
│       └── LlmProviderInterface.php
├── Services/
│   ├── Ai/
│   │   ├── AiProviderFactory.php
│   │   ├── LlmService.php
│   │   └── Providers/
│   │       ├── LocalAiEmbeddingProvider.php
│   │       ├── LocalAiLlmProvider.php
│   │       └── LmStudioEmbeddingProvider.php
│   ├── EmbeddingService.php (refactored)
│   └── Questions/
│       └── HypotheticalQuestionService.php
```

### Key Implementation Notes

1. **LocalAiEmbeddingProvider:** Uses `/v1/embeddings` endpoint (OpenAI-compatible)
2. **LocalAiLlmProvider:** Uses `/v1/completions` or `/v1/chat/completions`
3. **Error Handling:** All providers should retry on 5xx, fail fast on 4xx
4. **Caching:** Cache embeddings and questions separately (different TTLs)

---

## Prompt Template for Question Generation

```php
$prompt = <<<PROMPT
Given the following text chunk, generate {$count} specific questions that this text could answer.
The questions should be natural, conversational queries that a user might actually ask.
Focus on factual, information-seeking questions.

Text chunk:
---
{$chunkContent}
---

Return only the questions, one per line, without numbering or bullet points.
PROMPT;
```

**Expected Output Format:**
```
What is the main concept discussed in this section?
How does this process work?
What are the benefits mentioned?
When should this approach be used?
```

**Parsing Strategy:**
- Split by newline
- Trim whitespace
- Filter out empty lines
- Limit to configured count

---

## Testing Checklist

### Unit Tests
```bash
# Provider tests
php artisan test --filter=LocalAiEmbeddingProviderTest
php artisan test --filter=LocalAiLlmProviderTest
php artisan test --filter=AiProviderFactoryTest

# Service tests
php artisan test --filter=HypotheticalQuestionServiceTest
```

### Integration Tests
```bash
# Full pipeline test
php artisan test --filter=IngestionFlowTest

# API endpoint test
php artisan test --filter=IngestControllerTest
```

### Manual Verification
```bash
# 1. Check local-ai connectivity
curl http://localhost:8080/v1/models

# 2. Test embedding generation
curl -X POST http://localhost:8080/v1/embeddings \
  -H "Content-Type: application/json" \
  -d '{"model":"nomic-embed-text-v1.5","input":"test"}'

# 3. Test LLM generation
curl -X POST http://localhost:8080/v1/completions \
  -H "Content-Type: application/json" \
  -d '{"model":"Qwen3.5-9B-GGUF","prompt":"Say hello","max_tokens":10}'
```

---

## Rollback Plan

If issues are encountered:

1. **Immediate rollback:**
   ```bash
   # Update .env
   AI_EMBEDDING_PROVIDER=lmstudio
   
   # Clear config cache
   php artisan config:clear
   ```

2. **Code rollback:**
   - Revert to previous commit
   - LMStudio provider remains available

3. **Data considerations:**
   - Existing embeddings remain valid (same 768 dimensions)
   - Nodes with hypothetical questions are backward compatible
   - No data migration required

---

## Questions for Implementation Team

1. **LLM Endpoint:** Does local-ai expose `/v1/completions` or `/v1/chat/completions` for Qwen3.5-9B-GGUF?

2. **Question Quality:** Should we add a confidence score or filter low-quality questions?

3. **Async Generation:** Should question generation be queued for large documents?

4. **Caching:** What TTL for generated questions? (suggested: 30 days)

5. **Existing Data:** Should we backfill questions for existing chunks? (suggested: no, only new)

---

## References

- [ARCHITECTURE.md](../ARCHITECTURE.md) - Full architecture documentation
- [config/ai.php](../../config/ai.php) - Current configuration
- [app/Services/EmbeddingService.php](../../app/Services/EmbeddingService.php) - Current implementation
- local-ai documentation: https://localai.io

---

## Sign-off

- [ ] Architecture reviewed
- [ ] Implementation plan understood
- [ ] Questions answered
- [ ] Ready to proceed

---

*This handoff document is a living document. Update it as implementation progresses and decisions are refined.*
