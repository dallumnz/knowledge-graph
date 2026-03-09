<?php

namespace App\Livewire;

use App\Jobs\GenerateEmbedding;
use App\Repositories\EdgeRepository;
use App\Repositories\NodeRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class QuickIngest extends Component
{
    public string $title = '';

    public string $content = '';

    public bool $isIngesting = false;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public array $ingestedNodes = [];

    protected array $rules = [
        'title' => 'required|string|max:255',
        'content' => 'required|string|min:1',
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

            // Combine title and content for ingestion
            $text = $this->title."\n\n".$this->content;
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
