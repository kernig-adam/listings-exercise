<?php

namespace App\Http\Controllers;

use App\Http\Resources\AlertResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // One card per home (its newest alert). Refreshed alerts move up via
        // updated_at; `id` breaks ties so paging never repeats or skips a home.
        $alerts = $user->alerts()
            ->latestPerListing()
            ->with(['listing.branch', 'savedSearches.regions', 'savedSearches.propertyTypes'])
            ->latest('updated_at')
            ->orderByDesc('id')
            ->paginate(15);

        $listingIds = $alerts->getCollection()->pluck('listing_id');

        // One grouped query for the whole page, not one per card. Read before
        // marking, so a card says "new" when any alert for its home was unread.
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

        // Every alert for a shown home is read, not only the card's own, or older
        // ones would keep the unread badge up. toBase() skips updated_at, which
        // would re-sort what was just read.
        $user->alerts()->unread()
            ->whereIn('listing_id', $listingIds)
            ->toBase()
            ->update(['read_at' => now()]);

        return Inertia::render('Alerts/Index', [
            'alertList' => AlertResource::collection($alerts),
        ]);
    }
}
