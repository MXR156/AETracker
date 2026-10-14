<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\EarningsCalculator;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit()
    {
        return view('settings', ['settings' => Setting::allValues(), 'defaults' => config('aetracker.defaults')]);
    }

    public function update(Request $request)
    {
        if ($request->boolean('reset')) {
            Setting::query()->delete();

            return redirect()->route('settings.edit')->with('status', 'Settings reset to the defaults.');
        }

        $rules = ['currency_symbol' => ['required', 'string', 'max:5'], 'boost_tiers' => ['required', 'string'], 'badge_tiers' => ['nullable', 'string'], 'boost_tier_basis' => ['required', 'in:parcels,rent']];
        // Some settings are changed on their own pages (badges on the dashboard, AB income on Projection).
        foreach (array_diff(array_keys(config('aetracker.defaults')), config('aetracker.page_settings')) as $key) {
            $rules[$key] ??= ['required', 'numeric', 'min:0'];
        }
        $rules['boost_hours_per_day'][] = 'max:24';
        $rules['super_boost_days_per_year'][] = 'max:365';
        $data = $request->validate($rules);

        if (! EarningsCalculator::parseTiers($data['boost_tiers'])) {
            return back()->withInput()->withErrors(['boost_tiers' => 'Enter at least one tier as "from:multiplier", e.g. 0:50']);
        }

        foreach ($data as $key => $value) {
            Setting::put($key, (string) $value);
        }

        return redirect()->route('settings.edit')->with('status', 'Settings saved.');
    }
}
