<?php

namespace App\Jobs;

use App\Models\Node;
use App\Services\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateEmbedding implements ShouldQueue
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
        $this->onQueue('embeddings');
    }

    /**
     * Execute the job.
     */
    public function handle(EmbeddingService $embeddingService): void
    {
        try {
            Log::info('Generating embedding for node', ['node_id' => $this->node->id]);

            $embeddingService->createEmbeddingForNode($this->node);

            Log::info('Embedding generated successfully', ['node_id' => $this->node->id]);

            // Dispatch hypothetical question generation after successful embedding
            $this->dispatchQuestionGeneration();
        } catch (\Exception $e) {
            Log::error('Failed to generate embedding', [
                'node_id' => $this->node->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Dispatch hypothetical question generation job.
     */
    private function dispatchQuestionGeneration(): void
    {
        // Check if feature is enabled
        if (!config('ai.features.hypothetical_questions', true)) {
            return;
        }

        try {
            // Dispatch the job to the same queue for ordering
            GenerateHypotheticalQuestions::dispatch($this->node)
                ->onQueue('embeddings');

            Log::debug('Dispatched hypothetical question generation', [
                'node_id' => $this->node->id,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to dispatch question generation job', [
                'node_id' => $this->node->id,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - this is non-critical
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Embedding job failed permanently', [
            'node_id' => $this->node->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
