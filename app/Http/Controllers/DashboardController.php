<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Player;
use App\Models\Snapshot;
use App\Services\EarningsCalculator;
use App\Services\Leaderboard;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $me = Player::me();
        $boards = Location::orderBy('name')->get()->map(fn ($l) => new Leaderboard($l));

        $mine = $me ? $boards->filter(fn ($b) => $b->rowFor($me)) : collect();
        $leading = $mine->filter(fn ($b) => $b->rowFor($me)->rank === 1)->values();
        $chasing = $mine->filter(fn ($b) => $b->rowFor($me)->rank > 1)
            ->sortBy(fn ($b) => $b->parcelsToLead($me))
            ->values();

        return view('dashboard', [
            'me' => $me,
            'boards' => $boards,
            'leading' => $leading,
            'chasing' => $chasing,
            'stale' => $boards->filter(fn ($b) => $b->latest)->sortBy(fn ($b) => $b->latest->recorded_on)->take(5)->values(),
            'recent' => Snapshot::with('location')->orderByDesc('recorded_on')->orderByDesc('id')->take(8)->get(),
            'earnings' => (new EarningsCalculator)->current(),
        ]);
    }
}
