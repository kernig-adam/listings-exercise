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
    private const PER_PAGE = 15;

    public function index(Request $request): Response
    {
        // `id` breaks ties between searches created in the same second, so
        // paging never repeats or skips one.
        $searches = $request->user()->savedSearches()
            ->with(['regions', 'propertyTypes'])
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);

        return Inertia::render('SavedSearches/Index', [
            'savedSearchCount' => $searches->total(),
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
        $user = $request->user();
        $user->savedSearches()->findOrFail($id)->delete();

        // Deleting the last search on a page would otherwise bounce back to an
        // empty page that still has searches before it, so clamp to the last real page.
        parse_str((string) parse_url((string) $request->headers->get('referer'), PHP_URL_QUERY), $query);
        $lastPage = max(1, (int) ceil($user->savedSearches()->count() / self::PER_PAGE));
        $page = min(max(1, (int) ($query['page'] ?? 1)), $lastPage);

        return redirect()
            ->route('saved-searches.index', $page > 1 ? ['page' => $page] : [])
            ->with('status', 'Search deleted.');
    }
}
