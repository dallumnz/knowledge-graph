<?php

namespace App\Livewire;

use App\Jobs\GenerateEmbedding;
use App\Repositories\EdgeRepository;
use App\Repositories\NodeRepository;
use App\Services\DocumentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class QuickIngest extends Component
{
    use WithFileUploads;

    public string $title = '';

    public string $content = '';

    public $uploadedFile = null;

    public bool $isIngesting = false;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public array $ingestedNodes = [];

    protected array $rules = [
        'title' => 'required_without:uploadedFile|string|max:255',
        'content' => 'required_without:uploadedFile|string|min:1',
        'uploadedFile' => 'nullable|file|max:10240|mimes:txt,md,pdf,doc,docx',
    ];

    public function updated(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    public function ingest(): void
    {
        $this->validate();

        $this->isIngesting = true;
        $this->successMessage = null;
        $this->errorMessage = null;
        $this->ingestedNodes = [];

        try {
            $nodeRepository = app(NodeRepository::class);
            $edgeRepository = app(EdgeRepository::class);

            // Handle file upload if present
            if ($this->uploadedFile) {
                $text = $this->extractTextFromFile($this->uploadedFile);
                if (empty($this->title)) {
                    $this->title = $this->uploadedFile->getClientOriginalName();
                }
            } else {
                // Use text input
                $text = $this->title."\n\n".$this->content;
            }
            
            $chunkSize = 500;

            $chunks = $this->chunkText($text, $chunkSize);
            $nodeIds = [];

            DB::transaction(function () use ($chunks, $nodeRepository, $edgeRepository, &$nodeIds) {
                $previousNode = null;

                foreach ($chunks as $index => $chunk) {
                    // Create node for text chunk
                    $node = $nodeRepository->create([
                        'type' => 'text_chunk',
                        'content' => $chunk,
                    ]);

                    $nodeIds[] = $node->id;

                    // Dispatch embedding generation job (also triggers question generation)
                    GenerateEmbedding::dispatch($node);

                    // Create sequential edge if not first chunk
                    if ($previousNode !== null) {
                        $edgeRepository->connect(
                            $previousNode,
                            $node,
                            'followed_by',
                            1.0
                        );
                    }

                    $previousNode = $node;
                }
            });

            $this->ingestedNodes = $nodeIds;
            $this->successMessage = 'Content ingested successfully! Created '.count($nodeIds).' node(s). Embeddings and questions will be generated in the background.';

            // Clear form after successful ingestion
            $this->title = '';
            $this->content = '';
            $this->uploadedFile = null;

            // Dispatch event to refresh stats
            $this->dispatch('node-created');
        } catch (\Exception $e) {
            Log::error('Quick ingest failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->errorMessage = 'Failed to ingest content: '.$e->getMessage();
        }

        $this->isIngesting = false;
    }

    /**
     * Extract text content from uploaded file.
     *
     * Supports: txt, md, pdf (basic), doc, docx (basic)
     */
    private function extractTextFromFile($file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();

        return match ($extension) {
            'txt', 'md' => file_get_contents($path),
            'pdf' => $this->extractTextFromPdf($path),
            'doc', 'docx' => $this->extractTextFromDoc($path),
            default => file_get_contents($path),
        };
    }

    /**
     * Extract text from PDF (basic text extraction).
     */
    private function extractTextFromPdf(string $path): string
    {
        // Try pdftotext if available
        if (shell_exec('which pdftotext')) {
            $output = shell_exec("pdftotext -layout " . escapeshellarg($path) . " -");
            if ($output) {
                return $output;
            }
        }

        // Fallback: try to extract text from raw PDF
        $content = file_get_contents($path);
        // Remove binary data and extract text between streams
        $text = preg_replace('/[^\x20-\x7E\s]/', '', $content);
        // Clean up common PDF artifacts
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    /**
     * Extract text from Word document (basic).
     */
    private function extractTextFromDoc(string $path): string
    {
        // For docx, try to extract from word/document.xml
        if (str_ends_with($path, '.docx')) {
            $zip = new \ZipArchive();
            if ($zip->open($path) === true) {
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();
                if ($xml) {
                    // Strip XML tags to get text
                    return strip_tags(str_replace(['<w:p>', '<w:br/>'], ["\n\n", "\n"], $xml));
                }
            }
        }

        // Fallback for .doc files - read as text and clean
        $content = file_get_contents($path);
        return preg_replace('/[^\x20-\x7E\s]/', ' ', $content);
    }

    /**
     * Chunk text into smaller pieces.
     *
     * @return array<string>
     */
    private function chunkText(string $text, int $chunkSize): array
    {
        $chunks = [];
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        $currentChunk = '';
        foreach ($sentences as $sentence) {
            if (strlen($currentChunk) + strlen($sentence) > $chunkSize && $currentChunk !== '') {
                $chunks[] = trim($currentChunk);
                $currentChunk = $sentence;
            } else {
                $currentChunk .= ' '.$sentence;
            }
        }

        if ($currentChunk !== '') {
            $chunks[] = trim($currentChunk);
        }

        // If no chunks created (e.g., text shorter than chunk size), return whole text
        if (count($chunks) === 0) {
            $chunks[] = $text;
        }

        return $chunks;
    }

    public function render()
    {
        return view('livewire.quick-ingest');
    }
}
