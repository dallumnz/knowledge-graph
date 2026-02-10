<?php

use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('renders successfully', function () {
    Livewire::actingAs($this->user)
        ->test('token-manager')
        ->assertStatus(200);
});

it('displays empty state when no tokens exist', function () {
    Livewire::actingAs($this->user)
        ->test('token-manager')
        ->assertSee('No API tokens found');
});

it('can create a new token', function () {
    $component = Livewire::actingAs($this->user)
        ->test('token-manager')
        ->call('openCreateModal')
        ->set('tokenName', 'Test Token')
        ->call('createToken');

    // Token should be set and not empty
    $component->assertSet('plainTextToken', fn ($token) => ! empty($token) && is_string($token));

    expect($this->user->tokens()->count())->toBe(1);
    expect($this->user->tokens()->first()->name)->toBe('Test Token');
});

it('validates token name is required', function () {
    Livewire::actingAs($this->user)
        ->test('token-manager')
        ->call('openCreateModal')
        ->set('tokenName', '')
        ->call('createToken')
        ->assertHasErrors(['tokenName' => 'required']);
});

it('can revoke a token', function () {
    // Create a token first
    $token = $this->user->createToken('Test Token');

    Livewire::actingAs($this->user)
        ->test('token-manager')
        ->call('confirmDelete', $token->accessToken->id)
        ->call('deleteToken');

    expect($this->user->tokens()->count())->toBe(0);
});

it('can search tokens by name', function () {
    $this->user->createToken('Production API');
    $this->user->createToken('Development API');

    Livewire::actingAs($this->user)
        ->test('token-manager')
        ->set('search', 'Production')
        ->assertSee('Production API')
        ->assertDontSee('Development API');
});

it('displays token last used time', function () {
    $token = $this->user->createToken('Test Token');
    $accessToken = $token->accessToken;
    $accessToken->last_used_at = now();
    $accessToken->save();

    $component = Livewire::actingAs($this->user)
        ->test('token-manager');

    // Check that tokens are loaded with last_used_at
    $tokens = $component->get('tokens');
    expect($tokens)->toHaveCount(1);
    expect($tokens->first()->last_used_at)->not->toBeNull();
});

it('displays never used for unused tokens', function () {
    $this->user->createToken('Test Token');

    Livewire::actingAs($this->user)
        ->test('token-manager')
        ->assertSee('Test Token')
        ->assertSee('Never');
});

it('closes create modal and resets form', function () {
    $component = Livewire::actingAs($this->user)
        ->test('token-manager')
        ->call('openCreateModal')
        ->set('tokenName', 'Test')
        ->call('closeCreateModal');

    $component->assertSet('showCreateModal', false);
    $component->assertSet('tokenName', '');
});

it('closes delete modal and resets token to delete', function () {
    $component = Livewire::actingAs($this->user)
        ->test('token-manager')
        ->set('tokenToDelete', 123)
        ->set('showDeleteModal', true)
        ->call('closeDeleteModal');

    $component->assertSet('showDeleteModal', false);
    $component->assertSet('tokenToDelete', null);
});
