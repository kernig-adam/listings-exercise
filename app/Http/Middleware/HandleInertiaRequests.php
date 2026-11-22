<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->only('id', 'name', 'email'),
            ],
            // A closure, so it is counted when the response is built: after the
            // alerts page has marked its alerts read.
            'alerts' => [
                'unread' => fn (): int => $request->user()?->alerts()->unread()->count() ?? 0,
            ],
            // One-shot messages (e.g. "Search saved.") for the UI to announce.
            'flash' => [
                'status' => $request->session()->get('status'),
            ],
        ];
    }
}
