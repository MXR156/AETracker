<?php

namespace App\Http\Controllers;

use App\Models\Holding;
use App\Models\Location;
use App\Models\Player;
use App\Models\Snapshot;
use App\Services\EarningsCalculator;
use App\Services\Leaderboard;

/**
 * The one home page: my parcels, badges and earnings (and where to update them),
 * plus how I'm doing across the locations I track.
 */
class DashboardController extends Controller
{
    public function __invoke()
    {
        // Locations
        $me = Player::me();
        $boards = Location::orderBy('name')->get()->map(fn ($l) => new Leaderboard($l));

        $mine = $me ? $boards->filter(fn ($b) => $b->rowFor($me)) : collect();
        $leading = $mine->filter(fn ($b) => $b->rowFor($me)->rank === 1)->values();
        $chasing = $mine->filter(fn ($b) => $b->rowFor($me)->rank > 1)
            ->sortBy(fn ($b) => $b->parcelsToLead($me))
            ->values();

        // My parcels, badges and earnings
        $calc = new EarningsCalculator;
        $holdings = Holding::for()->orderByDesc('recorded_on')->orderByDesc('id')->get();
        $current = $holdings->first();
        $badges = $calc->badgesOwned();

        // Estimated daily earnings at each point in history (using today's badges and settings).
        $history = $holdings->reverse()->values()->map(fn ($h) => [
            'date' => $h->recorded_on->format('Y-m-d'),
            'parcels' => $h->total(),
            'per_day' => round($calc->calculate($h->counts(), $badges)['per_day'], 11),
        ]);

        return view('dashboard', [
            'me' => $me,
            'boards' => $boards,
            'leading' => $leading,
            'chasing' => $chasing,
            'stale' => $boards->filter(fn ($b) => $b->latest)->sortBy(fn ($b) => $b->latest->recorded_on)->take(5)->values(),
            'recent' => Snapshot::with('location')->orderByDesc('recorded_on')->orderByDesc('id')->take(8)->get(),
            'calc' => $calc,
            'current' => $current,
            'holdings' => $holdings,
            'badges' => $badges,
            'badgeTiers' => $calc->badgeTiers(),
            'earnings' => $calc->current($current),
            'history' => $history,
        ]);
    }
}
