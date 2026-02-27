<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pgvector\Laravel\Distance;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

class AiTicketEmbedding extends Model
{
    use HasNeighbors;

    protected $table = 'ai_ticket_embeddings';

    protected $fillable = [
        'ticket_id',
        'embedding',
    ];

    protected $casts = [
        'embedding' => Vector::class,
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ZdTicket::class, 'ticket_id');
    }

    public function nearestNeighbors(string $column = 'embedding', string $distance = Distance::L2): \Illuminate\Database\Eloquent\Builder
    {
        return $this->neighbors($column, $distance);
    }
}
