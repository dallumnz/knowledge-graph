# Knowledge Graph Architecture

## Overview

This document describes the architecture of the Knowledge Graph system, focusing on the AI provider abstraction, RAG pipeline, and hypothetical question generation for improved retrieval.

## Current State

The system currently uses:
- **LMStudio** at `http://localhost:1234` for embeddings
- **nomic-embed-text-v2** embedding model (768-dim)
- PostgreSQL with **pgvector** for vector storage
- Simple metadata extraction (summaries, keywords)

## Target State

Migrate to:
- **local-ai** at `http://localhost:8080` for embeddings and LLM inference
- **nomic-embed-text-v1.5** embedding model (768-dim)
- **Qwen3.5-9B-GGUF** for LLM tasks (hypothetical question generation)
- Configurable provider system supporting future external APIs (OpenAI, Anthropic)

---

## 1. Provider Abstraction Design

### 1.1 Rationale

The current `EmbeddingService` is tightly coupled to LMStudio configuration. To support multiple providers without code changes, we need a provider abstraction that:
- Encapsulates provider-specific HTTP clients
- Normalizes request/response formats
- Allows runtime provider switching via configuration
- Supports future additions (OpenAI, Anthropic, etc.)

### 1.2 Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                      AI Provider System                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│   ┌─────────────────┐    ┌─────────────────┐                    │
│   │ EmbeddingService │    │   LLMService    │                    │
│   │                 │    │                 │                    │
│   │ - generate()    │    │ - generate()    │                    │
│   │ - batch()       │    │ - chat()        │                    │
│   └────────┬────────┘    └────────┬────────┘                    │
│            │                      │                              │
│            └──────────┬───────────┘                              │
│                       │                                          │
│            ┌──────────▼───────────┐                              │
│            │   ProviderFactory    │                              │
│            │                      │                              │
│            │ - make($type)        │                              │
│            └──────────┬───────────┘                              │
│                       │                                          │
│        ┌──────────────┼──────────────┐                          │
│        │              │              │                          │
│   ┌────▼────┐   ┌────▼────┐   ┌────▼────┐                      │
│   │ LocalAi │   │ OpenAi  │   │Anthropic│                      │
│   │Provider │   │Provider │   │Provider │                      │
│   └─────────┘   └─────────┘   └─────────┘                      │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 1.3 Core Interfaces

```php
// Contracts for provider system
interface EmbeddingProviderInterface
{
    public function embed(string $text): ?array;
    public function embedBatch(array $texts): array;
    public function getDimensions(): int;
    public function getModel(): string;
    public function isAvailable(): bool;
}

interface LlmProviderInterface
{
    public function generate(string $prompt, array $options = []): ?string;
    public function chat(array $messages, array $options = []): ?string;
    public function getModel(): string;
    public function isAvailable(): bool;
}
```

### 1.4 Provider Factory

The factory creates provider instances based on configuration:

```php
class AiProviderFactory
{
    public static function makeEmbeddingProvider(?string $provider = null): EmbeddingProviderInterface
    {
        $provider ??= config('ai.default_embedding_provider');
        
        return match($provider) {
            'local-ai' => new LocalAiEmbeddingProvider(),
            'openai' => new OpenAiEmbeddingProvider(),
            'lmstudio' => new LmStudioEmbeddingProvider(), // legacy
            default => throw new \InvalidArgumentException("Unknown provider: {$provider}"),
        };
    }
    
    public static function makeLlmProvider(?string $provider = null): LlmProviderInterface
    {
        $provider ??= config('ai.default_llm_provider');
        
        return match($provider) {
            'local-ai' => new LocalAiLlmProvider(),
            'openai' => new OpenAiLlmProvider(),
            'anthropic' => new AnthropicLlmProvider(),
            default => throw new \InvalidArgumentException("Unknown provider: {$provider}"),
        };
    }
}
```

---

## 2. Hypothetical Question Generation Flow

