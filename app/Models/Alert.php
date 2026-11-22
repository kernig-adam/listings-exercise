<?php

namespace App\Models;

use App\Enums\AlertReason;
use Database\Factories\AlertFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * One alert per user per go-live event (unique user + outbox message), linked
 * to every saved search it matched. The links go when a search is deleted; the
 * alert stays.
 *
 * @property int $id
 * @property int $user_id
 * @property int $listing_id
 * @property int $outbox_message_id
 * @property AlertReason $reason
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read Listing $listing
 * @property-read OutboxMessage $outboxMessage
 * @property-read Collection<int, SavedSearch> $savedSearches
 */
class Alert extends Model
{
    /** @use HasFactory<AlertFactory> */
    use HasFactory;

    // user_id is deliberately absent: the consumer sets it from the matched search's owner.
    protected $fillable = [
        'listing_id',
        'outbox_message_id',
        'reason',
        'read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => AlertReason::class,
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Listing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /**
     * @return BelongsTo<OutboxMessage, $this>
     */
    public function outboxMessage(): BelongsTo
    {
        return $this->belongsTo(OutboxMessage::class);
    }

    /**
     * @return BelongsToMany<SavedSearch, $this>
     */
    public function savedSearches(): BelongsToMany
    {
        return $this->belongsToMany(SavedSearch::class);
    }

    /**
     * @param  Builder<Alert>  $query
     */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }
}
