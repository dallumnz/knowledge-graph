<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * RAG Metrics Model
 *
 * Stores per-query performance metrics for the RAG system.
 * Tracks retrieval quality, response accuracy, latency, and token usage.
 *
 * @property int $id
 * @property string $query_id Unique identifier for the query
 * @property string $query The actual query text
 * @property int|null $user_id ID of the user who made the query
 * @property float|null $retrieval_precision Percentage of retrieved chunks that were relevant
 * @property float|null $retrieval_recall Percentage of relevant chunks that were retrieved
 * @property float|null $answer_accuracy Whether the answer correctly addressed the query
 * @property float $confidence_score Overall confidence score from validation pipeline
 * @property array $validation_results Detailed validation results from all nodes
 * @property int $latency_ms Total response time in milliseconds
 * @property int $tokens_input Number of input tokens
 * @property int $tokens_output Number of output tokens
 * @property int|null $chunks_retrieved Number of chunks retrieved
 * @property string|null $search_method Search method used: vector, keyword, hybrid
 * @property bool|null $validation_passed Whether validation passed overall
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class RagMetrics extends Model
{
    use HasFactory;

    protected $table = 'rag_metrics';

    protected $fillable = [
        'query_id',
        'query',
        'user_id',
        'retrieval_precision',
        'retrieval_recall',
        'answer_accuracy',
        'confidence_score',
        'validation_results',
        'latency_ms',
        'tokens_input',
        'tokens_output',
        'chunks_retrieved',
        'search_method',
        'validation_passed',
    ];

    protected $casts = [
        'retrieval_precision' => 'float',
        'retrieval_recall' => 'float',
        'answer_accuracy' => 'float',
        'confidence_score' => 'float',
        'validation_results' => 'array',
        'latency_ms' => 'integer',
        'tokens_input' => 'integer',
        'tokens_output' => 'integer',
        'chunks_retrieved' => 'integer',
        'validation_passed' => 'boolean',
    ];

    /**
     * Get the user who made this query.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user feedback associated with this query.
     */
    public function feedback(): HasOne
    {
        return $this->hasOne(UserFeedback::class, 'query_id', 'query_id');
    }

    /**
     * Calculate estimated cost based on token usage.
     * Assumes $0.0015 per 1K input tokens and $0.002 per 1K output tokens (GPT-3.5 rates).
     */
    public function estimatedCost(): float
    {
        $inputCost = ($this->tokens_input / 1000) * 0.0015;
        $outputCost = ($this->tokens_output / 1000) * 0.002;
        
        return round($inputCost + $outputCost, 4);
    }

    /**
     * Scope for queries within a date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope for queries with validation failures.
     */
    public function scopeFailedValidation($query)
    {
        return $query->where('validation_passed', false);
    }

    /**
     * Scope for high-latency queries (> 5 seconds).
     */
    public function scopeHighLatency($query, $thresholdMs = 5000)
    {
        return $query->where('latency_ms', '>', $thresholdMs);
    }

    /**
     * Scope for low confidence queries.
     */
    public function scopeLowConfidence($query, $threshold = 0.5)
    {
        return $query->where('confidence_score', '<', $threshold);
    }
}
