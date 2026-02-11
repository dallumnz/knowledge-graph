<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ChunkKeyword model.
 *
 * Represents weighted keywords associated with text chunks.
 * Used for SEO, filtering, and faceted search in the RAG pipeline.
 *
 * @property int $id
 * @property int $chunk_id
 * @property string $keyword
 * @property float $weight
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ChunkKeyword extends Model
{
    use HasFactory;

    protected $fillable = ['chunk_id', 'keyword', 'weight'];

    protected function casts(): array
    {
        return [
            'weight' => 'float',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the chunk that owns this keyword.
     */
    public function chunk(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'chunk_id');
    }

    /**
     * Scope for keywords above a certain weight threshold.
     */
    public function scopeSignificant(float $threshold = 0.1): \Illuminate\Database\Eloquent\Builder
    {
        return $this->where('weight', '>=', $threshold);
    }

    /**
     * Scope for ordering by weight descending.
     */
    public function scopeHighestRated(): \Illuminate\Database\Eloquent\Builder
    {
        return $this->orderBy('weight', 'desc');
    }

    /**
     * Find keywords matching a search term.
     */
    public static function search(string $term): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('keyword', 'ILIKE', "%{$term}%")
            ->highestRated()
            ->get();
    }
}
