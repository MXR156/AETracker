@extends('layouts.app')
@section('title', 'Projection')
@section('content')
@php($v = fn ($key) => old($key, $settings[$key] ?? ''))
@php($last = end($rows))
@php($first = $rows[0])
@php($when = fn ($row) => $row ? ($row['month'] === 0 ? 'Already there' : $row['date']->format('M Y').' · in '.$row['month'].' '.Str::plural('month', $row['month'])) : null)
<div class="page-head">
    <div><h1>Projection</h1>
        <div class="muted">Where your account could be if your reward AB goes into parcels every month.</div></div>
</div>

<form method="post" action="{{ route('projection.update') }}" class="card">
    @csrf @method('PUT')
    <h2>Your AB income</h2>
    <div class="grid grid-2" style="gap:1rem">
        <div>
            <div class="form-row">
                <div class="field"><label for="ab_free_daily_90">Free daily rewards <span class="muted">(AB per 90 days)</span></label>
                    <input type="number" step="any" min="0" id="ab_free_daily_90" name="ab_free_daily_90" value="{{ $v('ab_free_daily_90') }}"></div>
                <div class="field"><label for="ab_free_ladder_30">Free reward ladder <span class="muted">(AB per 30 days)</span></label>
                    <input type="number" step="any" min="0" id="ab_free_ladder_30" name="ab_free_ladder_30" value="{{ $v('ab_free_ladder_30') }}"></div>
            </div>
            <div class="form-row">
                <div class="field"><label for="ab_extra_month">Other AB <span class="muted">(per month)</span></label>
                    <input type="number" step="any" min="0" id="ab_extra_month" name="ab_extra_month" value="{{ $v('ab_extra_month') }}">
                    <div class="hint">Arcade, spins, surveys, commissions…</div></div>
                <div class="field"><label for="ab_balance">AB you have now</label>
                    <input type="number" step="any" min="0" id="ab_balance" name="ab_balance" value="{{ $v('ab_balance') }}"></div>
            </div>
        </div>
        <div>
            <label style="font-weight:600;margin-bottom:.5rem"><input type="checkbox" name="explorer_club" value="1" @checked($p->explorerClub())> I'm in the Explorer Club</label>
            <div class="form-row">
                <div class="field"><label for="ab_explorer_90">AB per 90 days</label>
                    <input type="number" step="any" min="0" id="ab_explorer_90" name="ab_explorer_90" value="{{ $v('ab_explorer_90') }}"></div>
                <div class="field"><label for="explorer_cost_month">Cost per month</label>
                    <input type="number" step="any" min="0" id="explorer_cost_month" name="explorer_cost_month" value="{{ $v('explorer_cost_month') }}"></div>
            </div>
            <label style="font-weight:600;margin-bottom:.5rem"><input type="checkbox" name="premium_ladder" value="1" @checked($p->premiumLadder())> I'm on the Premium Rewards Ladder</label>
            <div class="form-row">
                <div class="field"><label for="ab_premium_30">AB per 30 days</label>
                    <input type="number" step="any" min="0" id="ab_premium_30" name="ab_premium_30" value="{{ $v('ab_premium_30') }}"></div>
                <div class="field"><label for="premium_cost_month">Cost per month</label>
                    <input type="number" step="any" min="0" id="premium_cost_month" name="premium_cost_month" value="{{ $v('premium_cost_month') }}"></div>
            </div>
        </div>
    </div>
    <div class="form-row" style="align-items:end">
        <div class="field"><label for="projection_months">Months to project</label>
            <input type="number" min="1" max="120" id="projection_months" name="projection_months" value="{{ $v('projection_months') }}"></div>
        <div class="field"><label for="rent_milestones">Monthly rent targets</label>
            <input id="rent_milestones" name="rent_milestones" value="{{ $v('rent_milestones') }}" placeholder="10, 20, 30, 50"></div>
        <div class="field"><button class="btn">Update projection</button></div>
    </div>
</form>

