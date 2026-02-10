<x-layouts::app :title="__('Dashboard')">
    <div class="space-y-6">
        {{-- Page Header --}}
        <div class="flex items-center justify-between">
            <flux:heading size="2xl">Dashboard</flux:heading>
            <div class="flex items-center gap-2">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    Welcome back, {{ auth()->user()->name }}
                </flux:text>
            </div>
        </div>

        {{-- Stats Section --}}
        @include('partials.stats')

        {{-- Main Content Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Search Section --}}
            @include('partials.search-bar')

            {{-- Quick Ingest Section --}}
            @include('partials.ingest-form')
        </div>

        {{-- Quick Actions Footer --}}
        <div class="mt-8 bg-gradient-to-r from-blue-50 to-purple-50 dark:from-blue-900/20 dark:to-purple-900/20 rounded-xl border border-blue-200 dark:border-blue-800 p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <flux:heading size="lg">API Access</flux:heading>
                    <flux:text class="text-sm text-gray-600 dark:text-gray-400">
                        Use the API to programmatically ingest and search your knowledge graph
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
                        href="{{ url('/api/ingest') }}"
                        variant="primary"
                        icon="code-bracket"
                        disabled
                    >
                        API Documentation
                    </flux:button>
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>
