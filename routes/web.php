<?php

use App\Http\Controllers\ListingController;
use App\Http\Controllers\SavedSearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ListingController::class, 'index'])->name('listings.index');
Route::get('/listings/{listing}', [ListingController::class, 'show'])->name('listings.show');

Route::get('/saved-searches', [SavedSearchController::class, 'index'])->name('saved-searches.index');
Route::middleware('throttle:saved-searches')->group(function () {
    Route::post('/saved-searches', [SavedSearchController::class, 'store'])->name('saved-searches.store');
    Route::delete('/saved-searches/{id}', [SavedSearchController::class, 'destroy'])
        ->whereNumber('id')
        ->name('saved-searches.destroy');
});
