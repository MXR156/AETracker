<?php

namespace App\Http\Controllers;

use App\Models\BoostRegion;
use App\Models\UserSetting;
use App\Services\EarningsCalculator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Admins keep each country's boost and badge tiers; accounts pick their country. */
class BoostRegionController extends Controller
{
    private function validated(Request $request, ?BoostRegion $region = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('boost_regions')->ignore($region?->id)],
            'boost_tiers' => ['required', 'string', 'max:2000'],
            'badge_tiers' => ['nullable', 'string', 'max:2000'],
        ]);
        if (! EarningsCalculator::parseTiers($data['boost_tiers'])) {
            throw ValidationException::withMessages(['boost_tiers' => 'Enter at least one boost tier as "from:multiplier", e.g. 1:20']);
        }

        return $data;
    }

    public function store(Request $request)
    {
        $region = BoostRegion::create($this->validated($request));
        if (! BoostRegion::where('is_default', true)->exists()) {
            $region->makeDefault();
        }

        return redirect()->to(route('settings.edit').'#countries')->with('status', "Added {$region->name}.");
    }

    public function update(Request $request, BoostRegion $region)
    {
        $region->update($this->validated($request, $region));

        return redirect()->to(route('settings.edit').'#countries')->with('status', "Saved {$region->name}'s tiers.");
    }

    public function makeDefault(BoostRegion $region)
    {
        $region->makeDefault();

        return redirect()->to(route('settings.edit').'#countries')->with('status', "{$region->name} is now the default for accounts that haven't picked a country.");
    }

    public function destroy(BoostRegion $region)
    {
        if (BoostRegion::count() === 1) {
            return back()->withErrors(['region' => 'There has to be at least one country.']);
        }
        // Anyone using it goes back to the default.
        UserSetting::where('key', 'boost_region_id')->where('value', (string) $region->id)->delete();
        $region->delete();
        if (! BoostRegion::where('is_default', true)->exists()) {
            BoostRegion::orderBy('name')->first()->makeDefault();
        }

        return redirect()->to(route('settings.edit').'#countries')->with('status', "Removed {$region->name}.");
    }
}
