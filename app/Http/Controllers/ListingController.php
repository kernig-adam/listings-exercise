<?php

namespace App\Http\Controllers;

use App\Enums\ListingStatus;
use App\Enums\PropertyType;
use App\Http\Requests\ListingIndexRequest;
use App\Http\Resources\BranchResource;
use App\Http\Resources\ListingResource;
use App\Models\Branch;
use App\Models\Listing;
use App\Support\SearchCriteria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ListingController extends Controller
{
    /**
     * List live listings, with optional filters.
     */
    public function index(ListingIndexRequest $request): Response
    {
        $criteria = $request->criteria();
        $user = $request->user();

        $query = Listing::query()->live()->matching($criteria);

        // `id` is a tiebreaker: without it, listings sharing a `listed_at` can be
        // ordered differently between page requests, which duplicates or skips
        // rows as you page through.
        $listings = $query->latest('listed_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        // Every full view is logged, filtered or not: unfiltered views are the
        // baseline the filtered ones are measured against. An Inertia partial
        // reload re-runs this method, so it is skipped to avoid counting a view twice.
        if (! $request->hasHeader('X-Inertia-Partial-Data')) {
            Log::channel('activity')->info('search.performed', [
                'user_id' => $user?->id,
                'filters' => $criteria->toArray(),
                'result_count' => $listings->total(),
                'page' => $listings->currentPage(),
            ]);
        }

        return Inertia::render('Listings/Index', [
            'listings' => ListingResource::collection($listings),
            'branches' => BranchResource::collection(Branch::query()->orderBy('name')->get()),
            'propertyTypes' => PropertyType::options(),
            'regionOptions' => Branch::regions(),
            'priceOptions' => SearchCriteria::priceOptions(),
            'bedroomOptions' => SearchCriteria::bedroomOptions(),
            'bathroomOptions' => SearchCriteria::bathroomOptions(),
            'filters' => $criteria->toArray(),
            // No user only when the demo database is unseeded; nothing can be saved then.
            'currentSearchSaved' => $user !== null && ! $criteria->isEmpty() && $criteria->isSavedBy($user),
        ]);
    }

    /**
     * Only live listings are public. Drafts, under-offer and sold listings are
     * not exposed here, matching the index page.
     */
    public function show(Request $request, Listing $listing): Response
    {
        abort_unless($listing->status === ListingStatus::Live, 404);

        // Only the alert cards send from=alert; anything else is plain browsing,
        // and the raw value is never logged.
        Log::channel('activity')->info('listing.viewed', [
            'user_id' => $request->user()?->id,
            'listing_id' => $listing->id,
            'source' => $request->query('from') === 'alert' ? 'alert' : 'browse',
        ]);

        return Inertia::render('Listings/Show', [
            'listing' => new ListingResource($listing),
        ]);
    }
}
