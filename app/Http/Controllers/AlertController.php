<?php

namespace App\Http\Controllers;

use App\Http\Requests\AlertsReadRequest;
use App\Http\Resources\AlertResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    /**
     * The signed-in buyer's alerts, one card per home, newest first.
     * Each home's alert history is loaded in one query for the whole page.
     */
    public function index(Request $request): Response
    {
        $user = $this->currentUser($request);

        // One card per home (its newest alert). Refreshed alerts move up via
        // updated_at; `id` breaks ties so paging never repeats or skips a home.
        $alerts = $user->alerts()
            ->latestPerListing()
            ->with(['listing.branch', 'savedSearches.regions', 'savedSearches.propertyTypes'])
            ->latest('updated_at')
            ->orderByDesc('id')
            ->paginate(15);

        $listingIds = $alerts->getCollection()->pluck('listing_id');

        // One grouped query for the whole page, not one per card. A card is "new"
        // while any alert for its home is unread.
        $history = $user->alerts()
            ->whereIn('listing_id', $listingIds)
            ->groupBy('listing_id')
            ->selectRaw('listing_id, min(created_at) as first_alerted_at, count(*) as alert_count, sum(case when read_at is null then 1 else 0 end) as unread_count')
            ->toBase()
            ->get()
            ->keyBy('listing_id');

        foreach ($alerts as $alert) {
            $alert->setAttribute('history', $history[$alert->listing_id]);
        }

        Log::channel('activity')->info('alerts.viewed', [
            'user_id' => $user->id,
            'homes_shown' => $alerts->count(),
            'new_count' => $alerts->filter(fn ($alert) => $history[$alert->listing_id]->unread_count > 0)->count(),
        ]);

        return Inertia::render('Alerts/Index', [
            'alertList' => AlertResource::collection($alerts),
        ]);
    }

    /**
     * Marks the user's alerts for these homes as read.
     * Returns 404 and changes nothing if any of the ids isn't theirs.
     */
    public function markRead(AlertsReadRequest $request): RedirectResponse
    {
        $user = $this->currentUser($request);
        $ids = $request->alertIds();

        // 404, not 422: an id that is not the user's gets the same answer whether
        // it belongs to someone else or does not exist, so nothing is confirmed.
        // Checked before any write, so a bad id changes nothing.
        $listingIds = $user->alerts()->whereKey($ids)->pluck('listing_id', 'id');
        if ($listingIds->count() !== count($ids)) {
            Log::warning('Alert ids not owned by user', [
                'user_id' => $user->id,
                'requested' => count($ids),
                'found' => $listingIds->count(),
            ]);
            abort(404);
        }

        // Every unread alert for those homes is read, not only the cards' own, or
        // older ones would keep the unread badge up. toBase() skips updated_at,
        // which would re-sort what was just read.
        $user->alerts()->unread()
            ->whereIn('listing_id', $listingIds)
            ->toBase()
            ->update(['read_at' => now()]);

        Log::channel('activity')->info('alerts.marked_read', ['user_id' => $user->id, 'homes' => $listingIds->unique()->count()]);

        return back(303);
    }
}
