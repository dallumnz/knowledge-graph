# TASK: Production RAG System (Agentic Architecture)

**Project:** knowledge-graph  
**Date:** 2026-03-10  
**Status:** Ready for Architecture Phase  
**Source:** Prod RAG Notes (video transcript)

---

## Objective

Transform the knowledge-graph into a **production-ready agentic RAG system** that handles messy data, complex queries, and prevents hallucinations through multi-layered validation.

**Key Insight:** Standard RAG fails because poor context → confident hallucinations. This system preserves structure, validates quality, and reasons before generating.

---

## Core Architecture (5 Layers)

### Layer 1: Data Ingestion & Restructuring
**Before embedding - maintain semantic meaning**

- **Structure-Aware Parsing:** Identify headings, paragraphs, tables, code blocks (not raw text streams)
- **Smart Chunking:** Natural boundaries, not fixed tokens
  - Sweet spot: 256-512 tokens with overlap
  - Keep tables whole, heading with content
- **Metadata Enrichment:**
  - Summaries and keywords
  - **Hypothetical Questions:** Generate Qs the chunk could answer (improves query matching)

**Current State:** Phases 1-4 done (Metadata extraction, Smart Chunking, Document Restructuring, Keyword Extraction)
**Gap:** Hypothetical questions generation

---

### Layer 2: Storage (Already Built)
**Hybrid Vector + Relational**

✅ **PostgreSQL + pgvector** (already implemented)
- Embeddings for semantic search
- Relational data for filtering/joining
- Document → Node → Chunk relationships

**Features to leverage:**
- Filtering by date, source, document type
- Joins across related chunks
- HNSW index for fast similarity search

---

### Layer 3: Query Processing & Reasoning
**Non-linear query path with planning**

- **Hybrid Search:** Vector (semantic) + Keyword (exact matches for codes/names)
- **Re-ranking:** Cross-encoder or LLM-based relevance scoring
- **Reasoning Engine (Planner):** Break complex queries into steps
  - What info is needed?
  - Which tools to use?
  - Order of operations?
- **Multi-Agent Coordination:**
  - Specialized agents for sub-tasks (financial data, calculations, summaries)
  - Agents coordinate for multi-step problems

**Gap:** Planner, agent coordination, re-ranking

---

### Layer 4: Validation & Security Nodes
**Gatekeeping before response**

- **Gatekeeper:** Does this actually answer the user's question?
- **Auditor:** Is content grounded in retrieved context? (anti-hallucination)
- **Strategist:** Does recommendation make business sense?
- **Red Team / Stress Testing:**
  - Prompt injection attempts
  - Biased queries
  - Information evasion
  - Test before deployment

**Gap:** All validation nodes need implementation

---

### Layer 5: Evaluation Framework
**Continuous measurement**

- **Qualitative:** LLM judges assess faithfulness, relevance, depth
- **Quantitative:** Retrieval precision/recall, answer accuracy
- **Performance:** Latency, token costs, throughput
- **Feedback Loop:** User thumbs up/down, query logs

**Gap:** Evaluation pipeline, metrics collection

---

## Complete Data Flow

