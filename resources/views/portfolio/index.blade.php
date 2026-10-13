@extends('layouts.app')
@section('title', 'My Parcels')
@section('content')
@php($e = $earnings)
<div class="page-head">
    <div><h1>My Parcels &amp; Earnings</h1>
        <div class="muted">Estimated from your parcel counts, badges and the rates on the <a href="{{ route('settings.edit') }}">Settings</a> page.</div></div>
    <a href="{{ route('portfolio.planner') }}" class="btn">Open planner →</a>
</div>

@unless ($current)
    <div class="notice">Start by entering how many parcels you own of each rarity below.</div>
@endunless

<div class="stats">
    <div class="stat"><div class="label">Parcels</div><div class="value">{{ number_format($e['parcels']) }}</div>
        <div class="sub">@foreach ($calc::RARITIES as $r){{ $e['counts'][$r] ?? 0 }} {{ substr($r, 0, 1) === 'l' ? 'leg' : substr($r, 0, 3) }}@if (! $loop->last) · @endif @endforeach</div></div>
    <div class="stat"><div class="label">Badges</div><div class="value">{{ number_format($badges) }}</div><div class="sub">+{{ ae_num($e['badge_percent'], 2) }}% rent</div></div>
    <div class="stat"><div class="label">Ad boost</div><div class="value">{{ ae_num($e['boost_multiplier'], 2) }}×</div><div class="sub">{{ ae_num($e['boost_hours'], 1) }} h/day boosted · tier from {{ number_format($e['tier_amount']) }} {{ $calc->tierBasis() === 'rent' ? 'equiv.' : 'parcels' }}</div></div>
    <div class="stat"><div class="label">Est. per day</div><div class="value">{{ ae_money($e['per_day']) }}</div><div class="sub">{{ ae_money($e['per_year'], 2) }} / year</div></div>
</div>

@if ($e['next_boost_drop'] && $e['parcels'] > 0)
    <div class="notice">
        Boost tier: you're <strong>{{ number_format(ceil($e['next_boost_drop']['away'])) }}</strong> {{ $calc->tierBasis() === 'rent' ? 'common-parcel equivalents' : Str::plural('parcel', (int) ceil($e['next_boost_drop']['away'])) }} away from dropping to the
        <strong>{{ ae_num($e['next_boost_drop']['multiplier'], 2) }}×</strong> boost tier.
        The <a href="{{ route('portfolio.planner') }}">planner</a> shows whether buying past it is still worth it.
    </div>
@endif

<div class="card table-wrap">
    <h2>Earnings breakdown</h2>
    <table>
        <thead><tr><th></th><th class="num">Per second</th><th class="num">Per hour</th><th class="num">Per day</th><th class="num">Per month</th><th class="num">Per year</th></tr></thead>
        <tbody>
        @foreach ([
            ['Base rent (no boosts)', $e['base_per_second']],
            ['Off boost (with badges)', $e['off_per_second']],
            ['On ad boost (×'.ae_num($e['boost_multiplier'], 2).')', $e['on_per_second']],
        ] as [$label, $ps])
            <tr>
                <td>{{ $label }}</td>
                <td class="num">{{ ae_money($ps, 10) }}</td>
                <td class="num">{{ ae_money($ps * 3600) }}</td>
                <td class="num">{{ ae_money($ps * 86400) }}</td>
                <td class="num">{{ ae_money($ps * 86400 * 365 / 12) }}</td>
                <td class="num">{{ ae_money($ps * 86400 * 365) }}</td>
            </tr>
        @endforeach
        <tr>
            <td><strong>Your typical day</strong> <span class="muted small">({{ ae_num($e['boost_hours'], 1) }}h boosted)</span></td>
            <td class="num">{{ ae_money($e['per_day'] / 86400, 10) }}</td>
            <td class="num">{{ ae_money($e['per_day'] / 24) }}</td>
            <td class="num"><strong>{{ ae_money($e['per_day']) }}</strong></td>
            <td class="num"><strong>{{ ae_money($e['per_month']) }}</strong></td>
            <td class="num"><strong>{{ ae_money($e['per_year']) }}</strong></td>
        </tr>
        </tbody>
    </table>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Bought or lost some parcels?</h2>
        <form method="post" action="{{ route('holdings.add') }}">
            @csrf
            <div class="form-row">
                <div class="field"><label for="quantity">Quantity</label><input type="number" id="quantity" name="quantity" value="1" required>
                    <div class="hint">Negative to remove.</div></div>
                <div class="field"><label for="rarity">Rarity</label>
                    <select id="rarity" name="rarity">@foreach ($calc::RARITIES as $r)<option value="{{ $r }}">{{ ucfirst($r) }}</option>@endforeach</select></div>
            </div>
            <div class="field"><label for="add_notes">Where / notes <span class="muted">(optional)</span></label><input id="add_notes" name="notes" placeholder="e.g. Boreham"></div>
            <button class="btn">Add to my parcels</button>
        </form>
    </div>

    <div class="card">
        <h2>Set exact counts</h2>
        <form method="post" action="{{ route('holdings.store') }}">
            @csrf
            <div class="form-row">
                @foreach ($calc::RARITIES as $r)
                    <div class="field"><label for="h_{{ $r }}">{{ ucfirst($r) }}</label>
                        <input type="number" min="0" id="h_{{ $r }}" name="{{ $r }}" value="{{ old($r, $current?->$r ?? 0) }}" required></div>
                @endforeach
            </div>
            <div class="form-row">
                <div class="field"><label for="h_date">Date</label><input type="date" id="h_date" name="recorded_on" value="{{ now()->toDateString() }}" required></div>
                <div class="field"><label for="h_notes">Notes</label><input id="h_notes" name="notes"></div>
            </div>
            <button class="btn btn-ghost">Save counts</button>
        </form>
    </div>
