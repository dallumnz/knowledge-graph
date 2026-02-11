<div class="w-full">
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="lg">Document Storage</flux:heading>
            <div class="flex items-center gap-2">
                <flux:icon name="folder" class="size-5 text-zinc-400" />
            </div>
        </div>

        {{-- Success Message --}}
        @if($successMessage)
            <div class="mb-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                <div class="flex items-center gap-2">
                    <flux:icon name="check-circle" class="size-5 text-green-500" />
                    <flux:text class="text-green-700 dark:text-green-300">{{ $successMessage }}</flux:text>
                </div>
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

        {{-- Search and Upload Controls --}}
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div class="w-full sm:w-auto sm:flex-1 max-w-md">
                <flux:input
                    wire:model.live="search"
                    placeholder="Search documents..."
                    icon="magnifying-glass"
                    class="w-full"
                />
            </div>
            <flux:button
                wire:click="openUploadForm"
                variant="primary"
                icon="arrow-up-tray"
            >
                Upload Document
            </flux:button>
        </div>

        {{-- Upload Form Modal --}}
        @if($showUploadForm)
            <div class="mb-6 p-4 bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="md">Upload New Document</flux:heading>
                    <flux:button
                        wire:click="closeUploadForm"
                        variant="ghost"
                        size="sm"
                        icon="x-mark"
                    >
                        Cancel
                    </flux:button>
                </div>

                <form wire:submit="storeDocument" class="space-y-4">
                    <div>
                        <flux:label for="title">Title</flux:label>
                        <flux:input
                            wire:model="title"
                            id="title"
                            placeholder="Enter document title..."
                            class="mt-1 w-full"
                        />
                        @error('title')
                            <flux:error class="mt-1">{{ $message }}</flux:error>
                        @enderror
                    </div>

                    <div>
                        <flux:label for="documentFile">Document File (Optional)</flux:label>
                        <flux:input
                            wire:model="documentFile"
                            id="documentFile"
                            type="file"
                            class="mt-1 w-full"
                        />
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                            Supported formats: TXT, PDF, DOC, DOCX, MD (max 10MB)
                        </flux:text>
                        @error('documentFile')
                            <flux:error class="mt-1">{{ $message }}</flux:error>
                        @enderror
                    </div>

                    <div>
                        <flux:label for="content">Content (Optional - for text documents)</flux:label>
                        <flux:textarea
                            wire:model="content"
                            id="content"
                            placeholder="Enter document content or paste text here..."
                            rows="6"
                            class="mt-1 w-full"
                        />
                        @error('content')
                            <flux:error class="mt-1">{{ $message }}</flux:error>
                        @enderror
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <flux:button
                            type="button"
                            wire:click="closeUploadForm"
                            variant="outline"
                        >
                            Cancel
                        </flux:button>
                        <flux:button
                            type="submit"
                            wire:loading.attr="disabled"
                            variant="primary"
                            icon="arrow-up-tray"
                        >
                            <span wire:loading.remove wire:target="storeDocument">Upload Document</span>
                            <span wire:loading wire:target="storeDocument">Uploading...</span>
                        </flux:button>
                    </div>
                </form>
            </div>
        @endif

        {{-- Document Viewer Modal --}}
        @if($viewingDocumentId && $viewingDocument)
            <div class="mb-6 p-4 bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <flux:heading size="md">{{ $viewingDocument->title }}</flux:heading>
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $viewingDocument->source_type }} • Version {{ $viewingDocument->version }}
                        </flux:text>
                    </div>
                    <flux:button
                        wire:click="closeDocumentViewer"
                        variant="ghost"
                        size="sm"
                        icon="x-mark"
                    >
                        Close
                    </flux:button>
                </div>

                @if($viewingDocument->content)
                    <div class="bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 max-h-96 overflow-y-auto">
                        <pre class="text-sm text-zinc-700 dark:text-zinc-300 whitespace-pre-wrap">{{ $viewingDocument->content }}</pre>
                    </div>
                @else
                    <div class="text-center py-8 text-zinc-500 dark:text-zinc-400">
                        <flux:icon name="document" class="size-12 mx-auto mb-2 opacity-50" />
                        <flux:text>No text content available for this document.</flux:text>
                    </div>
                @endif
            </div>
        @endif

        {{-- Documents List --}}
        <div class="space-y-2">
            @if($this->documents->count() > 0)
                @foreach($this->documents as $document)
                    <div class="flex items-center justify-between p-3 bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-700 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800/50 transition-colors">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <div class="flex-shrink-0">
                                @if($document->source_type === 'file')
                                    <flux:icon name="document-text" class="size-5 text-blue-500" />
                                @elseif($document->source_type === 'url')
                                    <flux:icon name="link" class="size-5 text-green-500" />
                                @else
                                    <flux:icon name="document" class="size-5 text-zinc-400" />
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <flux:text class="font-medium truncate">{{ $document->title }}</flux:text>
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $document->source_type }} • {{ $document->created_at->diffForHumans() }}
                                    @if($document->is_active)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300 ml-2">
                                            Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-zinc-100 text-zinc-800 dark:bg-zinc-700 dark:text-zinc-300 ml-2">
                                            Inactive
                                        </span>
                                    @endif
                                </flux:text>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0 ml-2">
                            <flux:button
                                wire:click="viewDocument({{ $document->id }})"
                                variant="ghost"
                                size="sm"
                                icon="eye"
                                title="View document"
                            >
                                View
                            </flux:button>
                            <flux:button
                                wire:click="deleteDocument({{ $document->id }})"
                                wire:confirm="Are you sure you want to delete this document?"
                                variant="ghost"
                                size="sm"
                                icon="trash"
                                class="text-red-600 hover:text-red-700"
                                title="Delete document"
                            >
                                Delete
                            </flux:button>
                        </div>
                    </div>
                @endforeach

                {{-- Pagination --}}
                <div class="mt-4">
                    {{ $this->documents->links() }}
                </div>
            @else
                <div class="text-center py-12 text-zinc-500 dark:text-zinc-400">
                    <flux:icon name="folder-open" class="size-16 mx-auto mb-4 opacity-50" />
                    <flux:heading size="md" class="mb-2">No documents found</flux:heading>
                    <flux:text>Upload your first document to get started.</flux:text>
                </div>
            @endif
        </div>
    </div>
</div>
