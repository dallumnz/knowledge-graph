<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\EdgeRepository;
use App\Repositories\NodeRepository;
use App\Services\Chunking\DocumentChunker;
use App\Services\DocumentService;
use App\Services\EmbeddingService;
use App\Services\Metadata\MetadataService;
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
        private DocumentService $documentService,
        private MetadataService $metadataService,
        private DocumentChunker $documentChunker,
    ) {}

    /**
     * Ingest text content into the knowledge graph.
     *
     * Supports optional document metadata for source attribution.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'text' => 'required|string|min:1',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'chunk_size' => 'nullable|integer|min:1|max:10000',
            'overlap' => 'nullable|integer|min:0|max:1000',
            'document' => 'nullable|array',
            'document.title' => 'required_with:document|string|max:500',
            'document.source_type' => 'required_with:document|string|max:50|in:file,url,text,api',
            'document.source_path' => 'nullable|string',
            'document.metadata' => 'nullable|array',
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
        $overlap = $request->input('overlap', 0);
        $documentData = $request->input('document');

        try {
            $chunks = $this->documentChunker->chunkText($text, $chunkSize, $overlap);
            $nodeIds = [];
            $document = null;

            DB::transaction(function () use ($chunks, $tags, $text, $documentData, &$nodeIds, &$document) {
                // Create document if document data is provided
                if ($documentData !== null) {
                    $document = $this->documentService->create([
                        'title' => $documentData['title'],
                        'source_type' => $documentData['source_type'],
                        'source_path' => $documentData['source_path'] ?? null,
                        'content' => $text, // Store full document content
                        'metadata' => $documentData['metadata'] ?? [],
                    ]);
                }

                $previousNode = null;

                foreach ($chunks as $index => $chunk) {
                    // Generate metadata for the chunk
                    $summary = $this->metadataService->generateSummary($chunk);
                    $keywords = $this->metadataService->extractKeywords($chunk);
                    $metadata = [
                        'summary' => $summary,
                        'keywords' => $keywords,
                    ];

                    // Create node for text chunk
                    $nodeData = [
                        'type' => 'text_chunk',
                        'content' => $chunk,
                        'metadata' => $metadata,
                    ];

                    // Link to document if provided
                    if ($document !== null) {
                        $nodeData['document_id'] = $document->id;
                    }

                    $node = $this->nodeRepository->create($nodeData);

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

            $response = [
                'success' => true,
                'message' => 'Content ingested successfully',
                'data' => [
                    'node_ids' => $nodeIds,
                    'chunk_count' => count($chunks),
                ],
            ];

            // Include document info in response if created
            if ($document !== null) {
                $response['data']['document'] = [
                    'id' => $document->id,
                    'title' => $document->title,
                    'source_type' => $document->source_type,
                ];
            }

            return response()->json($response, 201);
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
        $overlap = 0;

        try {
            $chunks = $this->documentChunker->chunkText($text, $chunkSize, $overlap);
            $nodeIds = [];
            $document = null;

            DB::transaction(function () use ($title, $text, $chunks, &$nodeIds, &$document) {
                // Create document for the quick ingest
                $document = $this->documentService->create([
                    'title' => $title,
                    'source_type' => 'text',
                    'content' => $text,
                    'metadata' => [
                        'ingest_method' => 'quick_ingest',
                        'has_title' => true,
                    ],
                ]);

                $previousNode = null;

                foreach ($chunks as $chunk) {
                    // Generate metadata for the chunk
                    $summary = $this->metadataService->generateSummary($chunk);
                    $keywords = $this->metadataService->extractKeywords($chunk);
                    $metadata = [
                        'summary' => $summary,
                        'keywords' => $keywords,
                    ];

                    // Create node for text chunk
                    $node = $this->nodeRepository->create([
                        'type' => 'text_chunk',
                        'content' => $chunk,
                        'document_id' => $document->id,
                        'metadata' => $metadata,
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

            $message = 'Content ingested successfully! Created '.count($nodeIds).' node(s).';
            if ($document !== null) {
                $message .= ' Document ID: '.$document->id;
            }

            return redirect()->route('dashboard')
                ->with('success', $message);
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
