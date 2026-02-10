<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pgvector\Laravel\Vector;

class Embedding extends Model
{
    use HasFactory;

    protected $primaryKey = 'node_id';

    public $incrementing = false;

    protected $fillable = ['node_id', 'embedding'];

    protected $casts = [
        'embedding' => Vector::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
