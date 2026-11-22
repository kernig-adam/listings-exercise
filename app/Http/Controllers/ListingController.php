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

        return Inertia::render('Listings/Index', [
            'listings' => ListingResource::collection($listings),
            'branches' => BranchResource::collection(Branch::query()->orderBy('name')->get()),
            'propertyTypes' => PropertyType::options(),
            'regionOptions' => Branch::regions(),
            'priceOptions' => SearchCriteria::priceOptions(),
            'bedroomOptions' => SearchCriteria::bedroomOptions(),
            'filters' => $criteria->toArray(),
            'searchDescription' => $criteria->isEmpty() ? null : $criteria->description(),
            // No user only when the demo database is unseeded; nothing can be saved then.
            'currentSearchSaved' => $user !== null && ! $criteria->isEmpty() && $criteria->isSavedBy($user),
        ]);
    }

    /**
     * Only live listings are public. Drafts, under-offer and sold listings are
     * not exposed here, matching the index page.
     */
    public function show(Listing $listing): Response
    {
        abort_unless($listing->status === ListingStatus::Live, 404);

        return Inertia::render('Listings/Show', [
            'listing' => new ListingResource($listing),
        ]);
    }
}
