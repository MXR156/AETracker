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
            'history' => $projection->history(),
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
            'login_streak_day' => ['required', 'integer', 'min:1', 'max:'.max(1, (int) $request->input('login_cycle_days', 90))],
            'login_cycle_days' => ['required', 'integer', 'min:1', 'max:366'],
            'login_free_base' => ['required', 'numeric', 'min:0'],
            'login_free_bonus' => ['nullable', 'string', 'max:1000'],
            'login_explorer_base' => ['required', 'numeric', 'min:0'],
            'login_explorer_bonus' => ['nullable', 'string', 'max:1000'],
            'ladder_free' => ['nullable', 'string', 'max:1000'],
            'ladder_premium' => ['nullable', 'string', 'max:1000'],
            'explorer_cost_month' => ['required', 'numeric', 'min:0'],
            'premium_cost_month' => ['required', 'numeric', 'min:0'],
            'ab_extra_month' => ['required', 'numeric', 'min:0'],
            'ab_balance' => ['required', 'numeric', 'min:0'],
            'projection_months' => ['required', 'integer', 'min:1', 'max:120'],
            'rent_milestones' => ['nullable', 'string', 'max:255'],
            'account_started_on' => ['nullable', 'date', 'before:tomorrow'],
            'projection_basis' => ['required', 'in:rewards,history'],
        ]);
        $data['account_started_on'] ??= '';
        $data['use_own_mix'] = $request->boolean('use_own_mix') ? '1' : '0';
        $data['explorer_club'] = $request->boolean('explorer_club') ? '1' : '0';
        $data['premium_ladder'] = $request->boolean('premium_ladder') ? '1' : '0';
        // The streak day is as of today; it moves on by itself from here.
        $data['login_streak_set_on'] = now()->toDateString();

        foreach ($data as $key => $value) {
            Setting::put($key, (string) $value);
        }

        return redirect()->route('projection.index')->with('status', 'Projection updated.');
    }
}
