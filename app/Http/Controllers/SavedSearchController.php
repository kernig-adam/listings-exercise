<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavedSearchStoreRequest;
use App\Http\Resources\SavedSearchResource;
use App\Models\SavedSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SavedSearchController extends Controller
{
    public function index(Request $request): Response
    {
        // `id` breaks ties between searches created in the same second, so
        // paging never repeats or skips one.
        $searches = $request->user()->savedSearches()
            ->with(['regions', 'propertyTypes'])
            ->latest()
            ->orderByDesc('id')
            ->paginate(15);

        return Inertia::render('SavedSearches/Index', [
            'savedSearches' => SavedSearchResource::collection($searches),
            'savedSearchLimit' => SavedSearch::MAX_PER_USER,
        ]);
    }

    /**
     * Cap and duplicate checks are validated first; two simultaneous requests
     * could still slip one extra past the cap, which the throttle makes harmless.
     */
    public function store(SavedSearchStoreRequest $request): RedirectResponse
    {
        $criteria = $request->criteria();

        DB::transaction(function () use ($request, $criteria): void {
            $search = $request->user()->savedSearches()->create($criteria->attributes());

            foreach ($criteria->regions as $region) {
                $search->regions()->create(['region' => $region]);
            }
            foreach ($criteria->propertyTypes as $type) {
                $search->propertyTypes()->create(['property_type' => $type]);
            }
        });

        return back()->with('status', 'Search saved.');
    }

    /**
     * Loaded through the user's relationship so another user's search is a 404,
     * indistinguishable from one that does not exist.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $request->user()->savedSearches()->findOrFail($id)->delete();

        return back()->with('status', 'Search deleted.');
    }
}
