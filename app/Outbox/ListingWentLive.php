<?php

namespace App\Outbox;

use App\Enums\AlertReason;
use App\Enums\ListingStatus;
use App\Models\Alert;
use App\Models\Listing;
use App\Models\OutboxMessage;
use App\Models\SavedSearch;
use Illuminate\Support\Facades\DB;

/**
 * Consumer for `listing.went_live`: one alert per matching user per go-live.
 * Delivery is at-least-once, so everything here is safe to run twice.
 */
class ListingWentLive
{
    public function handle(OutboxMessage $message): void
    {
        // Reload and re-check: the listing may have changed since the message was written.
        $listing = Listing::query()->with('branch')->find($message->payload['listing_id']);
        if ($listing === null || $listing->status !== ListingStatus::Live) {
            return;
        }

        // One query for every search this listing satisfies, grouped per user.
        $searchIdsByUser = SavedSearch::query()->matchingListing($listing)->get(['id', 'user_id'])
            ->groupBy('user_id')
            ->map(fn ($searches) => $searches->pluck('id')->all());
        if ($searchIdsByUser->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($message, $listing, $searchIdsByUser): void {
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
                } else {
                    $alert = (new Alert([
                        'listing_id' => $listing->id,
                        'outbox_message_id' => $message->id,
                        'reason' => $alertedBefore->contains($userId) ? AlertReason::BackOnMarket : AlertReason::NewListing,
                    ]))->forceFill(['user_id' => $userId]);
                    $alert->save();
                }

                $alert->savedSearches()->syncWithoutDetaching($searchIds);
            }
        });
    }
}
