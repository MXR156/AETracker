<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
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

// Logging in. There's no sign-up: admins add accounts on the Users page (or with `php artisan aetracker:user`).
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:20,1');
});
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// The bookmarklet posts here from the Atlas Earth site, so it can't log in or send a CSRF token. It only
// stages the data; the review page (behind the login) is where it gets saved.
Route::post('import/atlas-earth', [ImportController::class, 'receive'])->middleware('throttle:30,1')->name('import.receive');

// Everything else needs a login.
Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    // Location leaderboards
    Route::resource('locations', LocationController::class);
    Route::resource('locations.snapshots', SnapshotController::class)->except(['index', 'show'])->shallow();

    // Import a leaderboard from the Atlas Earth web map (bookmarklet)
    Route::get('import', [ImportController::class, 'index'])->name('import.index');
    Route::get('import/{token}', [ImportController::class, 'review'])->name('import.review');
    Route::post('import/{token}', [ImportController::class, 'save'])->name('import.save');

    // Players
    Route::get('players', [PlayerController::class, 'index'])->name('players.index');
    Route::get('players/{player}', [PlayerController::class, 'show'])->name('players.show');
    Route::put('players/{player}', [PlayerController::class, 'update'])->name('players.update');
    Route::post('players/{player}/me', [PlayerController::class, 'markMe'])->name('players.me');
    Route::delete('players/{player}', [PlayerController::class, 'destroy'])->name('players.destroy');

    // My parcels, badges and planner (my parcels and earnings are shown on the dashboard)
    Route::redirect('my-parcels', '/'); // My Parcels is now part of the dashboard
    Route::get('my-parcels/planner', [PortfolioController::class, 'planner'])->name('portfolio.planner');
    Route::post('my-parcels/holdings', [HoldingController::class, 'store'])->name('holdings.store');
    Route::delete('my-parcels/holdings/{holding}', [HoldingController::class, 'destroy'])->name('holdings.destroy');
    Route::put('my-parcels/badges', [PortfolioController::class, 'badges'])->name('portfolio.badges');

    // Projection: AB income -> parcels -> rent over the coming months
    Route::get('projection', [ProjectionController::class, 'index'])->name('projection.index');
    Route::put('projection', [ProjectionController::class, 'update'])->name('projection.update');

    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

    // My account, and (for admins) who can log in
    Route::get('account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('account', [AccountController::class, 'update'])->name('account.update');
    Route::middleware('can:manage-users')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});