### 2.1 Rationale

Hypothetical question generation improves RAG retrieval by:
- Creating query-to-chunk matching beyond semantic similarity
- Enabling retrieval of chunks that answer similar questions
- Providing additional context for ranking relevance

For each text chunk, we generate 3-5 questions that the chunk could answer. These are stored in the node's metadata and indexed for search.

### 2.2 Flow

```
┌─────────────────────────────────────────────────────────────────┐
│              Hypothetical Question Generation Flow               │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Document Ingestion Pipeline                                     │
│  ===========================                                     │
│                                                                  │
│  ┌─────────────┐     ┌─────────────┐     ┌─────────────┐        │
│  │   Parse     │────▶│    Chunk    │────▶│  Metadata   │        │
│  │  Document   │     │   Text      │     │  Extract    │        │
│  └─────────────┘     └─────────────┘     └──────┬──────┘        │
│                                                  │               │
│                                                  ▼               │
│  ┌─────────────┐     ┌─────────────┐     ┌─────────────┐        │
│  │   Store     │◀────│   Create    │◀────│  Generate   │        │
│  │  Questions  │     │    Node     │     │ Questions   │        │
│  │  (metadata) │     │             │     │   (LLM)     │        │
│  └─────────────┘     └──────┬──────┘     └─────────────┘        │
│                             │                                    │
│                             ▼                                    │
│                      ┌─────────────┐                             │
│                      │  Generate   │                             │
│                      │  Embedding  │                             │
│                      └─────────────┘                             │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 2.3 Question Generation Prompt

```php
$prompt = <<<PROMPT
Given the following text chunk, generate 3-5 specific questions that this text could answer.
The questions should be natural, conversational queries that a user might actually ask.
Focus on factual, information-seeking questions.

Text chunk:
---
{$chunkContent}
---

Return only the questions, one per line, without numbering or bullet points.
PROMPT;
```

### 2.4 Storage Format

Questions are stored in the node's metadata JSON column:

```php
$metadata = [
    'summary' => 'Brief summary of the chunk...',
    'keywords' => ['keyword1', 'keyword2', ...],
    'hypothetical_questions' => [
        'What is the main concept discussed in this section?',
        'How does this process work?',
        'What are the benefits mentioned?',
    ],
];
```

---

## 3. Configuration System

### 3.1 Config Structure (`config/ai.php`)

```php
return [
    // Default providers
    'default_embedding_provider' => env('AI_EMBEDDING_PROVIDER', 'local-ai'),
    'default_llm_provider' => env('AI_LLM_PROVIDER', 'local-ai'),
    
    // Provider configurations
    'providers' => [
        'local-ai' => [
            'driver' => 'local-ai',
            'url' => env('LOCALAI_URL', 'http://localhost:8080'),
            'api_key' => env('LOCALAI_API_KEY', ''),
            'embedding_model' => env('LOCALAI_EMBEDDING_MODEL', 'nomic-embed-text-v1.5'),
            'embedding_dimensions' => (int) env('LOCALAI_EMBEDDING_DIMENSIONS', 768),
            'llm_model' => env('LOCALAI_LLM_MODEL', 'Qwen3.5-9B-GGUF'),
            'timeout' => 60,
        ],
        
        'openai' => [
            'driver' => 'openai',
            'api_key' => env('OPENAI_API_KEY'),
            'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
            'embedding_dimensions' => (int) env('OPENAI_EMBEDDING_DIMENSIONS', 1536),
            'llm_model' => env('OPENAI_LLM_MODEL', 'gpt-4o-mini'),
            'timeout' => 30,
        ],
        
        'anthropic' => [
            'driver' => 'anthropic',
            'api_key' => env('ANTHROPIC_API_KEY'),
            'llm_model' => env('ANTHROPIC_LLM_MODEL', 'claude-3-haiku-20240307'),
            'timeout' => 30,
        ],
        
        // Legacy support
        'lmstudio' => [
            'driver' => 'openai_compatible',
            'url' => env('LMSTUDIO_URL', 'http://localhost:1234'),
            'api_key' => env('LMSTUDIO_API_KEY', 'lmstudio'),
            'embedding_model' => env('LMSTUDIO_EMBEDDING_MODEL', 'text-embedding-nomic-embed-text-v2'),
            'embedding_dimensions' => (int) env('LMSTUDIO_EMBEDDING_DIMENSIONS', 768),
        ],
    ],
    
    // Feature flags
    'features' => [
        'hypothetical_questions' => env('AI_ENABLE_HYPOTHETICAL_QUESTIONS', true),
        'questions_per_chunk' => (int) env('AI_QUESTIONS_PER_CHUNK', 4),
        'embedding_cache' => env('AI_EMBEDDING_CACHE', true),
        'question_cache' => env('AI_QUESTION_CACHE', true),
    ],
    
    // Retry configuration
    'retry' => [
        'max_attempts' => (int) env('AI_RETRY_MAX_ATTEMPTS', 3),
        'base_delay_ms' => (int) env('AI_RETRY_BASE_DELAY_MS', 1000),
    ],
];
```

### 3.2 Environment Variables (`.env`)

```bash
# AI Provider Configuration
AI_EMBEDDING_PROVIDER=local-ai
AI_LLM_PROVIDER=local-ai

