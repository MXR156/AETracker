@extends('layouts.app')
@section('title', 'Planner')
@section('content')
@php($now = $plan['now'])
@php($best = collect($plan['options'])->first(fn ($o) => $o['payback_days'] !== null))
<div class="page-head">
    <div><h1>Planner</h1><div class="muted">Parcels or badges: what pays for itself fastest?</div></div>
    <a href="{{ route('portfolio.index') }}" class="btn btn-ghost">← My parcels</a>
</div>

@unless ($hasHoldings)
    <div class="notice">You haven't entered your parcels yet - <a href="{{ route('portfolio.index') }}">add them</a> for a meaningful plan.</div>
@endunless

<form class="card filters" method="get" style="align-items:flex-end">
    <div><label for="amounts">Parcel amounts to compare</label><input id="amounts" name="amounts" value="{{ implode(',', $amounts) }}" style="width:200px"></div>
    <div><label for="boost_hours">Boosted hours / day</label><input type="number" step="0.5" min="0" max="24" id="boost_hours" name="boost_hours" value="{{ request('boost_hours', $settings['boost_hours_per_day']) }}" style="width:110px"></div>
    <button class="btn">Recalculate</button>
    <div class="muted small" style="flex-basis:100%">
        A parcel costs {{ ae_money($calc->parcelCost(), 2) }} ({{ ae_num($settings['atlas_bucks_per_parcel'], 0) }} AB × {{ ae_money((float) $settings['cost_per_atlas_buck'], 4) }}).
        New parcels use the rarity odds from Settings ({{ ae_num($settings['odds_common'], 1) }}% common, {{ ae_num($settings['odds_rare'], 1) }}% rare, {{ ae_num($settings['odds_epic'], 1) }}% epic, {{ ae_num($settings['odds_legendary'], 1) }}% legendary).
    </div>
</form>

<div class="stats">
    <div class="stat"><div class="label">Now</div><div class="value">{{ ae_money($now['per_day']) }}<span class="muted small"> /day</span></div>
        <div class="sub">{{ number_format($now['parcels']) }} parcels · {{ ae_num($now['boost_multiplier']) }}× boost · +{{ ae_num($now['badge_percent']) }}% badges</div></div>
    @if ($best)
        <div class="stat" style="grid-column: span 2"><div class="label">Best value right now</div><div class="value">{{ $best['label'] }}</div>
            <div class="sub">+{{ ae_money($best['gain_per_day']) }}/day for {{ ae_money($best['cost'], 2) }} · pays back in {{ ae_days($best['payback_days']) }}</div></div>
    @endif
</div>

<div class="card table-wrap">
    <h2>Options, fastest payback first</h2>
    <table>
        <thead><tr><th>Option</th><th class="num">Cost</th><th class="num">Extra / day</th><th class="num hide-sm">Extra / year</th><th class="num">New total / day</th><th class="num">Pays back in</th><th class="hide-sm">Notes</th></tr></thead>
        <tbody>
        @foreach ($plan['options'] as $o)
            <tr @class(['me' => $best && $o['label'] === $best['label']])>
                <td>{{ $o['label'] }}</td>
                <td class="num">{{ $o['kind'] === 'badge' && $calc->badgeCost() <= 0 ? '–' : ae_money($o['cost'], 2) }}</td>
                <td class="num">{!! $o['gain_per_day'] >= 0 ? '<span class="up">+'.e(ae_money($o['gain_per_day'])).'</span>' : '<span class="down">'.e(ae_money($o['gain_per_day'])).'</span>' !!}</td>
                <td class="num hide-sm">{{ ae_money($o['gain_per_year'], 2) }}</td>
                <td class="num">{{ ae_money($o['after']['per_day']) }}</td>
                <td class="num">{{ ae_days($o['payback_days']) }}</td>
                <td class="hide-sm small">
                    @if ($o['boost_changed'])<span class="chip chip-gold">boost {{ ae_num($now['boost_multiplier']) }}× → {{ ae_num($o['after']['boost_multiplier']) }}×</span>@endif
                    @if ($o['badge_tier_changed'])<span class="chip">badges +{{ ae_num($now['badge_percent']) }}% → +{{ ae_num($o['after']['badge_percent']) }}%</span>@endif
                    @if ($o['note'])<span class="muted">{{ $o['note'] }}</span>@endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @if ($calc->badgeCost() <= 0)
        <p class="muted small" style="margin-bottom:0">To compare badges with parcels, set what one badge costs you in <a href="{{ route('settings.edit') }}">Settings</a>.</p>
    @endif
</div>

<div class="card">
    <h2>Earnings as you add parcels</h2>
    <p class="muted small" style="margin-top:0">The dips are where you drop into a lower ad-boost tier - buying just past a tier edge can lower your boosted earnings.</p>
    <div class="chart-box"><canvas id="curve"></canvas></div>
</div>

<div class="notice">These are estimates. Rent rates, boost tiers and badge tiers change in Atlas Earth from time to time - keep them up to date on the <a href="{{ route('settings.edit') }}">Settings</a> page.</div>
@endsection

@push('scripts')
<script>
    (function () {
        const c = @json($curve);
        const opts = AE.baseOptions();
        opts.scales.x.title = { display: true, text: 'Parcels owned', color: AE.textColor() };
        opts.scales.y.title = { display: true, text: 'Est. per day', color: AE.textColor() };
        opts.scales.y2 = { position: 'right', beginAtZero: true, ticks: { color: AE.textColor() }, grid: { drawOnChartArea: false },
            title: { display: true, text: 'Boost ×', color: AE.textColor() } };
        new Chart(document.getElementById('curve'), {
            type: 'line',
            data: {
                labels: c.map(r => Math.round(r.parcels)),
                datasets: [
                    { label: 'Est. per day', data: c.map(r => r.per_day), borderColor: AE.palette[0], backgroundColor: AE.palette[0], pointRadius: 0, tension: 0 },
                    { label: 'Boost multiplier', data: c.map(r => r.boost), borderColor: AE.palette[2], backgroundColor: AE.palette[2], yAxisID: 'y2', stepped: true, pointRadius: 0, borderDash: [4, 4] },
                ],
            },
            options: opts,
        });
    })();
</script>
@endpush
