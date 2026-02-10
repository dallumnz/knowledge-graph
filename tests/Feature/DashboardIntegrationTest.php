<?php

use App\Models\Edge;
use App\Models\Embedding;
use App\Models\Node;
use App\Models\User;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

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
    $mockEmbeddingService->shouldReceive('createEmbeddingForNode')
        ->andReturnUsing(function ($node) {
            return Embedding::factory()->create(['node_id' => $node->id]);
        });
    $mockEmbeddingService->shouldReceive('getDimensions')->andReturn(768);
    $mockEmbeddingService->shouldReceive('getModel')->andReturn('test-model');

    $this->app->instance(EmbeddingService::class, $mockEmbeddingService);
});

afterEach(function () {
    Mockery::close();
});

describe('Dashboard Page', function () {
    it('requires authentication to access dashboard', function () {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    });

    it('allows authenticated users to access dashboard', function () {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Welcome back, '.$this->user->name);
    });

    it('displays stats section on dashboard', function () {
        Node::factory()->count(5)->create();
        Edge::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Total Nodes')
            ->assertSee('5')
            ->assertSee('Total Edges')
            ->assertSee('3');
    });

    it('displays search section on dashboard', function () {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Semantic Search')
            ->assertSee('Search your knowledge graph');
    });

    it('displays quick ingest section on dashboard', function () {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Quick Ingest')
            ->assertSee('Title')
            ->assertSee('Content');
    });
});

describe('Dashboard Search', function () {
    it('can search from dashboard', function () {
        // Create a test node
        $node = Node::factory()->create([
            'type' => 'text_chunk',
            'content' => 'Test content for semantic search',
        ]);

        // Mock the vector store to return the node
        $mockVectorStore = Mockery::mock(\App\Services\VectorStore::class);
        $mockVectorStore->shouldReceive('searchSimilar')
            ->andReturn([
                [
                    'node' => $node,
                    'score' => 0.95,
                ],
            ]);
        $this->app->instance(\App\Services\VectorStore::class, $mockVectorStore);

        Livewire::actingAs($this->user)
            ->test('dashboard-search')
            ->set('query', 'test search')
            ->call('search')
            ->assertSee('Test content for semantic search')
            ->assertSee('0.95');
    });

    it('shows validation error for empty search query', function () {
        Livewire::actingAs($this->user)
            ->test('dashboard-search')
            ->set('query', '')
            ->call('search')
            ->assertSee('Please enter a search query');
    });

    it('can clear search results', function () {
        Livewire::actingAs($this->user)
            ->test('dashboard-search')
            ->set('query', 'test query')
            ->call('clear')
            ->assertSet('query', '')
            ->assertSet('hasSearched', false);
    });

    it('shows no results message when search returns empty', function () {
        // Mock the vector store to return empty results
        $mockVectorStore = Mockery::mock(\App\Services\VectorStore::class);
        $mockVectorStore->shouldReceive('searchSimilar')
            ->andReturn([]);
        $this->app->instance(\App\Services\VectorStore::class, $mockVectorStore);

        Livewire::actingAs($this->user)
            ->test('dashboard-search')
            ->set('query', 'nonexistent query')
            ->call('search')
            ->assertSee('No results found');
    });
});

describe('Dashboard Quick Ingest', function () {
    it('can ingest content from dashboard', function () {
        Livewire::actingAs($this->user)
            ->test('quick-ingest')
            ->set('title', 'Test Title')
            ->set('content', 'Test content for quick ingest')
            ->call('ingest')
            ->assertSee('Content ingested successfully');

        // Verify node was created
        expect(Node::count())->toBe(1);
    });

    it('validates title is required', function () {
        Livewire::actingAs($this->user)
            ->test('quick-ingest')
            ->set('title', '')
            ->set('content', 'Test content')
            ->call('ingest')
            ->assertHasErrors(['title']);
    });

    it('validates content is required', function () {
        Livewire::actingAs($this->user)
            ->test('quick-ingest')
            ->set('title', 'Test Title')
            ->set('content', '')
            ->call('ingest')
            ->assertHasErrors(['content']);
    });

    it('clears form after successful ingest', function () {
        Livewire::actingAs($this->user)
            ->test('quick-ingest')
            ->set('title', 'Test Title')
            ->set('content', 'Test content')
            ->call('ingest')
            ->assertSet('title', '')
            ->assertSet('content', '');
    });

    it('dispatches node-created event after ingest', function () {
        Livewire::actingAs($this->user)
            ->test('quick-ingest')
            ->set('title', 'Test Title')
            ->set('content', 'Test content')
            ->call('ingest')
            ->assertDispatched('node-created');
    });

    it('chunks long content into multiple nodes', function () {
        // Create content longer than 500 characters
        $longContent = str_repeat('This is a test sentence. ', 30); // ~750 characters

        Livewire::actingAs($this->user)
            ->test('quick-ingest')
            ->set('title', 'Long Content Test')
            ->set('content', $longContent)
            ->call('ingest')
            ->assertSee('Content ingested successfully');

        // Should create multiple nodes due to chunking
        expect(Node::count())->toBeGreaterThan(1);
    });
});

describe('Dashboard Stats Integration', function () {
    it('updates stats after node creation', function () {
        // Initial state
        Node::factory()->count(3)->create();

        $component = Livewire::actingAs($this->user)
            ->test('dashboard-stats');

        $component->assertSee('3');

        // Create a new node
        Node::factory()->create();

        // Refresh stats
        $component->call('refreshStats');

        $component->assertSee('4');
    });

    it('displays recent activity correctly', function () {
        $node = Node::factory()->create([
            'type' => 'text_chunk',
            'content' => 'Recent activity test content',
        ]);

        Livewire::actingAs($this->user)
            ->test('dashboard-stats')
            ->assertSee('Recent activity test content')
            ->assertSee('text_chunk');
    });
});
