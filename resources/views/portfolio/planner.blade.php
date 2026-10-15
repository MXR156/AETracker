@extends('layouts.app')
@section('title', 'Planner')
@section('content')
@php($now = $plan['now'])
@php($best = collect($plan['options'])->first(fn ($o) => $o['payback_days'] !== null))
<div class="page-head">
    <div><h1>Planner</h1><div class="muted">Parcels or badges: what pays for itself fastest?</div></div>
    <a href="{{ route('dashboard') }}" class="btn btn-ghost">← Dashboard</a>
</div>

@unless ($hasHoldings)
    <div class="notice">You haven't entered your parcels yet - <a href="{{ route('dashboard') }}">add them</a> for a meaningful plan.</div>
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
    <div class="stat"><div class="label">Now</div><div class="value">{{ ae_earn($now['per_day']) }}<span class="muted small"> /day</span></div>
        <div class="sub">{{ number_format($now['parcels']) }} parcels · {{ ae_num($now['boost_multiplier']) }}× boost · +{{ ae_num($now['badge_percent']) }}% badges</div></div>
    @if ($best)
        <div class="stat" style="grid-column: span 2"><div class="label">Best value right now</div><div class="value">{{ $best['label'] }}</div>
            <div class="sub">+{{ ae_earn($best['gain_per_day']) }}/day for {{ ae_money($best['cost'], 2) }} · pays back in {{ ae_days($best['payback_days']) }}</div></div>
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
                <td class="num">{!! $o['gain_per_day'] >= 0 ? '<span class="up">+'.e(ae_earn($o['gain_per_day'])).'</span>' : '<span class="down">'.e(ae_earn($o['gain_per_day'])).'</span>' !!}</td>
                <td class="num hide-sm">{{ ae_earn($o['gain_per_year']) }}</td>
                <td class="num">{{ ae_earn($o['after']['per_day']) }}</td>
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
        <p class="muted small" style="margin-bottom:0">To compare badges with parcels, a badge's cost is set in {!! ae_settings_link() !!}.</p>
    @endif
</div>

@php($hours = (float) $settings['boost_hours_per_day'])
<div class="card">
    <h2>Earnings per day as you add parcels - with ad boost</h2>
    <p class="muted small" style="margin-top:0">Boosted all day at your tier (the "Per day" row in your earnings table){{ $hours < 24 ? ', with your typical '.ae_num($hours, 1).' boosted hours a day as a dashed line' : '' }}.
        The dips are where you drop into a lower ad-boost tier - buying just past a tier edge can lower your boosted earnings.</p>
    <div class="chart-box"><canvas id="curve-boosted"></canvas></div>
</div>

<div class="card">
    <h2>Monthly earnings as you add parcels</h2>
    @php($sbDays = (float) $settings['super_boost_days_per_year'])
    <p class="muted small" style="margin-top:0">A month is a twelfth of a year: {{ number_format((365 - $sbDays) / 12, 2) }} normal days{{ $sbDays > 0 ? ' + '.number_format($sbDays / 12, 2).' Super Boost days at '.ae_num((float) $settings['super_boost_multiplier'], 2).'×' : '' }},
        like the "Per month" row in your earnings table.</p>
    <div class="chart-box"><canvas id="curve-month"></canvas></div>
</div>

<div class="card">
    <h2>Earnings per day as you add parcels - base (no ad boost)</h2>
    <p class="muted small" style="margin-top:0">Rent with your badge boost only. This always goes up as you add parcels.</p>
    <div class="chart-box"><canvas id="curve-base"></canvas></div>
</div>

<div class="notice">These are estimates. Rent rates, boost tiers and badge tiers change in Atlas Earth from time to time - they're kept up to date in {!! ae_settings_link() !!}.</div>
@endsection

@push('scripts')
<script>
    (function () {
        const c = @json($curve);
        const labels = c.map(r => Math.round(r.parcels));
        const line = (label, key, color, extra = {}) => Object.assign({
            label, money: true, data: c.map(r => r[key]), borderColor: color, backgroundColor: color, pointRadius: 0, tension: 0,
        }, extra);
        const options = (withBoostAxis) => {
            const opts = AE.baseOptions();
            opts.scales.x.title = { display: true, text: 'Parcels owned', color: AE.textColor() };
            opts.scales.y.title = { display: true, text: 'Est. per day', color: AE.textColor() };
            opts.plugins.tooltip = AE.moneyTooltip();
            if (withBoostAxis) {
                opts.scales.y2 = { position: 'right', beginAtZero: true, ticks: { color: AE.textColor() }, grid: { drawOnChartArea: false },
                    title: { display: true, text: 'Boost ×', color: AE.textColor() } };
            }
            return opts;
        };

        const boosted = [line('With ad boost (all day)', 'boosted', AE.palette[0])];
        @if ($hours < 24)
            boosted.push(line('Your typical day ({{ ae_num($hours, 1) }}h boosted)', 'typical', AE.palette[1], { borderDash: [6, 4] }));
        @endif
        boosted.push({ label: 'Boost multiplier', data: c.map(r => r.boost), borderColor: AE.palette[2], backgroundColor: AE.palette[2],
            yAxisID: 'y2', stepped: true, pointRadius: 0, borderDash: [4, 4] });
        new Chart(document.getElementById('curve-boosted'), { type: 'line', data: { labels, datasets: boosted }, options: options(true) });

        const monthly = [
            line('With ad boost (all day)', 'boosted_month', AE.palette[0]),
            line('Base (no ad boost)', 'base_month', AE.palette[3]),
        ];
        @if ($hours < 24)
            monthly.splice(1, 0, line('Your typical ({{ ae_num($hours, 1) }}h boosted)', 'typical_month', AE.palette[1], { borderDash: [6, 4] }));
        @endif
        const monthOpts = options(false);
        monthOpts.scales.y.title.text = 'Est. per month';
        new Chart(document.getElementById('curve-month'), { type: 'line', data: { labels, datasets: monthly }, options: monthOpts });

        new Chart(document.getElementById('curve-base'), {
            type: 'line', data: { labels, datasets: [line('Base (no ad boost)', 'base', AE.palette[3])] }, options: options(false),
        });
    })();
</script>
@endpush
