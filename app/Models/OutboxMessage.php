<?php

namespace App\Models;

use Database\Factories\OutboxMessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A domain event written in the same transaction as the change that caused it.
 * The relay publishes it and stamps sent_at.
 *
 * @property int $id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property Carbon|null $sent_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class OutboxMessage extends Model
{
    /** @use HasFactory<OutboxMessageFactory> */
    use HasFactory;

    protected $fillable = ['type', 'payload', 'sent_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * Messages the relay still has to publish.
     *
     * @param  Builder<OutboxMessage>  $query
     */
    public function scopeUnsent(Builder $query): void
    {
        $query->whereNull('sent_at');
    }
}
