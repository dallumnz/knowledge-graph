<div class="w-full">
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="lg">Quick Ingest</flux:heading>
            <flux:icon name="document-plus" class="size-5 text-zinc-400" />
        </div>

        {{-- Success Message --}}
        @if($successMessage)
            <div class="mb-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                <div class="flex items-center gap-2">
                    <flux:icon name="check-circle" class="size-5 text-green-500" />
                    <flux:text class="text-green-700 dark:text-green-300">{{ $successMessage }}</flux:text>
                </div>
                @if(count($ingestedNodes) > 0)
                    <div class="mt-2 text-sm text-green-600 dark:text-green-400">
                        Node IDs: {{ implode(', ', $ingestedNodes) }}
                    </div>
                @endif
            </div>
        @endif

        {{-- Error Message --}}
        @if($errorMessage)
            <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                <div class="flex items-center gap-2">
                    <flux:icon name="exclamation-triangle" class="size-5 text-red-500" />
                    <flux:text class="text-red-700 dark:text-red-300">{{ $errorMessage }}</flux:text>
                </div>
            </div>
        @endif

        {{-- Ingest Form --}}
        <form wire:submit="ingest" class="space-y-4">
            {{-- File Upload --}}
            <div>
                <flux:label for="uploadedFile">Upload Document (Optional)</flux:label>
                <flux:input
                    type="file"
                    wire:model="uploadedFile"
                    id="uploadedFile"
                    accept=".txt,.md,.pdf,.doc,.docx"
                    class="mt-1 w-full"
                />
                @error('uploadedFile')
                    <flux:error class="mt-1">{{ $message }}</flux:error>
                @enderror
                <flux:text class="mt-1 text-xs text-zinc-500">
                    Supported: TXT, MD, PDF, DOC, DOCX (max 10MB)
                </flux:text>
            </div>

            <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-zinc-300 dark:border-zinc-600"></div>
                </div>
                <div class="relative flex justify-center">
                    <span class="bg-white dark:bg-zinc-800 px-2 text-sm text-zinc-500">Or enter text manually</span>
                </div>
            </div>

            <div>
                <flux:label for="title">Title</flux:label>
                <flux:input
                    wire:model="title"
                    id="title"
                    placeholder="Enter a title for your content..."
                    class="mt-1 w-full"
                />
                @error('title')
                    <flux:error class="mt-1">{{ $message }}</flux:error>
                @enderror
            </div>

            <div>
                <flux:label for="content">Content</flux:label>
                <flux:textarea
                    wire:model="content"
                    id="content"
                    placeholder="Enter the content to ingest into your knowledge graph..."
                    rows="6"
                    class="mt-1 w-full"
                />
                @error('content')
                    <flux:error class="mt-1">{{ $message }}</flux:error>
                @enderror
            </div>

            <div class="flex items-center justify-between pt-2">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    Content will be chunked and embedded automatically.
                </flux:text>
                <flux:button
                    type="submit"
                    wire:loading.attr="disabled"
                    variant="primary"
                    icon="arrow-up-tray"
                >
                    <span wire:loading.remove wire:target="ingest">Ingest Content</span>
                    <span wire:loading wire:target="ingest">Ingesting...</span>
                </flux:button>
            </div>
        </form>
    </div>
</div>
