@extends('layouts.app')
@section('title', 'Settings')
@section('content')
@php($v = fn ($key) => old($key, $settings[$key] ?? ''))
<div class="page-head">
    <div><h1>Settings</h1><div class="muted">The game numbers used to estimate your earnings. Check them against Atlas Earth and update them when they change.</div></div>
</div>

<div class="notice">Shared by everyone. The starting values are estimates, not official figures - check rent rates and each country's tiers in the game.</div>

<form method="post" action="{{ route('settings.update') }}">
    @csrf @method('PUT')
    <div class="grid grid-2">
        <div class="card">
            <h2>Rent per parcel, per second</h2>
            <p class="muted small" style="margin-top:0">Base rent before badges and ad boost.</p>
            @foreach (['common', 'rare', 'epic', 'legendary'] as $r)
                <div class="field">
                    <label for="rent_{{ $r }}">{{ ucfirst($r) }}</label>
                    <input id="rent_{{ $r }}" name="rent_{{ $r }}" value="{{ $v('rent_'.$r) }}" inputmode="decimal">
                    <div class="hint">≈ {{ ae_earn((float) $v('rent_'.$r) * 86400 * 365) }} per year, unboosted</div>
                </div>
            @endforeach
            <div class="field">
                <label for="currency_symbol">Currency symbol</label>
                <input id="currency_symbol" name="currency_symbol" value="{{ $v('currency_symbol') }}" style="width:80px">
            </div>
        </div>

        <div class="card">
            <h2>Ad boost</h2>
            <p class="muted small" style="margin-top:0">Boost and badge tiers are set per country - see <a href="#countries">Countries</a> below.
                Each person sets their own boosted hours a day on their account page.</p>
            <div class="field">
                <label for="boost_tier_basis">Tiers are based on</label>
                <select id="boost_tier_basis" name="boost_tier_basis">
                    <option value="parcels" @selected($v('boost_tier_basis') === 'parcels')>Number of parcels owned</option>
                    <option value="rent" @selected($v('boost_tier_basis') === 'rent')>Rent (common-parcel equivalents)</option>
                </select>
                <div class="hint">Parcels: the "from" numbers are your parcel count, as in the game's boost table.
                    Rent: your base rent ÷ common rent, so rarer parcels count as more than one.</div>
            </div>

            <div class="form-row">
                <div class="field"><label for="super_boost_days_per_year">Super Boost days a year</label>
                    <input type="number" step="any" min="0" max="365" id="super_boost_days_per_year" name="super_boost_days_per_year" value="{{ $v('super_boost_days_per_year') }}"></div>
                <div class="field"><label for="super_boost_multiplier">Super Boost multiplier</label>
                    <input type="number" step="any" min="0" id="super_boost_multiplier" name="super_boost_multiplier" value="{{ $v('super_boost_multiplier') }}"></div>
            </div>
            <div class="hint" style="margin-top:-.5rem">During a Super Boost everyone's ad boost jumps to this multiplier, whatever their normal tier (e.g. 32 days a year at 50×). Set days to 0 to ignore them.</div>
        </div>

        <div class="card">
            <h2>New parcel odds (%)</h2>
            <p class="muted small" style="margin-top:0">Chance of each rarity when you buy a parcel - used by the planner.</p>
            <div class="form-row">
                @foreach (['common', 'rare', 'epic', 'legendary'] as $r)
                    <div class="field"><label for="odds_{{ $r }}">{{ ucfirst($r) }}</label>
                        <input type="number" step="any" min="0" id="odds_{{ $r }}" name="odds_{{ $r }}" value="{{ $v('odds_'.$r) }}"></div>
                @endforeach
            </div>
        </div>

        <div class="card">
            <h2>Costs</h2>
            <div class="form-row">
                <div class="field"><label for="atlas_bucks_per_parcel">Atlas Bucks per parcel</label>
                    <input type="number" step="any" min="0" id="atlas_bucks_per_parcel" name="atlas_bucks_per_parcel" value="{{ $v('atlas_bucks_per_parcel') }}"></div>
                <div class="field"><label for="cost_per_atlas_buck">Real cost of 1 Atlas Buck</label>
                    <input type="number" step="any" min="0" id="cost_per_atlas_buck" name="cost_per_atlas_buck" value="{{ $v('cost_per_atlas_buck') }}">
                    <div class="hint">e.g. a 1,000 AB pack for 9.99 → 0.00999</div></div>
                <div class="field"><label for="atlas_bucks_per_badge">Atlas Bucks per badge</label>
                    <input type="number" step="any" min="0" id="atlas_bucks_per_badge" name="atlas_bucks_per_badge" value="{{ $v('atlas_bucks_per_badge') }}"></div>
                <div class="field"><label for="cost_per_badge">Real cost of one more badge</label>
                    <input type="number" step="any" min="0" id="cost_per_badge" name="cost_per_badge" value="{{ $v('cost_per_badge') }}">
                    <div class="hint">Leave at 0 to use the Atlas Bucks cost above (e.g. 200 AB × the cost of 1 AB).</div></div>
            </div>
        </div>
    </div>

    <div class="actions" style="margin-top:1rem">
        <button class="btn">Save settings</button>
    </div>
