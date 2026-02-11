<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Document extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'source_type',
        'source_path',
        'content',
        'metadata',
        'version',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'version' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the nodes (chunks) associated with this document.
     */
    public function nodes(): HasMany
    {
        return $this->hasMany(Node::class);
    }

    /**
     * Get the embeddings associated with this document through nodes.
     */
    public function embeddings(): HasManyThrough
    {
        return $this->hasManyThrough(Embedding::class, Node::class);
    }
}
