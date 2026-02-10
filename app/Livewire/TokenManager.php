<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Component;

class TokenManager extends Component
{
    public string $tokenName = '';

    public ?string $plainTextToken = null;

    public bool $showCreateModal = false;

    public bool $showDeleteModal = false;

    public ?int $tokenToDelete = null;

    public string $search = '';

    /**
     * Validation rules
     *
     * @return array<string, string>
     */
    protected function rules(): array
    {
        return [
            'tokenName' => 'required|string|min:1|max:255',
        ];
    }

    /**
     * Open the create token modal
     */
    public function openCreateModal(): void
    {
        $this->reset(['tokenName', 'plainTextToken']);
        $this->showCreateModal = true;
    }

    /**
     * Close the create token modal
     */
    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->reset(['tokenName', 'plainTextToken']);
    }

    /**
     * Create a new API token
     */
    public function createToken(): void
    {
        $this->validate();

        $user = Auth::user();

        if ($user === null) {
            return;
        }

        // Revoke existing tokens with the same name
        $user->tokens()->where('name', $this->tokenName)->delete();

        // Create new token
        $token = $user->createToken($this->tokenName);
        $this->plainTextToken = $token->plainTextToken;

        $this->dispatch('token-created');
    }

    /**
     * Confirm token deletion
     */
    public function confirmDelete(int $tokenId): void
    {
        $this->tokenToDelete = $tokenId;
        $this->showDeleteModal = true;
    }

    /**
     * Close the delete modal
     */
    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->tokenToDelete = null;
    }

    /**
     * Delete the confirmed token
     */
    public function deleteToken(): void
    {
        if ($this->tokenToDelete === null) {
            return;
        }

        $user = Auth::user();

        if ($user === null) {
            return;
        }

        $token = $user->tokens()->find($this->tokenToDelete);

        if ($token !== null) {
            $token->delete();
        }

        $this->closeDeleteModal();
        $this->dispatch('token-deleted');
    }

    /**
     * Copy token to clipboard
     */
    public function copyToken(): void
    {
        $this->dispatch('copy-to-clipboard', text: $this->plainTextToken);
    }

    /**
     * Get the user's tokens
     *
     * @return \Illuminate\Support\Collection<int, PersonalAccessToken>
     */
    public function getTokensProperty()
    {
        $user = Auth::user();

        if ($user === null) {
            return collect();
        }

        $query = $user->tokens();

        if ($this->search !== '') {
            $query->where('name', 'ilike', '%'.$this->search.'%');
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Render the component
     */
    public function render()
    {
        return view('livewire.token-manager');
    }
}
