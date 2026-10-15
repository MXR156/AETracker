<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoostRegionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HoldingController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ProjectionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SnapshotController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// IDs in URLs are numbers, so /locations/create isn't mistaken for a location called "create".
Route::pattern('location', '[0-9]+');
Route::pattern('snapshot', '[0-9]+');
Route::pattern('player', '[0-9]+');
Route::pattern('user', '[0-9]+');
Route::pattern('region', '[0-9]+');
Route::pattern('holding', '[0-9]+');

// ---- Public: anyone can look at the shared leaderboards ----------------------------------------
// The home page is the public overview (with a login box) for visitors, and your dashboard once logged in.
Route::get('/', DashboardController::class)->name('dashboard');
Route::get('locations', [LocationController::class, 'index'])->name('locations.index');
Route::get('locations/{location}', [LocationController::class, 'show'])->name('locations.show');
Route::get('players', [PlayerController::class, 'index'])->name('players.index');
Route::get('players/{player}', [PlayerController::class, 'show'])->name('players.show');

// Logging in and signing up (sign-up can be switched off by an admin in Settings).
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:20,1');
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1');
});
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// The bookmarklet posts here from the Atlas Earth site, so it can't log in or send a CSRF token. It only
// stages the data; the review page (behind the login) is where it gets saved.
Route::post('import/atlas-earth', [ImportController::class, 'receive'])->middleware('throttle:30,1')->name('import.receive');

// ---- Logged in: your own stats, and adding to the shared leaderboards ------------------------
Route::middleware('auth')->group(function () {
    // Everyone can add and edit the shared leaderboards; deleting is for admins (see below).
    Route::get('locations/create', [LocationController::class, 'create'])->name('locations.create');
    Route::post('locations', [LocationController::class, 'store'])->name('locations.store');
    Route::get('locations/{location}/edit', [LocationController::class, 'edit'])->name('locations.edit');
    Route::put('locations/{location}', [LocationController::class, 'update'])->name('locations.update');
    Route::resource('locations.snapshots', SnapshotController::class)->except(['index', 'show', 'destroy'])->shallow();

    // Import a leaderboard from the Atlas Earth web map (bookmarklet)
    Route::get('import', [ImportController::class, 'index'])->name('import.index');
    Route::get('import/{token}', [ImportController::class, 'review'])->name('import.review');
    Route::post('import/{token}', [ImportController::class, 'save'])->name('import.save');

    // Players
    Route::put('players/{player}', [PlayerController::class, 'update'])->name('players.update');
    Route::post('players/{player}/me', [PlayerController::class, 'markMe'])->name('players.me');
    Route::delete('players/{player}/me', [PlayerController::class, 'unmarkMe'])->name('players.unme');
    // My parcels, badges and planner (my parcels and earnings are shown on the dashboard)
    Route::redirect('my-parcels', '/'); // My Parcels is now part of the dashboard
    Route::get('my-parcels/planner', [PortfolioController::class, 'planner'])->name('portfolio.planner');
    Route::post('my-parcels/holdings', [HoldingController::class, 'store'])->name('holdings.store');
    Route::delete('my-parcels/holdings/{holding}', [HoldingController::class, 'destroy'])->name('holdings.destroy');
    Route::put('my-parcels/badges', [PortfolioController::class, 'badges'])->name('portfolio.badges');

    // Projection: AB income -> parcels -> rent over the coming months
    Route::get('projection', [ProjectionController::class, 'index'])->name('projection.index');
    Route::put('projection', [ProjectionController::class, 'update'])->name('projection.update');

    // My account
    Route::get('account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('account', [AccountController::class, 'update'])->name('account.update');
    Route::put('account/preferences', [AccountController::class, 'preferences'])->name('account.preferences');

    // Admins only: who can log in, the game settings, and deleting shared leaderboard data
    Route::middleware('can:admin')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}/player', [UserController::class, 'unlinkPlayer'])->name('users.unlink');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('settings/countries', [BoostRegionController::class, 'store'])->name('regions.store');
        Route::put('settings/countries/{region}', [BoostRegionController::class, 'update'])->name('regions.update');
        Route::post('settings/countries/{region}/default', [BoostRegionController::class, 'makeDefault'])->name('regions.default');
        Route::delete('settings/countries/{region}', [BoostRegionController::class, 'destroy'])->name('regions.destroy');

        Route::delete('locations/{location}', [LocationController::class, 'destroy'])->name('locations.destroy');
        Route::delete('snapshots/{snapshot}', [SnapshotController::class, 'destroy'])->name('snapshots.destroy');
        Route::delete('players/{player}', [PlayerController::class, 'destroy'])->name('players.destroy');
    });
});
