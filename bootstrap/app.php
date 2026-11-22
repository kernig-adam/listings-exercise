<?php

use App\Http\Middleware\ActAsDemoUser;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            // NOTE (take-home): auth is stubbed. Every request is resolved as the
            // seeded demo user so you can build user-scoped features (e.g. saved
            // searches) against auth()->user() without wiring up a real login flow.
            // See the README ("Authentication") for the rationale.
            //
            // This must run before HandleInertiaRequests, which reads the user
            // when it builds the shared props.
            ActAsDemoUser::class,

            HandleInertiaRequests::class,
        ]);

        // Laravel sorts `throttle` ahead of unlisted middleware, so it would run
        // before the stub and key (and log) every limit by IP, not by user. Real
        // `auth` already sorts before it; this gives the stub the same position.
        $middleware->prependToPriorityList(before: ThrottleRequests::class, prepend: ActAsDemoUser::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A throttled write goes back to the page with a message the UI shows in
        // place, not a raw 429 error page. JSON clients still get the 429.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            Log::warning('Rate limit hit', ['user_id' => $request->user()?->getAuthIdentifier(), 'route' => $request->route()?->getName()]);

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return null;
            }

            return back()->withErrors(['criteria' => 'You are doing that too quickly. Please wait a moment and try again.']);
        });
    })->create();
