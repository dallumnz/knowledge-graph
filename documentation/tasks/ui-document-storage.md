# UI Enhancement for Document Storage

**Task:** Update Knowledge Graph UI to support document storage and source attribution

**Location:** ~/projects/knowledge-graph

---

## Current State

### Backend (Already Implemented)
- `documents` table with `id`, `title`, `source_type`, `source_path`, `content`, `metadata`, `version`, `is_active`, `timestamps`
- `nodes.document_id` foreign key linking chunks to source documents
- Document API routes: CRUD + chunks endpoint
- Search results include document attribution
- IngestController accepts optional document metadata

### Frontend (Needs Updates)
- Livewire components exist for dashboard, search, ingestion
- No document management UI
- Search results don't show source attribution
- Ingestion form has no document metadata fields

---

## Requirements

### 1. Document Management Page

**New File:** `app/Livewire/Documents/DocumentsIndex.php`

```php
class DocumentsIndex extends Component
{
    public array $filters = [];
    public int $perPage = 20;
    
    // Filters
    public ?string $sourceType = null;
    public ?bool $isActive = null;
    public ?string $search = null;
    
    public function render()
    {
        return view('livewire.documents.index', [
            'documents' => Document::query()
                ->when($this->sourceType, fn($q) => $q->where('source_type', $this->sourceType))
                ->when($this->isActive !== null, fn($q) => $q->where('is_active', $this->isActive))
                ->when($this->search, fn($q) => $q->where('title', 'like', "%{$this->search}%"))
                ->orderByDesc('created_at')
                ->paginate($this->perPage)
        ]);
    }
    
    public function delete(int $id): void
    {
        Document::findOrFail($id)->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Document deleted']);
    }
}
```

**New File:** `resources/views/livewire/documents/index.blade.php`

**Features:**
- Table with columns: Title, Source Type, Source Path, Version, Created At, Actions
- Search bar for title filtering
- Dropdown filter for source_type
- Toggle for is_active status
- Pagination
- Action buttons: View, Edit, Delete (with confirmation)
- Use existing Tailwind styling patterns

---

### 2. Document Detail View

**New File:** `app/Livewire/Documents/Show.php`

```php
class Show extends Component
{
    public Document $document;
    
    protected function getNodeCountProperty(): int
    {
        return $this->document->nodes()->count();
    }
    
    protected function getChunksProperty(): Collection
    {
        return $this->document->nodes()->orderByDesc('created_at')->get();
    }
    
    public function render()
    {
        return view('livewire.documents.show', [
            'chunks' => $this->chunks,
            'nodeCount' => $this->nodeCount
        ]);
    }
}
```

**New File:** `resources/views/livewire/documents/show.blade.php`

**Features:**
- Document metadata card:
  - Title, Source Type, Source Path
  - Version, Is Active status
  - Created/Updated timestamps
  - Metadata JSON display (formatted)
- Tabs or sections:
  - **Overview** — Document info
  - **Chunks** — All nodes belonging to this document
    - Searchable/filterable list
    - Each chunk shows preview (first 200 chars)
    - Link to node detail if exists
  - **Raw** — Full document content (collapsible)
- Back button to document list
- Actions: Edit, Delete

---

### 3. Search Results Enhancement

**Modify:** `app/Livewire/SearchResults.php`

Add to result data:
```php
public function getResults(): array
{
    // Existing vector search logic...
    
    return collect($results)->map(function ($result) {
        $node = $result['node'];
        
        return [
            'id' => $node->id,
            'content' => $result['content'],
            'score' => $result['score'],
            'document' => $node->document ? [
                'id' => $node->document->id,
                'title' => $node->document->title,
                'source_path' => $node->document->source_path,
                'source_type' => $node->document->source_type,
            ] : null,
            'created_at' => $node->created_at,
        ];
    })->toArray();
}
```

**Modify:** `resources/views/livewire/search-results.blade.php`

Add source attribution display:
```blade
@foreach($results as $result)
    <div class="search-result border-b py-4">
        <div class="flex justify-between items-start">
            <div class="content flex-1">
                <p class="text-gray-800">{{ Str::limit($result['content'], 300) }}</p>
            </div>
            <div class="score ml-4 text-sm text-gray-500">
                {{ round($result['score'] * 100, 1) }}%
            </div>
        </div>
        
        @if($result['document'])
            <div class="source mt-2 text-sm text-gray-500 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <a href="{{ route('documents.show', $result['document']['id']) }}" 
                   class="text-blue-600 hover:underline">
                    {{ $result['document']['title'] }}
                </a>
                @if($result['document']['source_path'])
                    <span class="text-gray-400">({{ $result['document']['source_path'] }})</span>
                @endif
            </div>
        @endif
    </div>
@endforeach
```

---

### 4. Ingestion Form Enhancement

**Modify:** `app/Livewire/Ingest.php`

Add document fields:
```php
public array $document = [
    'title' => '',
    'source_type' => 'file',
    'source_path' => '',
    'metadata' => '{}',
];

public function updatedDocumentSourceType($value): void
{
    // Optional: adjust source_path placeholder based on type
}

public function save(): void
{
    $payload = [
        'chunks' => $this->chunks,
    ];
    
    // Only include document if title is provided
    if (!empty($this->document['title'])) {
        $payload['document'] = [
            'title' => $this->document['title'],
            'source_type' => $this->document['source_type'],
            'source_path' => $this->document['source_path'],
            'metadata' => json_decode($this->document['metadata'], true) ?? [],
        ];
    }
    
    // Existing ingestion logic...
}
```

**Modify:** `resources/views/livewire/ingest.blade.php`

