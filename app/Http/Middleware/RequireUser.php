<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authentication guard for user-scoped routes: no user, no entry.
 *
 * Laravel's `auth` middleware checks the auth guard, but the take-home stub
 * (ActAsDemoUser) only sets the request's user resolver, so `auth` would refuse
 * even the demo user. This checks $request->user(), the same source every
 * controller uses. With real authentication in place, swap it for `auth`
 * (which would redirect a guest to the login page instead of a 401).
 */
class RequireUser
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->user() === null, 401);

        return $next($request);
    }
}
