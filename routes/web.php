<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BeerController;
use App\Http\Controllers\BeerPriceController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Publican;
use App\Http\Controllers\VenueController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public: discovery & search (flowchart: Home Screen, Search bar, Filter)
|--------------------------------------------------------------------------
*/
Route::get('/', HomeController::class)->name('home');

Route::middleware('throttle:geo')->group(function () {
    Route::get('/venues/nearby', [VenueController::class, 'nearby'])->name('venues.nearby');
    Route::get('/beers/cheapest', [BeerController::class, 'cheapest'])->name('beers.cheapest');
    Route::get('/venues/lookup', [VenueController::class, 'lookup'])->name('venues.lookup');
    Route::get('/beers/lookup', [BeerController::class, 'lookup'])->name('beers.lookup');
});
Route::get('/venues/search', [VenueController::class, 'search'])->name('venues.search');
Route::get('/venues/{venue}', [VenueController::class, 'show'])->whereNumber('venue')->name('venues.show');
Route::get('/u/{user:username}', [ProfileController::class, 'public'])->name('profile.public');

/*
|--------------------------------------------------------------------------
| Guest auth
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:auth');
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:auth');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:auth')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:auth')->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Authenticated punters (flowchart: camera / post price / review)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/prices/create', [BeerPriceController::class, 'create'])->name('prices.create');
    Route::post('/prices', [BeerPriceController::class, 'store'])->middleware('throttle:contributions')->name('prices.store');

    Route::get('/beers/suggest', [BeerController::class, 'create'])->name('beers.create');
    Route::post('/beers', [BeerController::class, 'store'])->middleware('throttle:contributions')->name('beers.store');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');

    // Any signed-in user may lodge a publican claim for a venue.
    Route::get('/publican/claim', [Publican\ClaimController::class, 'create'])->name('publican.claim.create');
    Route::post('/publican/claim', [Publican\ClaimController::class, 'store'])->middleware('throttle:contributions')->name('publican.claim.store');
    Route::get('/publican/claim/{claim}/verify', [Publican\ClaimController::class, 'verify'])
        ->middleware('signed')->name('publican.claim.verify');
});

/*
|--------------------------------------------------------------------------
| Publicans
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:publican,admin'])->prefix('publican')->name('publican.')->group(function () {
    Route::get('/', [Publican\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/venues/{venue}/edit', [Publican\VenueController::class, 'edit'])->name('venues.edit');
    Route::put('/venues/{venue}', [Publican\VenueController::class, 'update'])->name('venues.update');
    Route::post('/venues/{venue}/prices', [Publican\OfficialPriceController::class, 'store'])->name('prices.store');
    Route::put('/replies/{beerPrice}', [Publican\ReviewReplyController::class, 'upsert'])->name('replies.upsert');
    Route::delete('/replies/{beerPrice}', [Publican\ReviewReplyController::class, 'destroy'])->name('replies.destroy');
});

/*
|--------------------------------------------------------------------------
| Administrators
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    Route::get('/beers', [Admin\BeerController::class, 'index'])->name('beers.index');
    Route::post('/beers', [Admin\BeerController::class, 'store'])->name('beers.store');
    Route::post('/beers/{beer}/approve', [Admin\BeerController::class, 'approve'])->name('beers.approve');
    Route::post('/beers/{beer}/reject', [Admin\BeerController::class, 'reject'])->name('beers.reject');

    Route::get('/prices', [Admin\PriceModerationController::class, 'index'])->name('prices.index');
    Route::post('/prices/{beerPrice}/flag', [Admin\PriceModerationController::class, 'flag'])->name('prices.flag');
    Route::post('/prices/{beerPrice}/unflag', [Admin\PriceModerationController::class, 'unflag'])->name('prices.unflag');
    Route::post('/prices/{beerPrice}/remove-photo', [Admin\PriceModerationController::class, 'removePhoto'])->name('prices.remove-photo');
    Route::delete('/prices/{beerPrice}', [Admin\PriceModerationController::class, 'destroy'])->name('prices.destroy');
    Route::post('/prices/{beerPrice}/restore', [Admin\PriceModerationController::class, 'restore'])->withTrashed()->name('prices.restore');

    Route::resource('venues', Admin\VenueController::class)->except(['show', 'destroy']);
    Route::post('/venues/{venue}/toggle', [Admin\VenueController::class, 'toggle'])->name('venues.toggle');

    Route::get('/claims', [Admin\ClaimController::class, 'index'])->name('claims.index');
    Route::post('/claims/{claim}/approve', [Admin\ClaimController::class, 'approve'])->name('claims.approve');
    Route::post('/claims/{claim}/reject', [Admin\ClaimController::class, 'reject'])->name('claims.reject');

    Route::get('/sync', [Admin\SyncController::class, 'index'])->name('sync.index');
    Route::post('/sync', [Admin\SyncController::class, 'run'])->name('sync.run');

    Route::get('/points', [Admin\PointsController::class, 'index'])->name('points.index');
    Route::post('/users/{user}/ban', [Admin\PointsController::class, 'toggleBan'])->name('users.ban');
});
