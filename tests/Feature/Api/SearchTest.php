<?php

use App\Models\Edge;
use App\Models\Embedding;
use App\Models\Node;
use App\Models\User;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

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

    $this->app->instance(EmbeddingService::class, $mockEmbeddingService);
});

describe('Search API', function () {
    it('requires authentication for search', function () {
        $response = $this->getJson('/api/search?q=test');

        $response->assertStatus(401);
    });

    it('validates search query', function () {
        $response = $this->actingAs($this->user)
            ->getJson('/api/search');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    });

    it('can search with query parameter', function () {
        // Create a test node
        $node = Node::factory()->create([
            'type' => 'text_chunk',
            'content' => 'Test content for search',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/search?q=test');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'query',
                    'results',
                    'count',
                ],
            ]);
    });

    it('respects limit parameter', function () {
        // Create multiple nodes
        Node::factory()->count(20)->create(['type' => 'text_chunk']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/search?q=content&limit=5');

        $response->assertStatus(200);
        $data = $response->json('data');
        expect($data['count'])->toBeLessThanOrEqual(5);
    });

    it('validates limit parameter range', function () {
        $response = $this->actingAs($this->user)
            ->getJson('/api/search?q=test&limit=0');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);
    });

    it('can get node details by id', function () {
        $node = Node::factory()->create([
            'type' => 'text_chunk',
            'content' => 'Test node content',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/nodes/{$node->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
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
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $node->id,
                    'type' => 'text_chunk',
                    'content' => 'Test node content',
                ],
            ]);
    });

    it('returns 404 for non-existent node', function () {
        $response = $this->actingAs($this->user)
            ->getJson('/api/nodes/99999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Node not found',
            ]);
    });

    it('can perform text search', function () {
        Node::factory()->create([
            'type' => 'text_chunk',
            'content' => 'This contains the search keyword',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/search/text?q=keyword');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'query',
                    'results',
                    'count',
                ],
            ]);
    });

    it('validates text search query', function () {
        $response = $this->actingAs($this->user)
            ->getJson('/api/search/text');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    });
});

describe('Node API', function () {
    it('returns node with edges', function () {
        $node1 = Node::factory()->create(['type' => 'text_chunk']);
        $node2 = Node::factory()->create(['type' => 'text_chunk']);

        Edge::factory()->create([
            'source_id' => $node1->id,
            'target_id' => $node2->id,
            'relation' => 'related_to',
            'weight' => 0.8,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/nodes/{$node1->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.outgoing_edges.0.relation', 'related_to')
            ->assertJsonPath('data.outgoing_edges.0.target_id', $node2->id);
    });

    it('indicates if node has embedding', function () {
        $node = Node::factory()->create(['type' => 'text_chunk']);

        // Without embedding
        $response = $this->actingAs($this->user)
            ->getJson("/api/nodes/{$node->id}");

        $response->assertJsonPath('data.has_embedding', false);

        // With embedding
        Embedding::factory()->create(['node_id' => $node->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/nodes/{$node->id}");

        $response->assertJsonPath('data.has_embedding', true);
    });
});
