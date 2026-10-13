<?php

use App\Http\Controllers\BadgeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HoldingController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SnapshotController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

// Location leaderboards
Route::resource('locations', LocationController::class);
Route::resource('locations.snapshots', SnapshotController::class)->except(['index', 'show'])->shallow();

// Import a leaderboard from the Atlas Earth web map (bookmarklet)
Route::get('import', [ImportController::class, 'index'])->name('import.index');
Route::post('import/atlas-earth', [ImportController::class, 'receive'])->name('import.receive');
Route::get('import/{token}', [ImportController::class, 'review'])->name('import.review');
Route::post('import/{token}', [ImportController::class, 'save'])->name('import.save');

// Players
Route::get('players', [PlayerController::class, 'index'])->name('players.index');
Route::get('players/{player}', [PlayerController::class, 'show'])->name('players.show');
Route::put('players/{player}', [PlayerController::class, 'update'])->name('players.update');
Route::post('players/{player}/me', [PlayerController::class, 'markMe'])->name('players.me');
Route::delete('players/{player}', [PlayerController::class, 'destroy'])->name('players.destroy');

// My parcels, earnings and planner
Route::get('my-parcels', [PortfolioController::class, 'index'])->name('portfolio.index');
Route::get('my-parcels/planner', [PortfolioController::class, 'planner'])->name('portfolio.planner');
Route::post('my-parcels/holdings', [HoldingController::class, 'store'])->name('holdings.store');
Route::post('my-parcels/holdings/add', [HoldingController::class, 'add'])->name('holdings.add');
Route::delete('my-parcels/holdings/{holding}', [HoldingController::class, 'destroy'])->name('holdings.destroy');
Route::post('my-parcels/badges', [BadgeController::class, 'store'])->name('badges.store');
Route::put('my-parcels/badges/{badge}', [BadgeController::class, 'update'])->name('badges.update');
Route::post('my-parcels/badges/{badge}/toggle', [BadgeController::class, 'toggle'])->name('badges.toggle');
Route::delete('my-parcels/badges/{badge}', [BadgeController::class, 'destroy'])->name('badges.destroy');

Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
