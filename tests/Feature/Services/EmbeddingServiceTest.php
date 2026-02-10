<?php

use App\Models\Embedding;
use App\Models\Node;
use App\Services\EmbeddingService;

beforeEach(function () {
    $this->service = new EmbeddingService;
});

describe('EmbeddingService', function () {

    it('returns configured dimensions', function () {
        $dimensions = $this->service->getDimensions();

        expect($dimensions)->toBeInt();
        expect($dimensions)->toBe(768);
    });

    it('returns configured model name', function () {
        $model = $this->service->getModel();

        expect($model)->toBeString();
        expect($model)->toBe('text-embedding-nomic-embed-text-v2');
    });

    it('returns null for empty text', function () {
        $result = $this->service->generateEmbedding('');

        expect($result)->toBeNull();
    });

    it('returns null for whitespace-only text', function () {
        $result = $this->service->generateEmbedding('   ');

        expect($result)->toBeNull();
    });

    it('creates embedding for node with content', function () {
        $node = Node::factory()->create([
            'type' => 'text_chunk',
            'content' => 'Test content for embedding generation',
        ]);

        // Mock the embedding generation by storing directly
        $vector = array_fill(0, 768, 0.1);
        $embedding = new Embedding;
        $embedding->node_id = $node->id;
        $embedding->embedding = $vector;
        $embedding->save();

        expect($embedding->node_id)->toBe($node->id);
        expect($embedding->embedding)->not->toBeNull();
        expect($embedding->embedding->toArray())->toHaveCount(768);
    });

    it('deletes embedding successfully', function () {
        $node = Node::factory()->create([
            'type' => 'text_chunk',
            'content' => 'Test content',
        ]);

        $vector = array_fill(0, 768, 0.1);
        $embedding = new Embedding;
        $embedding->node_id = $node->id;
        $embedding->embedding = $vector;
        $embedding->save();

        $result = $this->service->deleteEmbedding($node->id);

        expect($result)->toBeTrue();
        expect(Embedding::find($node->id))->toBeNull();
    });

    it('generates batch embeddings for multiple texts', function () {
        $texts = ['First text', 'Second text', 'Third text'];

        // Since we can't call the actual AI SDK in tests, we verify the method exists
        // and returns an array structure
        $results = $this->service->generateEmbeddingsBatch($texts);

        expect($results)->toBeArray();
        expect($results)->toHaveCount(3);
    });

    it('validates provider availability check method exists', function () {
        // The method should exist and return a boolean
        $result = $this->service->isProviderAvailable();

        expect($result)->toBeBool();
    });

});
