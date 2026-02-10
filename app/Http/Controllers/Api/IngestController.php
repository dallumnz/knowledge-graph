<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\EdgeRepository;
use App\Repositories\NodeRepository;
use App\Services\EmbeddingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class IngestController extends Controller
{
    public function __construct(
        private NodeRepository $nodeRepository,
        private EdgeRepository $edgeRepository,
        private EmbeddingService $embeddingService,
    ) {}

    /**
     * Ingest text content into the knowledge graph.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'text' => 'required|string|min:1',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'chunk_size' => 'nullable|integer|min:1|max:10000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $text = $request->input('text');
        $tags = $request->input('tags', []);
        $chunkSize = $request->input('chunk_size', 500);

        try {
            $chunks = $this->chunkText($text, $chunkSize);
            $nodeIds = [];

            DB::transaction(function () use ($chunks, $tags, &$nodeIds) {
                $previousNode = null;

                foreach ($chunks as $index => $chunk) {
                    // Create node for text chunk
                    $node = $this->nodeRepository->create([
                        'type' => 'text_chunk',
                        'content' => $chunk,
                    ]);

                    $nodeIds[] = $node->id;

                    // Generate and store embedding
                    $this->embeddingService->createEmbeddingForNode($node);

                    // Create sequential edge if not first chunk
                    if ($previousNode !== null) {
                        $this->edgeRepository->connect(
                            $previousNode,
                            $node,
                            'followed_by',
                            1.0
                        );
                    }

                    // Create tag edges if provided
                    foreach ($tags as $tag) {
                        $tagNode = $this->nodeRepository->create([
                            'type' => 'tag',
                            'content' => $tag,
                        ]);

                        $this->edgeRepository->connect(
                            $node,
                            $tagNode,
                            'tagged_with',
                            1.0
                        );
                    }

                    $previousNode = $node;
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Content ingested successfully',
                'data' => [
                    'node_ids' => $nodeIds,
                    'chunk_count' => count($chunks),
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Ingestion failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to ingest content',
                'error' => $e->getMessage(),
            ], 500);
        }
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

    /**
     * Handle quick ingest from dashboard form submission.
     * This provides a non-JavaScript fallback for the quick ingest form.
     */
    public function quickIngest(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string|min:1',
        ]);

        if ($validator->fails()) {
            return redirect()->route('dashboard')
                ->withErrors($validator)
                ->withInput();
        }

        $title = $request->input('title');
        $content = $request->input('content');

        // Combine title and content
        $text = $title."\n\n".$content;
        $chunkSize = 500;

        try {
            $chunks = $this->chunkText($text, $chunkSize);
            $nodeIds = [];

            DB::transaction(function () use ($chunks, &$nodeIds) {
                $previousNode = null;

                foreach ($chunks as $chunk) {
                    // Create node for text chunk
                    $node = $this->nodeRepository->create([
                        'type' => 'text_chunk',
                        'content' => $chunk,
                    ]);

                    $nodeIds[] = $node->id;

                    // Generate and store embedding
                    $this->embeddingService->createEmbeddingForNode($node);

                    // Create sequential edge if not first chunk
                    if ($previousNode !== null) {
                        $this->edgeRepository->connect(
                            $previousNode,
                            $node,
                            'followed_by',
                            1.0
                        );
                    }

                    $previousNode = $node;
                }
            });

            return redirect()->route('dashboard')
                ->with('success', 'Content ingested successfully! Created '.count($nodeIds).' node(s).');
        } catch (\Exception $e) {
            Log::error('Quick ingest failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('dashboard')
                ->with('error', 'Failed to ingest content: '.$e->getMessage())
                ->withInput();
        }
    }
}
