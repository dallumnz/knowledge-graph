<?php

use App\Models\Edge;
use App\Models\Embedding;
use App\Models\Node;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('renders successfully', function () {
    Livewire::actingAs($this->user)
        ->test('dashboard-stats')
        ->assertStatus(200);
});

it('displays correct node count', function () {
    Node::factory()->count(5)->create();

    Livewire::actingAs($this->user)
        ->test('dashboard-stats')
        ->assertSee('5')
        ->assertSee('Total Nodes');
});

it('displays correct edge count', function () {
    Edge::factory()->count(3)->create();

    Livewire::actingAs($this->user)
        ->test('dashboard-stats')
        ->assertSee('3')
        ->assertSee('Total Edges');
});

it('displays correct embedding count', function () {
    Embedding::factory()->count(2)->create();

    Livewire::actingAs($this->user)
        ->test('dashboard-stats')
        ->assertSee('2')
        ->assertSee('Embeddings');
});

it('displays node type distribution', function () {
    Node::factory()->count(3)->create(['type' => 'text_chunk']);
    Node::factory()->count(2)->create(['type' => 'tag']);

    Livewire::actingAs($this->user)
        ->test('dashboard-stats')
        ->assertSee('text_chunk')
        ->assertSee('tag');
});

it('displays recent nodes', function () {
    $node = Node::factory()->create([
        'type' => 'text_chunk',
        'content' => 'Recent test content',
    ]);

    Livewire::actingAs($this->user)
        ->test('dashboard-stats')
        ->assertSee('text_chunk')
        ->assertSee('Recent test content');
});

it('shows embedding indicator for nodes with embeddings', function () {
    $node = Node::factory()->create(['type' => 'text_chunk']);
    Embedding::factory()->create(['node_id' => $node->id]);

    $component = Livewire::actingAs($this->user)
        ->test('dashboard-stats');

    // Check that the component has nodes with embeddings in the data
    $recentNodes = $component->get('recentNodes');
    expect($recentNodes)->toHaveCount(1);
    expect($recentNodes[0]['has_embedding'])->toBeTrue();
});

it('can refresh stats', function () {
    $component = Livewire::actingAs($this->user)
        ->test('dashboard-stats');

    // Create a node after initial load
    Node::factory()->create();

    $component->call('refreshStats');

    $component->assertSee('1');
});

it('displays empty state when no nodes exist', function () {
    Livewire::actingAs($this->user)
        ->test('dashboard-stats')
        ->assertSee('No nodes yet');
});

it('displays empty state for recent nodes when none exist', function () {
    Livewire::actingAs($this->user)
        ->test('dashboard-stats')
        ->assertSee('No recent nodes');
});

it('formats numbers correctly', function () {
    Node::factory()->count(1000)->create();

    Livewire::actingAs($this->user)
        ->test('dashboard-stats')
        ->assertSee('1,000');
});