```
┌─────────────────────────────────────────────────────────────────┐
│  USER QUERY                                                     │
└──────────────────────┬──────────────────────────────────────────┘
                       ↓
┌─────────────────────────────────────────────────────────────────┐
│  REASONING ENGINE (Planner)                                     │
│  • Decompose complex query                                      │
│  • Determine required information                               │
│  • Select tools/agents                                          │
└──────────────────────┬──────────────────────────────────────────┘
                       ↓
┌─────────────────────────────────────────────────────────────────┐
│  RETRIEVAL (Hybrid + Multi-Agent)                               │
│  • Vector search (semantic similarity)                          │
│  • Keyword search (exact matches)                               │
│  • Re-ranking for relevance                                     │
│  • Multi-agent coordination for complex queries                 │
└──────────────────────┬──────────────────────────────────────────┘
                       ↓
┌─────────────────────────────────────────────────────────────────┐
│  VALIDATION NODES                                               │
│  • Gatekeeper: Answers the question?                            │
│  • Auditor: Grounded in context? (anti-hallucination)           │
│  • Strategist: Makes business sense?                            │
└──────────────────────┬──────────────────────────────────────────┘
                       ↓
┌─────────────────────────────────────────────────────────────────┐
│  OUTPUT (Only if validated)                                     │
│  • Generated response                                           │
│  • Citations to source chunks                                   │
│  • Confidence score                                             │
└──────────────────────┬──────────────────────────────────────────┘
                       ↓
┌─────────────────────────────────────────────────────────────────┐
│  FEEDBACK LOOP                                                  │
│  • User feedback (👍/👎)                                        │
│  • Evaluation metrics                                           │
│  • Red team findings                                            │
│  • Continuous improvement                                       │
└─────────────────────────────────────────────────────────────────┘
```

---

## Implementation Phases

### Phase 1: Enhanced Ingestion (Layer 1 completion)
- [ ] Hypothetical question generation for chunks
- [ ] Structure-aware parsing improvements
- [ ] Metadata enrichment pipeline

### Phase 2: Advanced Retrieval (Layer 3)
- [ ] Hybrid search (vector + keyword)
- [ ] Re-ranking service
- [ ] Query planner / reasoning engine
- [ ] Multi-agent coordination framework

### Phase 3: Validation Layer (Layer 4)
- [ ] Gatekeeper node
- [ ] Auditor (hallucination detection)
- [ ] Strategist (business context)
- [ ] Red team testing framework

### Phase 4: Evaluation & Monitoring (Layer 5)
- [ ] Evaluation metrics pipeline
- [ ] User feedback collection
- [ ] Performance monitoring
- [ ] Continuous improvement loop

### Phase 5: Production Hardening
- [ ] Caching layers
- [ ] Rate limiting
- [ ] Circuit breakers
- [ ] Load testing
- [ ] Documentation

---

## Current System Inventory

**✅ Existing (Don't rebuild):**
- PostgreSQL + pgvector storage
- Document/Node/Edge/Embedding models
- Chunking with metadata
- Basic semantic search API
- LMStudio embedding integration
- Queued processing (Laravel jobs)
- Web UI for ingestion/search
- 181 tests passing

**🔨 Needed (Build new):**
- Hypothetical question generation
- Hybrid search implementation
- Re-ranking service
- Query planner/reasoning engine
- Multi-agent framework
- Validation nodes (3x)
- Evaluation pipeline
- Red team testing

---

## Questions for Dallum

1. **Priority:** Which phase first? (My recommendation: Phase 1 → 2 → 3)

2. **LLM for reasoning/validation:** 
   - Use LMStudio (local)?
   - Add external API (OpenAI/Anthropic)?
   - Hybrid approach?

3. **Multi-agent coordination:**
   - Should this use the Agent Agency workflow we just set up?
   - Or internal Laravel-based agents?

4. **Scope for MVP:**
   - Full 5-phase vision?
   - Cut validation nodes for MVP?
   - Start with hybrid search only?

---

## Success Criteria

**MVP (Phase 1-2):**
- Hypothetical questions improve retrieval accuracy
- Hybrid search outperforms vector-only
- Query planner handles complex multi-part questions
- Citations link back to source chunks

**Production (All phases):**
- <500ms response time for standard queries
- <5% hallucination rate (Auditor catches them)
- User feedback loop operational
- Red team identifies no critical vulnerabilities
- Documentation enables handoff to other developers

---

## Next Step

**Spawn Senior-Architect** to design Phase 1 implementation (Enhanced Ingestion with hypothetical questions).

Ready to proceed?

---

*Based on: Prod RAG Notes.md (video transcript)*
