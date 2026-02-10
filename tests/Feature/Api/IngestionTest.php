<?php

use App\Models\Embedding;
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
    $mockEmbeddingService->shouldReceive('createEmbeddingForNode')->andReturnUsing(function ($node) {
        $embedding = new Embedding;
        $embedding->node_id = $node->id;
        $embedding->embedding = array_fill(0, 768, 0.1);
        $embedding->save();

        return $embedding;
    });

    $this->app->instance(EmbeddingService::class, $mockEmbeddingService);
});

describe('Ingestion API', function () {
    it('requires authentication', function () {
        $response = $this->postJson('/api/ingest', [
            'text' => 'Sample text to ingest',
        ]);

        $response->assertStatus(401);
    });

    it('can ingest text content', function () {
        $text = 'This is a sample text for ingestion. It should be chunked and stored properly.';

        $response = $this->actingAs($this->user)
            ->postJson('/api/ingest', [
                'text' => $text,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'node_ids',
                    'chunk_count',
                ],
            ])
            ->assertJson(['success' => true]);
    });

    it('validates required text field', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/ingest', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['text']);
    });

    it('can ingest with tags', function () {
        $text = 'Sample text with tags';

        $response = $this->actingAs($this->user)
            ->postJson('/api/ingest', [
                'text' => $text,
                'tags' => ['php', 'laravel'],
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);
    });

    it('respects chunk size parameter', function () {
        $text = str_repeat('Word. ', 100); // Long text

        $response = $this->actingAs($this->user)
            ->postJson('/api/ingest', [
                'text' => $text,
                'chunk_size' => 50,
            ]);

        $response->assertStatus(201);

        $data = $response->json('data');
        expect($data['chunk_count'])->toBeGreaterThan(1);
    });

    it('validates chunk size limits', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/ingest', [
                'text' => 'Sample text',
                'chunk_size' => 0,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['chunk_size']);
    });
});