# local-ai Settings
LOCALAI_URL=http://localhost:8080
LOCALAI_API_KEY=
LOCALAI_EMBEDDING_MODEL=nomic-embed-text-v1.5
LOCALAI_EMBEDDING_DIMENSIONS=768
LOCALAI_LLM_MODEL=Qwen3.5-9B-GGUF

# Legacy LMStudio (kept for backward compatibility)
LMSTUDIO_URL=http://localhost:1234
LMSTUDIO_API_KEY=lmstudio

# Future external providers (commented out until needed)
# OPENAI_API_KEY=sk-...
# ANTHROPIC_API_KEY=sk-ant-...

# Feature Flags
AI_ENABLE_HYPOTHETICAL_QUESTIONS=true
AI_QUESTIONS_PER_CHUNK=4
AI_EMBEDDING_CACHE=true
AI_QUESTION_CACHE=true
```

---

## 4. Service Layer Design

### 4.1 Refactored EmbeddingService

```php
class EmbeddingService
{
    private EmbeddingProviderInterface $provider;
    
    public function __construct(?string $provider = null)
    {
        $this->provider = AiProviderFactory::makeEmbeddingProvider($provider);
    }
    
    public function generateEmbedding(string $text): ?array
    {
        // Delegate to provider
        return $this->provider->embed($text);
    }
    
    public function createEmbeddingForNode(Node $node): ?Embedding
    {
        $vector = $this->provider->embed($node->content);
        // ... storage logic
    }
}
```

### 4.2 New LLMService

```php
class LlmService
{
    private LlmProviderInterface $provider;
    
    public function __construct(?string $provider = null)
    {
        $this->provider = AiProviderFactory::makeLlmProvider($provider);
    }
    
    public function generate(string $prompt, array $options = []): ?string
    {
        return $this->provider->generate($prompt, $options);
    }
    
    public function chat(array $messages, array $options = []): ?string
    {
        return $this->provider->chat($messages, $options);
    }
}
```

### 4.3 New HypotheticalQuestionService

```php
class HypotheticalQuestionService
{
    public function __construct(
        private LlmService $llmService,
    ) {}
    
    public function generateForChunk(string $chunkContent): array
    {
        if (!config('ai.features.hypothetical_questions')) {
            return [];
        }
        
        $prompt = $this->buildPrompt($chunkContent);
        $response = $this->llmService->generate($prompt, [
            'temperature' => 0.7,
            'max_tokens' => 200,
        ]);
        
        return $this->parseQuestions($response);
    }
    
