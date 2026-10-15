<?php

namespace App\Http\Controllers;

use App\Models\BoostRegion;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit()
    {
        return view('settings', [
            'settings' => Setting::allValues(),
            'defaults' => config('aetracker.defaults'),
            'regions' => BoostRegion::orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request)
    {
        if ($request->boolean('reset')) {
            Setting::query()->delete(); // shared game settings only - countries and personal settings are kept

            return redirect()->route('settings.edit')->with('status', 'Settings reset to the defaults.');
        }

        $rules = ['currency_symbol' => ['required', 'string', 'max:5'], 'boost_tier_basis' => ['required', 'in:parcels,rent']];
        // Some settings are changed on their own pages (badges on the dashboard, AB income on Projection).
        // ...and boost/badge tiers are kept per country (below).
        foreach (array_diff(array_keys(config('aetracker.defaults')), config('aetracker.page_settings'), config('aetracker.region_settings')) as $key) {
            $rules[$key] ??= ['required', 'numeric', 'min:0'];
        }
        $rules['super_boost_days_per_year'][] = 'max:365';
        $data = $request->validate($rules);

        foreach ($data as $key => $value) {
            Setting::put($key, (string) $value);
        }

        return redirect()->route('settings.edit')->with('status', 'Settings saved.');
    }
}
