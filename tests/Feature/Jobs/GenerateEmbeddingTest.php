<?php

use App\Jobs\GenerateEmbedding;
use App\Models\Node;
use App\Services\EmbeddingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->node = Node::factory()->create([
        'type' => 'text_chunk',
        'content' => 'Test content for embedding',
    ]);
});

it('dispatches to the embeddings queue', function () {
    Queue::fake();

    GenerateEmbedding::dispatch($this->node);

    Queue::assertPushedOn('embeddings', GenerateEmbedding::class);
});

it('generates embedding for node', function () {
    $embeddingService = mock(EmbeddingService::class);
    $embeddingService->shouldReceive('createEmbeddingForNode')
        ->once()
        ->with($this->node);

    $job = new GenerateEmbedding($this->node);
    $job->handle($embeddingService);
});

it('logs success message on completion', function () {
    Log::spy();

    $embeddingService = mock(EmbeddingService::class);
    $embeddingService->shouldReceive('createEmbeddingForNode');

    $job = new GenerateEmbedding($this->node);
    $job->handle($embeddingService);

    Log::shouldHaveReceived('info')
        ->with('Embedding generated successfully', ['node_id' => $this->node->id]);
});

it('logs error on failure', function () {
    Log::spy();

    $embeddingService = mock(EmbeddingService::class);
    $embeddingService->shouldReceive('createEmbeddingForNode')
        ->andThrow(new Exception('Embedding failed'));

    $job = new GenerateEmbedding($this->node);

    try {
        $job->handle($embeddingService);
    } catch (Exception $e) {
        // Expected
    }

    Log::shouldHaveReceived('error')
        ->with('Failed to generate embedding', \Mockery::subset(['node_id' => $this->node->id]));
});

it('has correct timeout and tries configuration', function () {
    $job = new GenerateEmbedding($this->node);

    expect($job->timeout)->toBe(120);
    expect($job->tries)->toBe(3);
});

it('calls failed method on permanent failure', function () {
    Log::spy();

    $job = new GenerateEmbedding($this->node);
    $exception = new Exception('Permanent failure');

    $job->failed($exception);

    Log::shouldHaveReceived('error')
        ->with('Embedding job failed permanently', \Mockery::subset(['node_id' => $this->node->id]));
});
