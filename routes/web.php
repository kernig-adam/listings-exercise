<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\SavedSearchController;
use App\Http\Middleware\RequireUser;
use Illuminate\Support\Facades\Route;

// Public: anyone can browse listings.
Route::get('/', [ListingController::class, 'index'])->name('listings.index');
Route::get('/listings/{listing}', [ListingController::class, 'show'])->name('listings.show');

// User-scoped: a guest gets a 401 before any controller runs. Whose data is
// visible is enforced separately, by loading through the user's relationships.
Route::middleware(RequireUser::class)->group(function () {
    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');

    Route::get('/saved-searches', [SavedSearchController::class, 'index'])->name('saved-searches.index');
    Route::middleware('throttle:saved-searches')->group(function () {
        Route::post('/saved-searches', [SavedSearchController::class, 'store'])->name('saved-searches.store');
        Route::delete('/saved-searches/{id}', [SavedSearchController::class, 'destroy'])
            ->whereNumber('id')
            ->name('saved-searches.destroy');
    });
});
