<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Edge extends Model
{
    use HasFactory;

    protected $fillable = ['source_id', 'target_id', 'relation', 'weight'];

    protected $casts = [
        'weight' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'source_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'target_id');
    }
}
