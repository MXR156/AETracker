@extends('layouts.app')
@section('title', 'Settings')
@section('content')
@php($v = fn ($key) => old($key, $settings[$key] ?? ''))
<div class="page-head">
    <div><h1>Settings</h1><div class="muted">The game numbers used to estimate your earnings. Check them against Atlas Earth and update them when they change.</div></div>
</div>

<div class="notice">The starting values are estimates, not official figures. Check your rent rates, boost tiers and badge tiers in the game.</div>

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
            <div class="field">
                <label for="boost_hours_per_day">Hours boosted per day (typical, 24 = always)</label>
                <input type="number" step="0.5" min="0" max="24" id="boost_hours_per_day" name="boost_hours_per_day" value="{{ $v('boost_hours_per_day') }}">
            </div>
            <div class="field">
                <label for="boost_tiers">Boost tiers</label>
                <textarea id="boost_tiers" name="boost_tiers" rows="8">{{ $v('boost_tiers') }}</textarea>
                <div class="hint">One tier per line as <code>from:multiplier</code> - e.g. <code>351:3</code> means 351 or more gives 3× boost.</div>
            </div>
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

            <h2 style="margin-top:1.25rem">Badge boost</h2>
            <div class="field">
                <label for="badge_tiers">Badge tiers</label>
                <textarea id="badge_tiers" name="badge_tiers" rows="6">{{ $v('badge_tiers') }}</textarea>
                <div class="hint">One tier per line as <code>badges:percent</code> - e.g. <code>1:5</code> means 1 or more badges gives +5% rent.
                    Set how many badges you have on <a href="{{ route('portfolio.index') }}#badges">My Parcels</a>.</div>
            </div>
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
                <div class="field"><label for="cost_per_badge">Real cost of one more badge</label>
                    <input type="number" step="any" min="0" id="cost_per_badge" name="cost_per_badge" value="{{ $v('cost_per_badge') }}">
                    <div class="hint">Optional - lets the planner compare badges with parcels. 0 if you don't know.</div></div>
            </div>
        </div>
    </div>

    <div class="actions" style="margin-top:1rem">
        <button class="btn">Save settings</button>
    </div>
</form>

<form method="post" action="{{ route('settings.update') }}" style="margin-top:1.5rem" onsubmit="return confirm('Reset every setting to the default?')">
    @csrf @method('PUT')
    <input type="hidden" name="reset" value="1">
    <button class="btn btn-danger btn-sm">Reset to defaults</button>
</form>
@endsection