Add document metadata section:
```blade
<div class="bg-gray-50 p-6 rounded-lg mb-6">
    <h3 class="text-lg font-medium text-gray-900 mb-4">
        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        Document Metadata (Optional)
    </h3>
    <p class="text-sm text-gray-500 mb-4">
        Link chunks to a source document for attribution and versioning.
    </p>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
            <input type="text" wire:model="document.title" 
                   class="w-full rounded-md border-gray-300 shadow-sm"
                   placeholder="Q4 Policy Document">
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Source Type</label>
            <select wire:model="document.source_type" class="w-full rounded-md border-gray-300 shadow-sm">
                <option value="file">File</option>
                <option value="url">URL</option>
                <option value="text">Text</option>
                <option value="api">API</option>
            </select>
        </div>
        
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Source Path</label>
            <input type="text" wire:model="document.source_path" 
                   class="w-full rounded-md border-gray-300 shadow-sm"
                   placeholder="/docs/policies/q4-2024.pdf">
        </div>
        
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Metadata (JSON)</label>
            <textarea wire:model="document.metadata" rows="3"
                     class="w-full rounded-md border-gray-300 shadow-sm font-mono text-sm"
                     placeholder='{"author": "Legal Team", "version": 1}'></textarea>
            @error('document.metadata') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>
    </div>
</div>
```

---

### 5. Node Detail Enhancement

**Modify:** Node detail view (find existing file)

Add document attribution section:
```blade
@if($node->document)
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <h3 class="text-sm font-medium text-blue-800 mb-2 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Source Document
        </h3>
        <div class="text-sm text-blue-700">
            <p><strong>Title:</strong> {{ $node->document->title }}</p>
            <p><strong>Source:</strong> {{ $node->document->source_type }} — {{ $node->document->source_path }}</p>
            <p><strong>Version:</strong> {{ $node->document->version }}</p>
            <a href="{{ route('documents.show', $node->document->id) }}" 
               class="inline-flex items-center mt-2 text-blue-600 hover:underline">
                View full document
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
    </div>
@endif
```

---

### 6. Navigation

**Modify:** Main navigation layout

Add Documents link:
```blade
<x-nav-link :href="route('documents.index')" :active="request()->routeIs('documents.*')">
    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
    </svg>
    Documents
</x-nav-link>
```

---

## Files to Create/Modify

### New Files
1. `app/Livewire/Documents/DocumentsIndex.php`
2. `app/Livewire/Documents/Show.php`
3. `resources/views/livewire/documents/index.blade.php`
4. `resources/views/livewire/documents/show.blade.php`

### Modified Files
1. `app/Livewire/SearchResults.php` — Add document to result data
2. `app/Livewire/Ingest.php` — Add document metadata fields
3. `resources/views/livewire/search-results.blade.php` — Display source attribution
4. `resources/views/livewire/ingest.blade.php` — Document metadata section
5. Node detail view (find and modify) — Add document attribution
6. Navigation layout — Add Documents link

### Routes
Add to `routes/web.php`:
```php
Route::prefix('documents')->name('documents.')->group(function () {
    Route::get('/', DocumentsIndex::class)->name('index');
    Route::get('/{document}', Show::class)->name('show');
});
```

---

## Tests to Write

1. `tests/Feature/Livewire/Documents/DocumentsIndexTest.php`
   - List documents
   - Filter by source_type
   - Filter by is_active
   - Search by title
   - Delete document

2. `tests/Feature/Livewire/Documents/ShowTest.php`
   - View document metadata
   - List document chunks
   - Navigate back to list

3. `tests/Feature/Livewire/SearchResultsTest.php`
   - Search results show document attribution

4. `tests/Feature/Livewire/IngestTest.php`
   - Ingest with document metadata
   - Ingest without document metadata (backward compatibility)

---

## Implementation Steps

### Step 1: Document List UI
- [ ] Create DocumentsIndex Livewire component
- [ ] Create index Blade template
- [ ] Add navigation link
- [ ] Add routes
- [ ] Write tests

### Step 2: Document Detail UI
- [ ] Create Show Livewire component
- [ ] Create show Blade template
- [ ] Write tests

### Step 3: Search Enhancement
- [ ] Update SearchResults to include document
- [ ] Update Blade to display attribution
- [ ] Write tests

### Step 4: Ingestion Enhancement
- [ ] Update Ingest component
- [ ] Update Blade template
- [ ] Test backward compatibility
- [ ] Write tests

### Step 5: Node Detail Enhancement
- [ ] Add document attribution to node detail
- [ ] Write tests

---

## Success Criteria

- [ ] Documents page accessible via navigation
- [ ] Documents can be listed, filtered, searched
- [ ] Document detail shows metadata and chunks
- [ ] Search results display source document
- [ ] Ingestion accepts optional document metadata
- [ ] Node detail shows source document
- [ ] All existing tests pass
- [ ] New UI tests pass (80%+ coverage)

---

## Design Guidelines

- Use existing Tailwind CSS patterns
- Follow component structure: `resources/views/livewire/component-name/`
- Use icons from Heroicons (SVG) consistently
- Mobile-responsive layout
- Loading states with wire:loading
- Toast notifications for actions
- Confirmation dialogs for destructive actions

---

## Commands

```bash
# Run tests
php artisan test

# Start dev server
npm run dev
php artisan serve

# Clear caches
php artisan view:clear
php artisan cache:clear
```

---

## Related Files

- Backend: `app/Models/Document.php`
- Backend: `app/Http/Controllers/Api/DocumentController.php`
- Existing: `app/Livewire/SearchResults.php`
- Existing: `app/Livewire/Ingest.php`
- Existing: `resources/views/livewire/`
