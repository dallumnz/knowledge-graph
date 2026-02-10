<?php

use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('User Token Management', function () {

    it('requires authentication to access tokens page', function () {
        $response = $this->get(route('user.tokens'));

        $response->assertRedirect(route('login'));
    });

    it('allows authenticated users to access tokens page', function () {
        $response = $this->actingAs($this->user)
            ->get(route('user.tokens'));

        $response->assertStatus(200);
        $response->assertViewIs('user.tokens');
    });

    it('renders token manager livewire component', function () {
        Livewire::actingAs($this->user)
            ->test('user.token-manager')
            ->assertStatus(200);
    });

    it('displays empty state when no tokens exist', function () {
        Livewire::actingAs($this->user)
            ->test('user.token-manager')
            ->assertSee('No API tokens found');
    });

    it('can create a new token via form', function () {
        $component = Livewire::actingAs($this->user)
            ->test('user.token-manager')
            ->call('openCreateModal')
            ->set('tokenName', 'Test Token')
            ->call('createToken');

        // Token should be set and not empty
        $component->assertSet('plainTextToken', fn ($token) => ! empty($token) && is_string($token));

        // Assert token exists in database
        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'Test Token',
            'tokenable_id' => $this->user->id,
            'tokenable_type' => User::class,
        ]);

        expect($this->user->tokens()->count())->toBe(1);
        expect($this->user->tokens()->first()->name)->toBe('Test Token');
    });

    it('validates token name is required', function () {
        Livewire::actingAs($this->user)
            ->test('user.token-manager')
            ->call('openCreateModal')
            ->set('tokenName', '')
            ->call('createToken')
            ->assertHasErrors(['tokenName' => 'required']);
    });

    it('lists tokens in table with name created_at and last_used_at', function () {
        // Create a token
        $token = $this->user->createToken('Production API');
        $accessToken = $token->accessToken;
        $accessToken->last_used_at = now()->subHours(2);
        $accessToken->save();

        Livewire::actingAs($this->user)
            ->test('user.token-manager')
            ->assertSee('Production API')
            ->assertSee($accessToken->created_at->format('M d, Y H:i'))
            ->assertSee('2 hours ago');
    });

    it('shows never used for tokens without last_used_at', function () {
        $this->user->createToken('Unused Token');

        Livewire::actingAs($this->user)
            ->test('user.token-manager')
            ->assertSee('Unused Token')
            ->assertSee('Never');
    });

    it('can revoke a token', function () {
        // Create a token first
        $token = $this->user->createToken('Token To Revoke');
        $tokenId = $token->accessToken->id;

        // Assert token exists
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $tokenId,
            'name' => 'Token To Revoke',
        ]);

        Livewire::actingAs($this->user)
            ->test('user.token-manager')
            ->call('confirmDelete', $tokenId)
            ->call('deleteToken');

        // Assert token was deleted from database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenId,
        ]);

        expect($this->user->tokens()->count())->toBe(0);
    });

    it('closes create modal and resets form after creation', function () {
        $component = Livewire::actingAs($this->user)
            ->test('user.token-manager')
            ->call('openCreateModal')
            ->set('tokenName', 'Test Token')
            ->call('createToken')
            ->call('closeCreateModal');

        $component->assertSet('showCreateModal', false);
        $component->assertSet('tokenName', '');
    });

    it('closes delete modal and resets token to delete', function () {
        $component = Livewire::actingAs($this->user)
            ->test('user.token-manager')
            ->set('tokenToDelete', 123)
            ->set('showDeleteModal', true)
            ->call('closeDeleteModal');

        $component->assertSet('showDeleteModal', false);
        $component->assertSet('tokenToDelete', null);
    });

    it('dispatches event when token is created', function () {
        Livewire::actingAs($this->user)
            ->test('user.token-manager')
            ->call('openCreateModal')
            ->set('tokenName', 'Event Test Token')
            ->call('createToken')
            ->assertDispatched('token-created');
    });

    it('dispatches event when token is deleted', function () {
        $token = $this->user->createToken('Delete Event Token');

        Livewire::actingAs($this->user)
            ->test('user.token-manager')
            ->call('confirmDelete', $token->accessToken->id)
            ->call('deleteToken')
            ->assertDispatched('token-deleted');
    });
});
