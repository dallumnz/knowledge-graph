<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * User Feedback Model
 *
 * Stores explicit user feedback on RAG responses.
 * Links to rag_metrics via query_id for correlation analysis.
 *
 * @property int $id
 * @property string $query_id Unique identifier for the query
 * @property int $user_id ID of the user providing feedback
 * @property string $rating thumbs_up or thumbs_down
 * @property string|null $comment Optional user comment
 * @property string|null $expected_answer What the user expected to see
 * @property string|null $feedback_category Category of feedback
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class UserFeedback extends Model
{
    use HasFactory;

    protected $table = 'user_feedback';

    protected $fillable = [
        'query_id',
        'user_id',
        'rating',
        'comment',
        'expected_answer',
        'feedback_category',
    ];

    protected $casts = [
        'rating' => 'string',
    ];

    /**
     * Get the user who provided this feedback.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the associated metrics record.
     */
    public function metrics(): BelongsTo
    {
        return $this->belongsTo(RagMetrics::class, 'query_id', 'query_id');
    }

    /**
     * Check if this is positive feedback.
     */
    public function isPositive(): bool
    {
        return $this->rating === 'thumbs_up';
    }

    /**
     * Check if this is negative feedback.
     */
    public function isNegative(): bool
    {
        return $this->rating === 'thumbs_down';
    }

    /**
     * Scope for positive feedback.
     */
    public function scopePositive($query)
    {
        return $query->where('rating', 'thumbs_up');
    }

    /**
     * Scope for negative feedback.
     */
    public function scopeNegative($query)
    {
        return $query->where('rating', 'thumbs_down');
    }

    /**
     * Scope for feedback within a date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
