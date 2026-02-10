<div>
    <div class="flex items-center justify-between mb-6">
        <flux:heading size="xl">Knowledge Graph Overview</flux:heading>
        <flux:button
            wire:click="refreshStats"
            variant="ghost"
            size="sm"
            icon="arrow-path"
            class="{{ $isLoading ? 'animate-spin' : '' }}"
        >
            Refresh
        </flux:button>
    </div>

    {{-- Stats Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        {{-- Nodes Card --}}
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex items-center gap-4">
                <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                    <flux:icon name="circle-stack" class="size-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <flux:text class="text-sm text-gray-500 dark:text-gray-400">Total Nodes</flux:text>
                    <flux:heading size="2xl">{{ number_format($nodeCount) }}</flux:heading>
                </div>
            </div>
        </div>

        {{-- Edges Card --}}
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex items-center gap-4">
                <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg">
                    <flux:icon name="arrows-right-left" class="size-6 text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <flux:text class="text-sm text-gray-500 dark:text-gray-400">Total Edges</flux:text>
                    <flux:heading size="2xl">{{ number_format($edgeCount) }}</flux:heading>
                </div>
            </div>
        </div>

        {{-- Embeddings Card --}}
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex items-center gap-4">
                <div class="p-3 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                    <flux:icon name="bolt" class="size-6 text-purple-600 dark:text-purple-400" />
                </div>
                <div>
                    <flux:text class="text-sm text-gray-500 dark:text-gray-400">Embeddings</flux:text>
                    <flux:heading size="2xl">{{ number_format($embeddingCount) }}</flux:heading>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Node Types Distribution --}}
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="lg" class="mb-4">Node Types</flux:heading>
            @if (count($nodeTypeDistribution) > 0)
                <div class="space-y-3">
                    @foreach ($nodeTypeDistribution as $item)
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-zinc-100 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-200">
                                    {{ $item['type'] }}
                                </span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-32 h-2 bg-zinc-100 dark:bg-zinc-700 rounded-full overflow-hidden">
                                    @php
                                        $percentage = $nodeCount > 0 ? ($item['count'] / $nodeCount) * 100 : 0;
                                    @endphp
                                    <div class="h-full bg-blue-500 rounded-full" style="width: {{ $percentage }}%;"></div>
                                </div>
                                <span class="text-sm text-gray-600 dark:text-gray-400 w-12 text-right">
                                    {{ number_format($item['count']) }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <flux:icon name="inbox" class="size-8 text-gray-300 mx-auto mb-2" />
                    <flux:text class="text-gray-500">No nodes yet</flux:text>
                </div>
            @endif
        </div>

        {{-- Recent Nodes --}}
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="lg" class="mb-4">Recent Nodes</flux:heading>
            @if (count($recentNodes) > 0)
                <div class="space-y-3">
                    @foreach ($recentNodes as $node)
                        <div class="flex items-start gap-3 p-3 bg-zinc-50 dark:bg-zinc-900/50 rounded-lg">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200">
                                        {{ $node['type'] }}
                                    </span>
                                    @if ($node['has_embedding'])
                                        <flux:icon name="bolt" class="size-3 text-purple-500" />
                                    @endif
                                </div>
                                <p class="text-sm text-gray-700 dark:text-gray-300 truncate">
                                    {{ $node['content'] }}
                                </p>
                                <p class="text-xs text-gray-400 mt-1">
                                    {{ $node['created_at'] }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <flux:icon name="inbox" class="size-8 text-gray-300 mx-auto mb-2" />
                    <flux:text class="text-gray-500">No recent nodes</flux:text>
                </div>
            @endif
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="mt-8 bg-gradient-to-r from-blue-50 to-purple-50 dark:from-blue-900/20 dark:to-purple-900/20 rounded-xl border border-blue-200 dark:border-blue-800 p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <flux:heading size="lg">Get Started</flux:heading>
                <flux:text class="text-sm text-gray-600 dark:text-gray-400">
                    Use the API to ingest content into your knowledge graph
                </flux:text>
            </div>
            <div class="flex gap-3">
                <flux:button
                    href="{{ route('settings.tokens') }}"
                    variant="outline"
                    icon="key"
                >
                    Manage API Tokens
                </flux:button>
                <flux:button
                    href="https://github.com/laravel/livewire-starter-kit"
                    target="_blank"
                    variant="primary"
                    icon="code-bracket"
                >
                    API Documentation
                </flux:button>
            </div>
        </div>
    </div>
</div>
