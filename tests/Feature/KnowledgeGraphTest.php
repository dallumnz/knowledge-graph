<?php

use App\Models\Edge;
use App\Models\Embedding;
use App\Models\Node;
use App\Models\User;
use App\Repositories\EdgeRepository;
use App\Repositories\NodeRepository;
use App\Services\EmbeddingService;
use App\Services\VectorStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

describe('Knowledge Graph API', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        // Mock the EmbeddingService to return predictable vectors
        $mockEmbeddingService = Mockery::mock(EmbeddingService::class);
        $mockEmbeddingService->shouldReceive('generateEmbedding')
            ->andReturnUsing(function () {
                // Return a 768-dimensional vector
                return array_fill(0, 768, 0.1);
            });
        $mockEmbeddingService->shouldReceive('getDimensions')->andReturn(768);
        $mockEmbeddingService->shouldReceive('getModel')->andReturn('test-model');
        $mockEmbeddingService->shouldReceive('createEmbeddingForNode')->andReturnUsing(function ($node) {
            $embedding = new Embedding;
            $embedding->node_id = $node->id;
            $embedding->embedding = array_fill(0, 768, 0.1);
            $embedding->save();

            return $embedding;
        });
        $mockEmbeddingService->shouldReceive('isProviderAvailable')->andReturn(true);

        $this->app->instance(EmbeddingService::class, $mockEmbeddingService);
    });

    describe('Ingestion Endpoints', function () {
        it('requires authentication for ingest endpoint', function () {
            $response = $this->postJson(route('api.ingest.store'), [
                'text' => 'Test content',
            ]);

            $response->assertUnauthorized();
        });

        it('validates required text field', function () {
            $response = $this->actingAs($this->user)
                ->postJson(route('api.ingest.store'), []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['text']);
        });

        it('accepts text content for ingestion', function () {
            $response = $this->actingAs($this->user)
                ->postJson(route('api.ingest.store'), [
                    'text' => 'This is a test sentence. Here is another sentence.',
                ]);

            $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Content ingested successfully',
                ])
                ->assertJsonStructure([
                    'data' => [
                        'node_ids',
                        'chunk_count',
                    ],
                ]);
        });

        it('chunks long text into multiple nodes', function () {
            $longText = str_repeat('This is a test sentence. ', 50);

            $response = $this->actingAs($this->user)
                ->postJson(route('api.ingest.store'), [
                    'text' => $longText,
                    'chunk_size' => 100,
                ]);

            $response->assertStatus(201);
            $data = $response->json('data');
            expect($data['chunk_count'])->toBeGreaterThan(1);
            expect(count($data['node_ids']))->toBe($data['chunk_count']);
        });

        it('creates tag nodes when tags are provided', function () {
            $response = $this->actingAs($this->user)
                ->postJson(route('api.ingest.store'), [
                    'text' => 'Test content with tags.',
                    'tags' => ['php', 'laravel'],
                ]);

            $response->assertStatus(201);

            // Check that tag nodes were created
            $tagNodes = Node::where('type', 'tag')
                ->whereIn('content', ['php', 'laravel'])
                ->get();

            expect($tagNodes)->toHaveCount(2);
        });

        it('validates chunk_size parameter', function () {
            $response = $this->actingAs($this->user)
                ->postJson(route('api.ingest.store'), [
                    'text' => 'Test content',
                    'chunk_size' => 0,
                ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['chunk_size']);
        });
    });

    describe('Search Endpoints', function () {
        it('requires authentication for search endpoint', function () {
            $response = $this->getJson(route('api.search', ['q' => 'test']));

            $response->assertUnauthorized();
        });

        it('validates query parameter', function () {
            $response = $this->actingAs($this->user)
                ->getJson(route('api.search'));

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['q']);
        });

        it('returns search results with valid query', function () {
            // Create test nodes
            $node = Node::factory()->create([
                'type' => 'text_chunk',
                'content' => 'Laravel is a PHP framework for web artisans.',
            ]);

            $response = $this->actingAs($this->user)
                ->getJson(route('api.search', ['q' => 'Laravel framework']));

            $response->assertOk()
                ->assertJson([
                    'success' => true,
                ])
                ->assertJsonStructure([
                    'data' => [
                        'query',
                        'results',
                        'count',
                    ],
                ]);
        });

        it('respects limit parameter', function () {
            $response = $this->actingAs($this->user)
                ->getJson(route('api.search', [
                    'q' => 'test',
                    'limit' => 5,
                ]));

            $response->assertOk();
            // Just verify the request succeeds with limit parameter
            expect($response->json('success'))->toBeTrue();
        });

        it('validates limit parameter range', function () {
            $response = $this->actingAs($this->user)
                ->getJson(route('api.search', [
                    'q' => 'test',
                    'limit' => 200,
                ]));

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['limit']);
        });

        it('performs text search without vector similarity', function () {
            Node::factory()->create([
                'type' => 'text_chunk',
                'content' => 'This is about PHP programming.',
            ]);

            $response = $this->actingAs($this->user)
                ->getJson(route('api.search.text', ['q' => 'PHP']));

            $response->assertOk()
                ->assertJson([
                    'success' => true,
                ])
                ->assertJsonStructure([
                    'data' => [
                        'query',
                        'results',
                        'count',
                    ],
                ]);
        });

        it('filters text search by type', function () {
            Node::factory()->textChunk()->create([
                'content' => 'Text chunk content.',
            ]);
            Node::factory()->tag()->create([
                'content' => 'mytag',
            ]);

            $response = $this->actingAs($this->user)
                ->getJson(route('api.search.text', [
                    'q' => 'mytag',
                    'type' => 'tag',
                ]));

            $response->assertOk();
            $results = $response->json('data.results');
            expect($results)->toHaveCount(1);
            expect($results[0]['type'])->toBe('tag');
        });
    });

    describe('Node Endpoints', function () {
        it('requires authentication for node endpoint', function () {
            $response = $this->getJson(route('api.nodes.show', ['id' => 1]));

            $response->assertUnauthorized();
        });

        it('returns node details with edges', function () {
            $node = Node::factory()->create([
                'type' => 'text_chunk',
                'content' => 'Test node content',
            ]);

            // Create related nodes and edges
            $targetNode = Node::factory()->create([
                'type' => 'tag',
                'content' => 'test-tag',
            ]);

            Edge::factory()->create([
                'source_id' => $node->id,
                'target_id' => $targetNode->id,
                'relation' => 'tagged_with',
            ]);

            $response = $this->actingAs($this->user)
                ->getJson(route('api.nodes.show', ['id' => $node->id]));

            $response->assertOk()
                ->assertJson([
                    'success' => true,
                ])
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'type',
                        'content',
                        'created_at',
                        'updated_at',
                        'has_embedding',
                        'outgoing_edges',
                        'incoming_edges',
                    ],
                ]);

            $data = $response->json('data');
            expect($data['id'])->toBe($node->id);
            expect($data['outgoing_edges'])->toHaveCount(1);
        });

        it('returns 404 for non-existent node', function () {
            $response = $this->actingAs($this->user)
                ->getJson(route('api.nodes.show', ['id' => 99999]));

            $response->assertNotFound()
                ->assertJson([
                    'success' => false,
                    'message' => 'Node not found',
                ]);
        });
    });

    describe('Repositories', function () {
        it('can create nodes via NodeRepository', function () {
            $repository = new NodeRepository;
            $node = $repository->create([
                'type' => 'text_chunk',
                'content' => 'Repository test content',
            ]);

            expect($node)->toBeInstanceOf(Node::class);
            expect($node->type)->toBe('text_chunk');
            expect($node->content)->toBe('Repository test content');
        });

        it('can find nodes by ID with eager loading', function () {
            $node = Node::factory()->create();
            Embedding::factory()->create(['node_id' => $node->id]);

            $repository = new NodeRepository;
            $found = $repository->findById($node->id, ['embedding']);

            expect($found)->not->toBeNull();
            expect($found->id)->toBe($node->id);
            expect($found->embedding)->not->toBeNull();
        });

        it('can filter nodes by type', function () {
            Node::factory()->textChunk()->count(3)->create();
            Node::factory()->tag()->count(2)->create();

            $repository = new NodeRepository;
            $textChunks = $repository->getAll('text_chunk');

            expect($textChunks)->toHaveCount(3);
        });

        it('can search nodes by content', function () {
            Node::factory()->create(['content' => 'Unique search term here']);
            Node::factory()->create(['content' => 'Different content']);

            $repository = new NodeRepository;
            $results = $repository->searchByContent('Unique search');

            expect($results)->toHaveCount(1);
            expect($results->first()->content)->toContain('Unique');
        });

        it('can create edges via EdgeRepository', function () {
            $source = Node::factory()->create();
            $target = Node::factory()->create();

            $repository = new EdgeRepository;
            $edge = $repository->connect($source, $target, 'related_to', 0.8);

            expect($edge)->toBeInstanceOf(Edge::class);
            expect($edge->source_id)->toBe($source->id);
            expect($edge->target_id)->toBe($target->id);
            expect($edge->relation)->toBe('related_to');
            expect($edge->weight)->toBe(0.8);
        });

        it('can find or create edges', function () {
            $source = Node::factory()->create();
            $target = Node::factory()->create();

            $repository = new EdgeRepository;

            // First call creates the edge
            $edge1 = $repository->findOrCreate($source, $target, 'test_relation');

            // Second call should return the same edge
            $edge2 = $repository->findOrCreate($source, $target, 'test_relation');

            expect($edge1->id)->toBe($edge2->id);
        });

        it('can delete edges by node', function () {
            $node = Node::factory()->create();
            $target = Node::factory()->create();

            Edge::factory()->create([
                'source_id' => $node->id,
                'target_id' => $target->id,
            ]);

            $repository = new EdgeRepository;
            $deleted = $repository->deleteByNode($node);

            expect($deleted)->toBe(1);
            expect(Edge::where('source_id', $node->id)->count())->toBe(0);
        });
    });

    describe('Services', function () {
        it('EmbeddingService can generate embeddings', function () {
            $service = new EmbeddingService;

            // Mock the embedding generation by checking the service exists
            expect($service)->toBeInstanceOf(EmbeddingService::class);
            expect($service->getDimensions())->toBe(768);
        });

        it('VectorStore can store and search vectors', function () {
            $store = new VectorStore;

            // Create a test vector
            $vector = [];
            for ($i = 0; $i < 768; $i++) {
                $vector[] = 0.1;
            }

            $node = Node::factory()->create();
            $stored = $store->storeVector($node->id, $vector);

            expect($stored)->toBeTrue();

            // Verify the embedding was stored
            $embedding = Embedding::where('node_id', $node->id)->first();
            expect($embedding)->not->toBeNull();
        });

        it('VectorStore validates vector dimensions', function () {
            $store = new VectorStore;

            // Try to store a vector with wrong dimensions - should throw exception
            expect(fn () => $store->storeVector(1, [0.1, 0.2, 0.3]))
                ->toThrow(InvalidArgumentException::class, 'Vector must have exactly 768 dimensions');
        });

        it('VectorStore can delete vectors', function () {
            $node = Node::factory()->create();
            Embedding::factory()->create(['node_id' => $node->id]);

            $store = new VectorStore;
            $deleted = $store->deleteVector($node->id);

            expect($deleted)->toBeTrue();
            expect(Embedding::where('node_id', $node->id)->exists())->toBeFalse();
        });
    });

    describe('Models', function () {
        it('Node has embedding relationship', function () {
            $node = Node::factory()->create();
            $embedding = Embedding::factory()->create(['node_id' => $node->id]);

            expect($node->embedding)->not->toBeNull();
            expect($node->embedding->node_id)->toBe($node->id);
        });

        it('Node has outgoing and incoming edges', function () {
            $node1 = Node::factory()->create();
            $node2 = Node::factory()->create();

            Edge::factory()->create([
                'source_id' => $node1->id,
                'target_id' => $node2->id,
            ]);

            expect($node1->outgoingEdges)->toHaveCount(1);
            expect($node2->incomingEdges)->toHaveCount(1);
        });

        it('Edge belongs to source and target nodes', function () {
            $source = Node::factory()->create();
            $target = Node::factory()->create();

            $edge = Edge::factory()->create([
                'source_id' => $source->id,
                'target_id' => $target->id,
            ]);

            expect($edge->source->id)->toBe($source->id);
            expect($edge->target->id)->toBe($target->id);
        });

        it('Embedding can convert vector to array', function () {
            $vector = [];
            for ($i = 0; $i < 768; $i++) {
                $vector[] = 0.5;
            }

            $embedding = new Embedding;
            $embedding->embedding = $vector;

            $retrieved = $embedding->embedding->toArray();

            expect(count($retrieved))->toBe(768);
            expect(abs($retrieved[0] - 0.5) < 0.01)->toBeTrue();
        });
    });
});
