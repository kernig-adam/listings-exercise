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
        // Refreshed alerts move up via updated_at; `id` breaks ties so paging
        // never repeats or skips an alert.
        $alerts = $request->user()->alerts()
            ->with(['listing.branch', 'savedSearches.regions', 'savedSearches.propertyTypes'])
            ->latest('updated_at')
            ->orderByDesc('id')
            ->paginate(15);

        // The resource reads read_at only when the response is built, after this
        // method returns, so these in-memory alerts still say "unread" and render
        // as new. toBase() skips updated_at, which would re-sort what was just read.
        $request->user()->alerts()->unread()
            ->whereKey($alerts->getCollection()->pluck('id'))
            ->toBase()
            ->update(['read_at' => now()]);

        return Inertia::render('Alerts/Index', [
            'alertList' => AlertResource::collection($alerts),
        ]);
    }
}