    public function generateForNode(Node $node): array
    {
        return $this->generateForChunk($node->content);
    }
}
```

---

## 5. Ingestion Pipeline Integration

### 5.1 Updated IngestController Flow

```php
// In IngestController::store()

foreach ($chunks as $index => $chunk) {
    // 1. Generate metadata
    $summary = $this->metadataService->generateSummary($chunk);
    $keywords = $this->metadataService->extractKeywords($chunk);
    
    // 2. Generate hypothetical questions (NEW)
    $questions = $this->questionService->generateForChunk($chunk);
    
    $metadata = [
        'summary' => $summary,
        'keywords' => $keywords,
        'hypothetical_questions' => $questions, // NEW
    ];
    
    // 3. Create node
    $node = $this->nodeRepository->create([
        'type' => 'text_chunk',
        'content' => $chunk,
        'metadata' => $metadata,
        // ...
    ]);
    
    // 4. Generate embedding
    $this->embeddingService->createEmbeddingForNode($node);
    
    // ... rest of pipeline
}
```

---

## 6. Migration Strategy

### 6.1 Zero-Downtime Migration

1. **Deploy new code** with both LMStudio and local-ai providers
2. **Update configuration** to use local-ai as default
3. **New documents** use local-ai
4. **Existing embeddings** remain valid (same dimensions)
5. **Optional**: Backfill hypothetical questions for existing chunks

### 6.2 Rollback Plan

If issues arise:
1. Revert config to `AI_EMBEDDING_PROVIDER=lmstudio`
2. Existing embeddings remain compatible
3. No data migration required

---

## 7. Files to Create/Modify

### New Files
1. `app/Contracts/Ai/EmbeddingProviderInterface.php`
2. `app/Contracts/Ai/LlmProviderInterface.php`
3. `app/Services/Ai/AiProviderFactory.php`
4. `app/Services/Ai/Providers/LocalAiEmbeddingProvider.php`
5. `app/Services/Ai/Providers/LocalAiLlmProvider.php`
6. `app/Services/Ai/Providers/OpenAiEmbeddingProvider.php` (stub)
7. `app/Services/Ai/Providers/OpenAiLlmProvider.php` (stub)
8. `app/Services/Ai/Providers/LmStudioEmbeddingProvider.php` (legacy adapter)
9. `app/Services/Ai/LlmService.php`
10. `app/Services/Questions/HypotheticalQuestionService.php`
11. `app/Jobs/GenerateHypotheticalQuestions.php`

### Modified Files
1. `config/ai.php` - restructure for multi-provider
2. `.env.example` - add new environment variables
3. `app/Services/EmbeddingService.php` - refactor to use provider
4. `app/Http/Controllers/Api/IngestController.php` - add question generation
5. `app/Services/Metadata/MetadataService.php` - optionally extend interface

### Migration Files
None required for provider migration (config-only change).
Optional: Migration to add index on `metadata->hypothetical_questions` if needed for search.

---

## 8. Testing Strategy

### Unit Tests
- Provider factory creates correct instances
- Each provider handles errors gracefully
- Question parsing handles various LLM response formats

### Integration Tests
- End-to-end ingestion with question generation
- Provider availability checks
- Fallback behavior when LLM unavailable

### Manual Verification
- Verify local-ai connectivity
- Test embedding dimensions match expected (768)
- Validate question quality from Qwen3.5-9B-GGUF

---

## Appendix A: Provider API Compatibility

### local-ai Endpoints
- Embeddings: `POST /v1/embeddings` (OpenAI-compatible)
- Completions: `POST /v1/completions` or `POST /v1/chat/completions`

### OpenAI Endpoints
- Embeddings: `POST /v1/embeddings`
- Chat: `POST /v1/chat/completions`

### Anthropic Endpoints
- Messages: `POST /v1/messages` (different format, requires adapter)

---

*Document Version: 1.0*  
*Last Updated: 2026-03-10*  
*Author: Architecture Subagent*
