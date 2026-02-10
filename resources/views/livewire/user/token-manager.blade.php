<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="lg">API Tokens</flux:heading>
            <flux:text class="text-sm text-gray-500 dark:text-gray-400">
                Manage your personal access tokens for API authentication.
            </flux:text>
        </div>
        <flux:button variant="primary" wire:click="openCreateModal" icon="plus">
            Create Token
        </flux:button>
    </div>

    {{-- Tokens Table --}}
    <flux:table>
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Created</flux:table.column>
            <flux:table.column>Last Used</flux:table.column>
            <flux:table.column class="text-right">Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->tokens as $token)
                <flux:table.row>
                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            <flux:icon name="key" class="size-4 text-gray-500" />
                            <span class="font-medium">{{ $token->name }}</span>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $token->created_at->format('M d, Y H:i') }}
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($token->last_used_at)
                            {{ $token->last_used_at->diffForHumans() }}
                        @else
                            <span class="text-gray-400">Never</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="text-right">
                        <flux:button
                            variant="ghost"
                            size="sm"
                            wire:click="confirmDelete({{ $token->id }})"
                            icon="trash"
                            class="text-red-600 hover:text-red-700"
                        >
                            Revoke
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" class="text-center py-8">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon name="key" class="size-8 text-gray-300" />
                            <flux:text class="text-gray-500">No API tokens found</flux:text>
                            <flux:text class="text-sm text-gray-400">
                                Create a token to get started with the API
                            </flux:text>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Create Token Modal --}}
    <flux:modal wire:model="showCreateModal" name="create-token" :show="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Create API Token</flux:heading>
                <flux:text class="text-sm text-gray-500">
                    Create a new personal access token for API authentication.
                </flux:text>
            </div>

            @if ($plainTextToken)
                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                    <flux:heading size="sm" class="text-green-800 dark:text-green-200 mb-2">
                        Token Created Successfully
                    </flux:heading>
                    <flux:text class="text-sm text-green-700 dark:text-green-300 mb-3">
                        Copy this token now. You won't be able to see it again!
                    </flux:text>
                    <div class="flex gap-2">
                        <code class="flex-1 bg-white dark:bg-zinc-800 px-3 py-2 rounded text-sm font-mono break-all">
                            {{ $plainTextToken }}
                        </code>
                        <flux:button
                            variant="ghost"
                            size="sm"
                            x-on:click="
                                navigator.clipboard.writeText(@js($plainTextToken));
                                $dispatch('notify', { message: 'Token copied to clipboard!', type: 'success' });
                            "
                            icon="clipboard"
                        >
                            Copy
                        </flux:button>
                    </div>
                </div>

                <div class="flex justify-end">
                    <flux:button wire:click="closeCreateModal" variant="primary">
                        Done
                    </flux:button>
                </div>
            @else
                <flux:field>
                    <flux:label>Token Name</flux:label>
                    <flux:input
                        wire:model="tokenName"
                        placeholder="e.g., Production API, Development"
                        autofocus
                    />
                    <flux:error name="tokenName" />
                </flux:field>

                <div class="flex justify-end gap-2">
                    <flux:button wire:click="closeCreateModal" variant="ghost">
                        Cancel
                    </flux:button>
                    <flux:button wire:click="createToken" variant="primary">
                        Create Token
                    </flux:button>
                </div>
            @endif
        </div>
    </flux:modal>

    {{-- Delete Confirmation Modal --}}
    <flux:modal wire:model="showDeleteModal" name="delete-token" :show="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Revoke Token</flux:heading>
                <flux:text class="text-sm text-gray-500">
                    Are you sure you want to revoke this API token? This action cannot be undone.
                </flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="closeDeleteModal" variant="ghost">
                    Cancel
                </flux:button>
                <flux:button wire:click="deleteToken" variant="danger">
                    Revoke Token
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
