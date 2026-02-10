<div class="w-full">
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="lg">Semantic Search</flux:heading>
            <flux:icon name="magnifying-glass" class="size-5 text-zinc-400" />
        </div>

        {{-- Search Input --}}
        <div class="flex gap-2 mb-4">
            <div class="flex-1">
                <flux:input
                    wire:model="query"
                    wire:keydown.enter="search"
                    placeholder="Search your knowledge graph..."
                    icon="magnifying-glass"
                    class="w-full"
                />
            </div>
            <flux:button
                wire:click="search"
                wire:loading.attr="disabled"
                variant="primary"
                class="shrink-0"
            >
                <span wire:loading.remove wire:target="search">Search</span>
                <span wire:loading wire:target="search">Searching...</span>
            </flux:button>
            @if($query || $hasSearched)
                <flux:button
                    wire:click="clear"
                    variant="ghost"
                    icon="x-mark"
                    class="shrink-0"
                >
                    Clear
                </flux:button>
            @endif
        </div>

        {{-- Error Message --}}
        @if($errorMessage)
            <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                <div class="flex items-center gap-2">
                    <flux:icon name="exclamation-triangle" class="size-5 text-red-500" />
                    <flux:text class="text-red-700 dark:text-red-300">{{ $errorMessage }}</flux:text>
                </div>
            </div>
        @endif

        {{-- Search Results --}}
        @if($hasSearched && !$isSearching)
            <div class="mt-4">
                @if(count($results) > 0)
                    <div class="flex items-center justify-between mb-3">
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                            Found {{ count($results) }} result(s) for "{{ $query }}"
                        </flux:text>
                    </div>

                    <div class="space-y-3 max-h-96 overflow-y-auto">
                        @foreach($results as $result)
                            <div class="p-4 bg-zinc-50 dark:bg-zinc-900/50 rounded-lg border border-zinc-200 dark:border-zinc-700 hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200">
                                                {{ $result['type'] }}
                                            </span>
                                            <span class="text-xs text-zinc-400">
                                                #{{ $result['id'] }}
                                            </span>
                                        </div>
                                        <p class="text-sm text-zinc-700 dark:text-zinc-300 line-clamp-3">
                                            {{ $result['content'] }}
                                        </p>
                                        <div class="flex items-center gap-3 mt-2 text-xs text-zinc-400">
                                            <span class="flex items-center gap-1">
                                                <flux:icon name="clock" class="size-3" />
                                                {{ $result['created_at'] }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="shrink-0">
                                        <div class="flex items-center gap-1 px-2 py-1 bg-purple-100 dark:bg-purple-900/30 rounded-full">
                                            <flux:icon name="bolt" class="size-3 text-purple-600 dark:text-purple-400" />
                                            <span class="text-xs font-medium text-purple-700 dark:text-purple-300">
                                                {{ $result['score'] }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <flux:icon name="magnifying-glass" class="size-12 text-zinc-300 mx-auto mb-3" />
                        <flux:heading size="md" class="text-zinc-500 mb-1">No results found</flux:heading>
                        <flux:text class="text-sm text-zinc-400">
                            Try adjusting your search query
                        </flux:text>
                    </div>
                @endif
            </div>
        @endif

        {{-- Loading State --}}
        <div wire:loading wire:target="search" class="mt-4">
            <div class="flex items-center justify-center py-8">
                <flux:icon name="arrow-path" class="size-8 text-zinc-400 animate-spin" />
            </div>
        </div>
    </div>
</div>
