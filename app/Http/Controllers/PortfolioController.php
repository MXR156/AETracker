<?php

namespace App\Http\Controllers;

use App\Models\Holding;
use App\Models\Setting;
use App\Services\EarningsCalculator;
use Illuminate\Http\Request;

class PortfolioController extends Controller
{
    /** Save how many badges I own. */
    public function badges(Request $request)
    {
        $data = $request->validate(['badges_owned' => ['required', 'integer', 'min:0', 'max:100000']]);
        Setting::put('badges_owned', (string) $data['badges_owned']);

        return redirect()->to(route('dashboard').'#update')->with('status', "Badges set to {$data['badges_owned']}.");
    }

    public function planner(Request $request)
    {
        $calc = new EarningsCalculator;
        $current = Holding::current();
        $counts = $current ? $current->counts() : array_fill_keys(EarningsCalculator::RARITIES, 0);
        $badges = $calc->badgesOwned();

        $amounts = collect(explode(',', (string) $request->query('amounts', '1,10,25,50,100')))
            ->map(fn ($n) => (int) trim($n))->filter(fn ($n) => $n > 0 && $n <= 100000)->unique()->sort()->values()->all() ?: [1, 10, 50];

        if ($request->filled('boost_hours')) {
            $calc->settings['boost_hours_per_day'] = (string) max(0, min(24, (float) $request->query('boost_hours')));
        }

        $plan = $calc->plan($counts, $badges, $amounts);

        // How earnings change as you add parcels - shows where boost tiers kick in.
        $mix = $calc->expectedNewParcelMix();
        $curve = [];
        $max = max(100, (int) (array_sum($counts) * 1.5));
        $step = max(1, (int) ceil($max / 200)); // fine enough to show each boost tier edge
        for ($extra = 0; $extra <= $max; $extra += $step) {
            $c = $counts;
            foreach ($mix as $rarity => $share) {
                $c[$rarity] = ($c[$rarity] ?? 0) + $extra * $share;
            }
            $r = $calc->calculate($c, $badges);
            $curve[] = [
                'parcels' => array_sum($counts) + $extra,
                'boosted' => round($r['on_per_day'], 11),   // boosted all day at the tier
                'base' => round($r['off_per_day'], 11),     // no ad boost
                'typical' => round($r['per_day'], 11),      // your usual boosted hours
                'boosted_month' => round($r['boosted_per_month'], 11), // boosted all day, incl. Super Boost days
                'base_month' => round($r['base_per_month'], 11),
                'typical_month' => round($r['per_month'], 11),
                'boost' => $r['boost_multiplier'],
            ];
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