</form>

<div class="card" id="countries" style="margin-top:1.5rem">
    <h2>Countries</h2>
    <p class="muted small" style="margin-top:0">Atlas Earth's boost and badge tiers depend on the country. Each person picks theirs on their account page;
        anyone who hasn't uses the default.</p>
    <div class="grid grid-2">
        @foreach ($regions as $region)
            <form method="post" action="{{ route('regions.update', $region) }}" class="card" style="margin:0">
                @csrf @method('PUT')
                <div class="field"><label>Country</label>
                    <input name="name" value="{{ $region->name }}" required>
                    @if ($region->is_default)<div class="hint"><span class="chip">default</span></div>@endif</div>
                <div class="form-row">
                    <div class="field"><label>Boost tiers <span class="muted">(from:×)</span></label>
                        <textarea name="boost_tiers" rows="11" required>{{ $region->boost_tiers }}</textarea></div>
                    <div class="field"><label>Badge tiers <span class="muted">(badges:%)</span></label>
                        <textarea name="badge_tiers" rows="11">{{ $region->badge_tiers }}</textarea></div>
                </div>
                <div class="actions">
                    <button class="btn btn-sm">Save {{ $region->name }}</button>
                    @unless ($region->is_default)
                        <button class="btn btn-ghost btn-sm" form="default-{{ $region->id }}">Make default</button>
                        <button class="btn btn-danger btn-sm" form="delete-{{ $region->id }}">Remove</button>
                    @endunless
                </div>
            </form>
        @endforeach
    </div>
    @foreach ($regions as $region)
        <form method="post" action="{{ route('regions.default', $region) }}" id="default-{{ $region->id }}" hidden>@csrf</form>
        <form method="post" action="{{ route('regions.destroy', $region) }}" id="delete-{{ $region->id }}" hidden onsubmit="return confirm('Remove {{ $region->name }}? Anyone using it goes back to the default.')">@csrf @method('DELETE')</form>
    @endforeach
    <p class="muted small">One tier per line: boost tiers as <code>from:multiplier</code> (e.g. <code>351:3</code> = 351 or more gives 3×),
        badge tiers as <code>badges:percent</code> (e.g. <code>11:10</code> = 11 or more badges gives +10%).</p>

    <details style="margin-top:.5rem" @if ($errors->has('name') || $errors->has('boost_tiers')) open @endif>
        <summary>+ Add a country</summary>
        <form method="post" action="{{ route('regions.store') }}" style="margin-top:.75rem;max-width:640px">
            @csrf
            <div class="field"><label for="new_region">Country</label><input id="new_region" name="name" value="{{ old('name') }}" placeholder="e.g. France" required></div>
            <div class="form-row">
                <div class="field"><label for="new_boost">Boost tiers</label><textarea id="new_boost" name="boost_tiers" rows="6" placeholder="1:20&#10;61:15" required>{{ old('boost_tiers') }}</textarea></div>
                <div class="field"><label for="new_badge">Badge tiers</label><textarea id="new_badge" name="badge_tiers" rows="6" placeholder="1:5&#10;11:10">{{ old('badge_tiers') }}</textarea></div>
            </div>
            <button class="btn btn-sm">Add country</button>
        </form>
    </details>
</div>

<form method="post" action="{{ route('settings.update') }}" style="margin-top:1.5rem" onsubmit="return confirm('Reset the game settings above to the defaults? Countries are kept.')">
    @csrf @method('PUT')
    <input type="hidden" name="reset" value="1">
    <button class="btn btn-danger btn-sm">Reset to defaults</button>
</form>
@endsection
