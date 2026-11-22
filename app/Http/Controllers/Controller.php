<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * $request->user() is nullable; RequireUser keeps guests out, but a route
     * that misses it must answer 401, not crash on null. Not #[CurrentUser]:
     * that reads the auth guard, which the ActAsDemoUser stub never sets.
     */
    protected function currentUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