<div class="stats">
    <div class="stat"><div class="label">AB per 90 days</div><div class="value">{{ number_format($p->abPerDay() * 90) }}</div>
        <div class="sub">{{ number_format($p->abPerDay(), 1) }} a day</div></div>
    <div class="stat"><div class="label">Parcels per 90 days</div><div class="value">{{ number_format($p->abPerDay() * 90 / $p->abPerParcel(), 1) }}</div>
        <div class="sub">{{ number_format($p->abPerDay() * 365 / $p->abPerParcel()) }} a year</div></div>
    <div class="stat"><div class="label">Subscriptions</div><div class="value">{{ ae_money($p->subscriptionCostPerMonth(), 2) }}</div>
        <div class="sub">a month · {{ ae_money($p->subscriptionCostPerMonth() * 3, 2) }} per 90 days</div></div>
    <div class="stat"><div class="label">Parcels in {{ $last['month'] }} months</div><div class="value">{{ number_format($last['parcels']) }}</div>
        <div class="sub">from {{ number_format($first['parcels']) }} now · {{ ae_num($last['boost'], 2) }}× boost</div></div>
    <div class="stat"><div class="label">Rent per month then</div><div class="value" style="font-size:1.1rem">{{ ae_earn($last['rent_month']) }}</div>
        <div class="sub">now {{ ae_earn($first['rent_month']) }}</div></div>
</div>

@if (($p->explorerClub() && (float) $settings['explorer_cost_month'] <= 0) || ($p->premiumLadder() && (float) $settings['premium_cost_month'] <= 0))
    <div class="notice">Enter what your subscriptions cost per month above to see when your rent will cover them.</div>
@endif

