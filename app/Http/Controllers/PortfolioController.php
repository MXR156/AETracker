<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use App\Models\Holding;
use App\Services\EarningsCalculator;
use Illuminate\Http\Request;

class PortfolioController extends Controller
{
    public function index()
    {
        $calc = new EarningsCalculator;
        $holdings = Holding::orderByDesc('recorded_on')->orderByDesc('id')->get();
        $current = $holdings->first();
        $badges = Badge::orderByDesc('owned')->orderBy('category')->orderBy('name')->get();
        $owned = $badges->where('owned', true);

        // Estimated daily earnings at each point in history (using today's badges and settings).
        $history = $holdings->reverse()->values()->map(fn ($h) => [
            'date' => $h->recorded_on->format('Y-m-d'),
            'parcels' => $h->total(),
            'per_day' => round($calc->calculate($h->counts(), $owned->count(), (float) $owned->sum('boost_percent'))['per_day'], 6),
        ]);

        return view('portfolio.index', [
            'calc' => $calc,
            'current' => $current,
            'holdings' => $holdings,
            'badges' => $badges,
            'earnings' => $calc->current($current),
            'history' => $history,
            'settings' => $calc->settings,
        ]);
    }

    public function planner(Request $request)
    {
        $calc = new EarningsCalculator;
        $current = Holding::current();
        $counts = $current ? $current->counts() : array_fill_keys(EarningsCalculator::RARITIES, 0);
        $owned = Badge::where('owned', true)->get();

        $amounts = collect(explode(',', (string) $request->query('amounts', '1,10,25,50,100')))
            ->map(fn ($n) => (int) trim($n))->filter(fn ($n) => $n > 0 && $n <= 100000)->unique()->sort()->values()->all() ?: [1, 10, 50];

        if ($request->filled('boost_hours')) {
            $calc->settings['boost_hours_per_day'] = (string) max(0, min(24, (float) $request->query('boost_hours')));
        }

        $plan = $calc->plan(
            $counts,
            $owned->count(),
            (float) $owned->sum('boost_percent'),
            Badge::where('owned', false)->orderBy('cost')->get(),
            $amounts,
        );

        // How earnings change as you add parcels - shows where boost tiers kick in.
        $mix = $calc->expectedNewParcelMix();
        $curve = [];
        $max = max(100, (int) (array_sum($counts) * 1.5));
        $step = max(1, (int) ceil($max / 60));
        for ($extra = 0; $extra <= $max; $extra += $step) {
            $c = $counts;
            foreach ($mix as $rarity => $share) {
                $c[$rarity] = ($c[$rarity] ?? 0) + $extra * $share;
            }
            $r = $calc->calculate($c, $owned->count(), (float) $owned->sum('boost_percent'));
            $curve[] = ['parcels' => array_sum($counts) + $extra, 'per_day' => round($r['per_day'], 6), 'boost' => $r['boost_multiplier']];
        }

        return view('portfolio.planner', [
            'calc' => $calc,
            'plan' => $plan,
            'amounts' => $amounts,
            'curve' => $curve,
            'settings' => $calc->settings,
            'hasHoldings' => (bool) $current,
        ]);
    }
}
