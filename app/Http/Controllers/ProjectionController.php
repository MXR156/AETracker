<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\EarningsCalculator;
use App\Services\Projection;
use Illuminate\Http\Request;

class ProjectionController extends Controller
{
    public function index()
    {
        $projection = new Projection(new EarningsCalculator);
        $result = $projection->run();

        return view('projection', [
            'p' => $projection,
            'result' => $result,
            'rows' => $result['rows'],
            'sources' => $projection->sources(),
            'takeovers' => $projection->takeovers($result['rows']),
            'settings' => $projection->settings,
            'chart' => array_map(fn ($r) => [
                'label' => $r['month'] === 0 ? 'Now' : $r['date']->format('M y'),
                'parcels' => $r['parcels'],
                'rent' => round($r['rent_month'], 11),
            ], $result['rows']),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'ab_free_daily_90' => ['required', 'numeric', 'min:0'],
            'ab_free_ladder_30' => ['required', 'numeric', 'min:0'],
            'ab_explorer_90' => ['required', 'numeric', 'min:0'],
            'explorer_cost_month' => ['required', 'numeric', 'min:0'],
            'ab_premium_30' => ['required', 'numeric', 'min:0'],
            'premium_cost_month' => ['required', 'numeric', 'min:0'],
            'ab_extra_month' => ['required', 'numeric', 'min:0'],
            'ab_balance' => ['required', 'numeric', 'min:0'],
            'projection_months' => ['required', 'integer', 'min:1', 'max:120'],
            'rent_milestones' => ['nullable', 'string', 'max:255'],
        ]);
        $data['explorer_club'] = $request->boolean('explorer_club') ? '1' : '0';
        $data['premium_ladder'] = $request->boolean('premium_ladder') ? '1' : '0';

        foreach ($data as $key => $value) {
            Setting::put($key, (string) $value);
        }

        return redirect()->route('projection.index')->with('status', 'Projection updated.');
    }
}
