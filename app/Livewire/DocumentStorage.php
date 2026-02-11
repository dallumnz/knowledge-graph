<?php

namespace App\Livewire;

use App\Models\Document;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class DocumentStorage extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $title = '';

    public $documentFile = null;

    public ?string $content = null;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public bool $showUploadForm = false;

    public ?int $viewingDocumentId = null;

    public ?Document $viewingDocument = null;

    public string $search = '';

    public int $perPage = 10;

    /**
     * Validation rules
     *
     * @return array<string, string>
     */
    protected function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'documentFile' => 'nullable|file|max:10240|mimes:txt,pdf,doc,docx,md',
            'content' => 'nullable|string',
        ];
    }

    /**
     * Open the upload form
     */
    public function openUploadForm(): void
    {
        $this->reset(['title', 'documentFile', 'content', 'successMessage', 'errorMessage']);
        $this->showUploadForm = true;
    }

    /**
     * Close the upload form
     */
    public function closeUploadForm(): void
    {
        $this->showUploadForm = false;
        $this->reset(['title', 'documentFile', 'content', 'successMessage', 'errorMessage']);
    }

    /**
     * Store a new document
     */
    public function storeDocument(): void
    {
        $this->validate();

        $user = Auth::user();

        if ($user === null) {
            $this->errorMessage = 'You must be logged in to upload documents.';

            return;
        }

        try {
            $filePath = null;
            $sourceType = 'text';
            $documentContent = $this->content ?? '';

            // Handle file upload if provided
            if ($this->documentFile !== null) {
                $sourceType = 'file';
                $fileName = $this->documentFile->getClientOriginalName();
                $filePath = $this->documentFile->store('documents/'.$user->id, 'local');

                // If no title provided, use file name
                if ($this->title === '') {
                    $this->title = pathinfo($fileName, PATHINFO_FILENAME);
                }

                // Extract content from text files
                if (in_array($this->documentFile->getMimeType(), ['text/plain', 'text/markdown'])) {
                    $documentContent = file_get_contents($this->documentFile->getRealPath());
                }
            }

            // Create document
            $document = Document::create([
                'title' => $this->title,
                'source_type' => $sourceType,
                'source_path' => $filePath,
                'content' => $documentContent,
                'metadata' => [
                    'uploaded_by' => $user->id,
                    'original_filename' => $this->documentFile?->getClientOriginalName(),
                ],
                'version' => 1,
                'is_active' => true,
            ]);

            $this->successMessage = 'Document "'.$document->title.'" uploaded successfully!';
            $this->showUploadForm = false;
            $this->reset(['title', 'documentFile', 'content']);

            // Dispatch event to refresh list
            $this->dispatch('document-created');
        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to upload document: '.$e->getMessage();
        }
    }

    /**
     * View a document's content
     */
    public function viewDocument(int $documentId): void
    {
        $user = Auth::user();

        if ($user === null) {
            return;
        }

        $this->viewingDocument = Document::find($documentId);

        if ($this->viewingDocument !== null) {
            $this->viewingDocumentId = $documentId;
        }
    }

    /**
     * Close the document viewer
     */
    public function closeDocumentViewer(): void
    {
        $this->viewingDocumentId = null;
        $this->viewingDocument = null;
    }

    /**
     * Delete a document
     */
    public function deleteDocument(int $documentId): void
    {
        $user = Auth::user();

        if ($user === null) {
            return;
        }

        $document = Document::find($documentId);

        if ($document !== null) {
            // Delete associated file if exists
            if ($document->source_path !== null && Storage::exists($document->source_path)) {
                Storage::delete($document->source_path);
            }

            $document->delete();
            $this->dispatch('document-deleted');
        }
    }

    /**
     * Get the documents query
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator<Document>
     */
    public function getDocumentsProperty()
    {
        $query = Document::query();

        if ($this->search !== '') {
            $query->where('title', 'ilike', '%'.$this->search.'%');
        }

        return $query->orderBy('created_at', 'desc')->paginate($this->perPage);
    }

    /**
     * Render the component
     */
    public function render()
    {
        return view('livewire.document-storage');
    }
}
