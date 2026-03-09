<?php

namespace App\Jobs;

use App\Models\Node;
use App\Services\Ai\HypotheticalQuestionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to generate hypothetical questions for a node.
 *
 * This job runs asynchronously after embedding generation to avoid
 * blocking the ingestion pipeline. It generates questions that the
 * node's content could answer and stores them in the node's metadata.
 */
class GenerateHypotheticalQuestions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(public Node $node)
    {
        $this->onQueue('embeddings'); // Use same queue as embeddings for ordering
    }

    /**
     * Execute the job.
     */
    public function handle(HypotheticalQuestionService $questionService): void
    {
        // Check if feature is enabled
        if (!$questionService->isEnabled()) {
            Log::debug('Skipping hypothetical question generation - feature disabled', [
                'node_id' => $this->node->id,
            ]);
            return;
        }

        // Refresh node to get latest data
        $this->node->refresh();

        // Skip if node has no content
        if (empty($this->node->content)) {
            Log::warning('Skipping question generation - node has no content', [
                'node_id' => $this->node->id,
            ]);
            return;
        }

        // Skip if questions already exist (prevent duplicates)
        $existingMetadata = $this->node->metadata ?? [];
        if (!empty($existingMetadata['hypothetical_questions'])) {
            Log::debug('Skipping question generation - questions already exist', [
                'node_id' => $this->node->id,
            ]);
            return;
        }

        try {
            Log::info('Generating hypothetical questions for node', [
                'node_id' => $this->node->id,
            ]);

            $questions = $questionService->generateQuestions($this->node->content);

            if (empty($questions)) {
                Log::warning('No questions generated for node', [
                    'node_id' => $this->node->id,
                ]);
                return;
            }

            // Update node metadata with questions
            $metadata = $this->node->metadata ?? [];
            $metadata['hypothetical_questions'] = $questions;

            $this->node->metadata = $metadata;
            $this->node->save();

            Log::info('Hypothetical questions generated and stored', [
                'node_id' => $this->node->id,
                'question_count' => count($questions),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate hypothetical questions', [
                'node_id' => $this->node->id,
                'error' => $e->getMessage(),
            ]);

            // Don't throw - this is non-critical functionality
            // The job failing shouldn't break the ingestion pipeline
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Hypothetical question generation job failed permanently', [
            'node_id' => $this->node->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