<div class="grid grid-2">
    <div class="card table-wrap">
        <h2>AB income</h2>
        <table>
            <thead><tr><th>Source</th><th class="num">Per 30 days</th><th class="num">Per 90 days</th><th class="num hide-sm">Per year</th></tr></thead>
            <tbody>
            @foreach ($sources as $s)
                <tr @class(['muted' => ! $s['on']])>
                    <td>{{ $s['label'] }} @unless ($s['on'])<span class="chip chip-muted">not included</span>@endunless</td>
                    <td class="num">{{ number_format($s['per_30'], 0) }}</td>
                    <td class="num">{{ number_format($s['per_90'], 0) }}</td>
                    <td class="num hide-sm">{{ number_format($s['per_year'], 0) }}</td>
                </tr>
            @endforeach
            <tr>
                <td><strong>Total</strong></td>
                <td class="num"><strong>{{ number_format($p->abPerDay() * 30, 0) }}</strong></td>
                <td class="num"><strong>{{ number_format($p->abPerDay() * 90, 0) }}</strong></td>
                <td class="num hide-sm"><strong>{{ number_format($p->abPerDay() * 365, 0) }}</strong></td>
            </tr>
            </tbody>
        </table>
        <p class="muted small" style="margin-bottom:0">Per 30 and per 90 days are worked out from the period each reward is given over. A parcel costs {{ number_format($p->abPerParcel()) }} AB.</p>
    </div>

    <div class="card table-wrap">
        <h2>Milestones</h2>
        <table>
            <thead><tr><th>Milestone</th><th>When</th><th class="num">Parcels</th></tr></thead>
            <tbody>
            @forelse ($result['milestones'] as $m)
                <tr>
                    <td>{{ $m['label'] }}@if ($m['target'] !== null) <span class="muted small">({{ ae_money($m['target'], 2) }})</span>@endif</td>
                    <td>@if ($m['row']){{ $when($m['row']) }}@else<span class="muted">Not within {{ $last['month'] }} months</span>@endif</td>
                    <td class="num">{{ $m['row'] ? number_format($m['row']['parcels']) : '' }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="empty">Add monthly rent targets above.</td></tr>
            @endforelse
            </tbody>
        </table>
        <p class="muted small" style="margin-bottom:0">Rent per month includes ad boost ({{ ae_num((float) $settings['boost_hours_per_day'], 1) }}h a day), badges and Super Boost days, as on the dashboard.</p>
    </div>
</div>

<div class="card" style="margin-top:1.25rem">
    <h2>Parcels and monthly rent over time</h2>
    <div class="chart-box"><canvas id="projection-chart"></canvas></div>
</div>

@if ($takeovers)
    <div class="card table-wrap">
        <h2>Becoming Mayor</h2>
        <p class="muted small" style="margin-top:0">If every new parcel went into that location. “Leader keeps growing” uses the leader's growth between your snapshots.</p>
        <table>
            <thead><tr>
                <th>Location</th><th class="num">You</th><th>Leader</th><th class="num">Gap</th><th class="num hide-sm">Leader growth</th>
                <th>Leader stays still</th><th>Leader keeps growing</th>
            </tr></thead>
            <tbody>
            @foreach ($takeovers as $t)
                <tr>
                    <td><a href="{{ route('locations.show', $t->location) }}">{{ $t->location->displayName() }}</a></td>
                    <td class="num">{{ number_format($t->mine->parcels) }}</td>
                    <td>{{ $t->leader->player->username }} <span class="muted small">{{ number_format($t->leader->parcels) }}</span></td>
                    <td class="num">{{ number_format($t->gap) }}</td>
                    <td class="num hide-sm">{{ $t->growth === null ? '–' : ae_num($t->growth, 1).'/mo' }}</td>
                    <td>{{ $when($t->still) ?? 'Not within '.$last['month'].' months' }}</td>
                    <td>@if ($t->growth === null)<span class="muted small">need 2+ snapshots</span>@else{{ $when($t->growing) ?? 'Not within '.$last['month'].' months' }}@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="card table-wrap">
    <details>
        <summary>Month by month</summary>
        <table style="margin-top:.75rem">
            <thead><tr><th>Month</th><th class="num">Parcels bought</th><th class="num">Parcels</th><th class="num">Boost</th><th class="num">Rent per month</th>
                <th class="num hide-sm">Rent earned so far</th><th class="num hide-sm">Subscriptions paid</th><th class="num hide-sm">AB left over</th></tr></thead>
            <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td class="nowrap">{{ $r['month'] === 0 ? 'Now' : $r['date']->format('M Y') }}</td>
                    <td class="num">{{ $r['month'] === 0 ? '' : number_format($r['bought']) }}</td>
                    <td class="num">{{ number_format($r['parcels']) }}</td>
                    <td class="num">{{ ae_num($r['boost'], 2) }}×</td>
                    <td class="num">{{ ae_earn($r['rent_month']) }}</td>
                    <td class="num hide-sm">{{ ae_earn($r['rent_total']) }}</td>
                    <td class="num hide-sm">{{ ae_money($r['subs_total'], 2) }}</td>
                    <td class="num hide-sm">{{ number_format($r['ab_balance']) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </details>
</div>

<div class="notice">A forecast, not a promise: it assumes you complete every reward cycle, put all AB into parcels straight away, reward amounts and game rates stay the same, and new parcels follow the rarity odds in Settings. Your badge count stays as it is.</div>
@endsection

@push('scripts')
<script>
    (function () {
        const rows = @json($chart);
        const subs = {{ $p->subscriptionCostPerMonth() }};
        const opts = AE.baseOptions();
        opts.scales.y.title = { display: true, text: 'Parcels', color: AE.textColor() };
        opts.scales.y2 = { position: 'right', beginAtZero: true, ticks: { color: AE.textColor() }, grid: { drawOnChartArea: false },
            title: { display: true, text: 'Rent per month', color: AE.textColor() } };
        opts.plugins.tooltip = AE.moneyTooltip();
        const datasets = [
            { label: 'Parcels', data: rows.map(r => r.parcels), borderColor: AE.palette[0], backgroundColor: AE.palette[0], pointRadius: 0, tension: 0 },
            { label: 'Rent per month', money: true, data: rows.map(r => r.rent), borderColor: AE.palette[1], backgroundColor: AE.palette[1], yAxisID: 'y2', pointRadius: 0, tension: 0 },
        ];
        if (subs > 0) {
            datasets.push({ label: 'Subscriptions per month', money: true, data: rows.map(() => subs), borderColor: AE.palette[2], backgroundColor: AE.palette[2],
                yAxisID: 'y2', pointRadius: 0, borderDash: [6, 4] });
        }
        new Chart(document.getElementById('projection-chart'), { type: 'line', data: { labels: rows.map(r => r.label), datasets }, options: opts });
    })();
</script>
@endpush