</div>

@if ($history->count() > 1)
    <div class="card" style="margin-top:1.25rem">
        <h2>Parcels &amp; estimated earnings over time</h2>
        <div class="chart-box"><canvas id="history"></canvas></div>
    </div>
@endif

<div class="card table-wrap" style="margin-top:1.25rem">
    <h2>Parcel history</h2>
    <table>
        <thead><tr><th>Date</th>@foreach ($calc::RARITIES as $r)<th class="num">{{ ucfirst($r) }}</th>@endforeach<th class="num">Total</th><th class="hide-sm">Notes</th><th></th></tr></thead>
        <tbody>
        @forelse ($holdings as $h)
            <tr>
                <td class="nowrap">{{ $h->recorded_on->format('j M Y') }}</td>
                @foreach ($calc::RARITIES as $r)<td class="num">{{ number_format($h->$r) }}</td>@endforeach
                <td class="num"><strong>{{ number_format($h->total()) }}</strong></td>
                <td class="hide-sm small muted">{{ $h->notes }}</td>
                <td class="right">
                    <form class="inline" method="post" action="{{ route('holdings.destroy', $h) }}" onsubmit="return confirm('Delete this entry?')">
                        @csrf @method('DELETE')<button class="link-btn" style="color:var(--down)">Delete</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">No entries yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="card" id="badges">
    <h2>Badges</h2>
    <div class="grid grid-2" style="align-items:start">
        <form method="post" action="{{ route('portfolio.badges') }}">
            @csrf @method('PUT')
            <div class="field">
                <label for="badges_owned">How many badges do you have?</label>
                <div class="actions" style="flex-wrap:nowrap">
                    <input type="number" min="0" id="badges_owned" name="badges_owned" value="{{ old('badges_owned', $badges) }}" style="max-width:140px" required>
                    <button class="btn">Save</button>
                </div>
            </div>
            <p style="margin:0">
                @if ($e['badge_percent'] > 0)
                    {{ number_format($badges) }} badges gives you <strong>+{{ ae_num($e['badge_percent'], 2) }}% rent</strong>.
                @else
                    No badge boost yet.
                @endif
                @if ($e['next_badge_tier'])
                    <span class="muted">{{ number_format($e['next_badge_tier']['from'] - $badges) }} more for +{{ ae_num($e['next_badge_tier']['value'], 2) }}%.</span>
                @else
                    <span class="muted">That's the top tier.</span>
                @endif
            </p>
        </form>

        <div class="table-wrap">
            <table>
                <thead><tr><th>Badges</th><th class="num">Rent boost</th></tr></thead>
                <tbody>
                @forelse ($badgeTiers as $i => $tier)
                    @php($upTo = isset($badgeTiers[$i + 1]) ? $badgeTiers[$i + 1]['from'] - 1 : null)
                    <tr @class(['me' => $badges >= $tier['from'] && ($upTo === null || $badges <= $upTo)])>
                        <td>{{ number_format($tier['from']) }}{{ $upTo === null ? '+' : '–'.number_format($upTo) }}</td>
                        <td class="num">+{{ ae_num($tier['value'], 2) }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="empty">No badge tiers set - add them in <a href="{{ route('settings.edit') }}">Settings</a>.</td></tr>
                @endforelse
                </tbody>
            </table>
            <p class="muted small" style="margin-bottom:0">Change the tiers in <a href="{{ route('settings.edit') }}">Settings</a>.</p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@if ($history->count() > 1)
<script>
    (function () {
        const h = @json($history);
        const opts = AE.baseOptions();
        opts.scales.y.title = { display: true, text: 'Parcels', color: AE.textColor() };
        opts.scales.y2 = { position: 'right', beginAtZero: true, ticks: { color: AE.textColor() }, grid: { drawOnChartArea: false },
            title: { display: true, text: 'Est. per day', color: AE.textColor() } };
        new Chart(document.getElementById('history'), {
            type: 'line',
            data: {
                labels: h.map(r => r.date),
                datasets: [
                    { label: 'Parcels', data: h.map(r => r.parcels), borderColor: AE.palette[0], backgroundColor: AE.palette[0], stepped: true },
                    { label: 'Est. per day', data: h.map(r => r.per_day), borderColor: AE.palette[1], backgroundColor: AE.palette[1], yAxisID: 'y2', stepped: true },
                ],
            },
            options: opts,
        });
    })();
</script>
@endif
@endpush
