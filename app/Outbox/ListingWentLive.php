<?php

namespace App\Outbox;

use App\Enums\AlertReason;
use App\Enums\ListingStatus;
use App\Models\Alert;
use App\Models\Listing;
use App\Models\OutboxMessage;
use App\Models\SavedSearch;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Consumer for `listing.went_live`: one alert per matching user per go-live.
 * Delivery is at-least-once, so everything here is safe to run twice.
 */
class ListingWentLive
{
    public function handle(OutboxMessage $message): void
    {
        $listingId = $this->listingId($message);

        // Reload and re-check: the listing may have changed since the message was written.
        $listing = Listing::query()->with('branch')->find($listingId);
        if ($listing === null) {
            $this->skip($message, $listingId, 'not_found');

            return;
        }
        if ($listing->status !== ListingStatus::Live) {
            $this->skip($message, $listingId, 'not_live');

            return;
        }

        // One query for every search this listing satisfies, grouped per user.
        $searchIdsByUser = SavedSearch::query()->matchingListing($listing)->get(['id', 'user_id'])
            ->groupBy('user_id')
            ->map(fn ($searches) => $searches->pluck('id')->all());
        if ($searchIdsByUser->isEmpty()) {
            $this->skip($message, $listingId, 'no_matches');

            return;
        }

        $created = 0;
        $refreshed = 0;

        DB::transaction(function () use ($message, $listing, $searchIdsByUser, &$created, &$refreshed): void {
            $forListing = Alert::query()
                ->where('listing_id', $listing->id)
                ->whereIn('user_id', $searchIdsByUser->keys())
                ->get();

            // Already handled for this message: a reprocess changes nothing.
            $handled = $forListing->where('outbox_message_id', $message->id)->pluck('user_id')->all();
            $unreadByUser = $forListing->whereNull('read_at')->keyBy('user_id');
            $alertedBefore = $forListing->pluck('user_id')->unique();

            foreach ($searchIdsByUser->except($handled) as $userId => $searchIds) {
                $alert = $unreadByUser->get($userId);

                if ($alert !== null) {
                    // Flip-flop guard: bump the unread alert instead of stacking another.
                    $alert->touch();
                    $refreshed++;
                } else {
                    $alert = (new Alert([
                        'listing_id' => $listing->id,
                        'outbox_message_id' => $message->id,
                        'reason' => $alertedBefore->contains($userId) ? AlertReason::BackOnMarket : AlertReason::NewListing,
                    ]))->forceFill(['user_id' => $userId]);
                    $alert->save();
                    $created++;
                }

                $alert->savedSearches()->syncWithoutDetaching($searchIds);
            }
        });

        Log::info('Alerts generated', [
            'outbox_message_id' => $message->id,
            'listing_id' => $listing->id,
            'matched_searches' => $searchIdsByUser->flatten()->count(),
            'users' => $searchIdsByUser->count(),
            'created' => $created,
            'refreshed' => $refreshed,
        ]);
    }

    /**
     * The payload crosses a trust boundary (in production it arrives off a queue),
     * so it is checked before anything acts on it. Throwing leaves the message
     * unsent and lets the relay log it as a failure.
     */
    private function listingId(OutboxMessage $message): int
    {
        $payload = $message->payload;
        $listingId = $payload['listing_id'] ?? null;
        $occurredAt = $payload['occurred_at'] ?? null;

        if (! is_int($listingId) || $listingId < 1
            || ! is_string($occurredAt) || DateTimeImmutable::createFromFormat(DATE_ATOM, $occurredAt) === false) {
            throw new InvalidArgumentException('Malformed listing.went_live payload');
        }

        return $listingId;
    }

    private function skip(OutboxMessage $message, int $listingId, string $reason): void
    {
        Log::info('Listing went live, no alerts', [
            'outbox_message_id' => $message->id,
            'listing_id' => $listingId,
            'reason' => $reason,
        ]);
    }
}
