@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
@php($e = $earnings)
<div class="page-head">
    <div>
        <h1>Dashboard</h1>
        <div class="muted">
            @if ($me) Tracking as <a href="{{ route('players.show', $me) }}"><strong>{{ $me->username }}</strong></a>
            @else No player is marked as you yet - open your player page and click <em>“This is me”</em>. @endif
        </div>
    </div>
    <div class="actions">
        <a href="{{ route('portfolio.planner') }}" class="btn btn-ghost">Planner →</a>
        <a href="{{ route('locations.create') }}" class="btn">+ Add location</a>
    </div>
</div>

@unless ($current)
    <div class="notice">Start by entering how many parcels you own of each rarity under <a href="#update">Update my parcels</a>.</div>
@endunless

<div class="stats">
    <div class="stat"><div class="label">Parcels</div><div class="value">{{ number_format($e['parcels']) }}</div>
        <div class="sub">@foreach ($calc::RARITIES as $r){{ $e['counts'][$r] ?? 0 }} {{ substr($r, 0, 1) === 'l' ? 'leg' : substr($r, 0, 3) }}@if (! $loop->last) · @endif @endforeach</div></div>
    <div class="stat"><div class="label">Badges</div><div class="value">{{ number_format($badges) }}</div><div class="sub">+{{ ae_num($e['badge_percent'], 2) }}% rent</div></div>
    <div class="stat"><div class="label">Ad boost</div><div class="value">{{ ae_num($e['boost_multiplier'], 2) }}×</div><div class="sub">tier from {{ number_format($e['tier_amount']) }} {{ $calc->tierBasis() === 'rent' ? 'equiv.' : 'parcels' }}
        @isset($calc->settings['boost_region_name']) · <a href="{{ route('account.edit') }}#preferences">{{ $calc->settings['boost_region_name'] }}</a>@endisset</div></div>
    <div class="stat"><div class="label">Per month (boosted)</div><div class="value" style="font-size:1.1rem">{{ ae_earn($e['boosted_per_month']) }}</div><div class="sub">{{ ae_earn($e['boosted_per_year']) }} / year</div></div>
    @if ($me)
        <div class="stat"><div class="label">Leader in</div><div class="value">{{ $leading->count() }}</div><div class="sub">of {{ $leading->count() + $chasing->count() }} locations you're in</div></div>
    @endif
</div>

@if ($e['next_boost_drop'] && $e['parcels'] > 0)
    <div class="notice">
        Boost tier: you're <strong>{{ number_format(ceil($e['next_boost_drop']['away'])) }}</strong> {{ $calc->tierBasis() === 'rent' ? 'common-parcel equivalents' : Str::plural('parcel', (int) ceil($e['next_boost_drop']['away'])) }} away from dropping to the
        <strong>{{ ae_num($e['next_boost_drop']['multiplier'], 2) }}×</strong> boost tier.
        The <a href="{{ route('portfolio.planner') }}">planner</a> shows whether buying past it is still worth it.
    </div>
@endif

<h2 id="update" style="margin-top:.5rem">Update my parcels &amp; badges</h2>
<div class="grid grid-2" style="margin-bottom:1.25rem">
    <div class="card">
        <h2>My parcels</h2>
        <p class="muted small" style="margin-top:0">How many you own of each rarity - used for all the earnings figures. Update it whenever your count changes.</p>
        <form method="post" action="{{ route('holdings.store') }}">
            @csrf
            <div class="form-row form-row-4">
                @foreach ($calc::RARITIES as $r)
                    <div class="field"><label for="h_{{ $r }}">{{ ucfirst($r) }}</label>
                        <input type="number" min="0" id="h_{{ $r }}" name="{{ $r }}" value="{{ old($r, $current?->$r ?? 0) }}" required></div>
                @endforeach
            </div>
            <button class="btn">Save</button>
        </form>
    </div>
    <div class="card" id="badges">
        <h2>Badges</h2>
        <form method="post" action="{{ route('portfolio.badges') }}">
            @csrf @method('PUT')
            <div class="field">
                <label for="badges_owned">How many badges do you have?</label>
                <div class="actions" style="flex-wrap:nowrap">
                    <input type="number" min="0" id="badges_owned" name="badges_owned" value="{{ old('badges_owned', $badges) }}" style="max-width:140px" required>
                    <button class="btn">Save</button>
                </div>
            </div>
        </form>
        <p style="margin-top:0">
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
        <details>
            <summary class="small">Badge tiers</summary>
            <table style="margin-top:.5rem">
                <tbody>
                @forelse ($badgeTiers as $i => $tier)
                    @php($upTo = isset($badgeTiers[$i + 1]) ? $badgeTiers[$i + 1]['from'] - 1 : null)
                    <tr @class(['me' => $badges >= $tier['from'] && ($upTo === null || $badges <= $upTo)])>
                        <td>{{ number_format($tier['from']) }}{{ $upTo === null ? '+' : '–'.number_format($upTo) }} badges</td>
                        <td class="num">+{{ ae_num($tier['value'], 2) }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="empty">No badge tiers set.</td></tr>
                @endforelse
                </tbody>
            </table>
            <p class="muted small" style="margin-bottom:0">Badge tiers for {{ $calc->settings['boost_region_name'] ?? 'your country' }}, from {!! ae_settings_link() !!}.</p>
        </details>
    </div>
</div>

@php($m = fn ($v) => ae_earn($v))
@php($typical = $e['boost_hours'] < 24)
@php($sb = $e['super_days'] > 0)
<div class="card table-wrap">
    <h2>Earnings <span class="muted small" style="font-weight:400">(includes your +{{ ae_num($e['badge_percent'], 2) }}% badge boost)</span></h2>
    <table>
        <thead><tr>
            <th>Scenario</th><th class="num">Base</th><th class="num">With ad boost ({{ ae_num($e['boost_multiplier'], 2) }}×)</th>
            @if ($typical)<th class="num">Your typical ({{ ae_num($e['boost_hours'], 1) }}h boosted)</th>@endif
        </tr></thead>
        <tbody>
        @foreach ([['Per second', 1], ['Per hour', 3600], ['Per day', 86400], ['Per week', 604800]] as [$label, $seconds])
            <tr>
                <td>{{ $label }}</td>
                <td class="num">{{ $m($e['off_per_second'] * $seconds) }}</td>
                <td class="num">{{ $m($e['on_per_second'] * $seconds) }}</td>
                @if ($typical)<td class="num">{{ $m($e['per_day'] / 86400 * $seconds) }}</td>@endif
            </tr>
        @endforeach
        @foreach ([['Per month', 12, 'month'], ['Per year', 1, 'year']] as [$label, $div, $period])
            <tr>
                <td>{{ $label }} <span class="muted small nowrap">({{ number_format($e['normal_days'] / $div, 2).'d'.($sb ? ' + '.number_format($e['super_days'] / $div, 2).'SBd' : '') }})</span></td>
                <td class="num">{{ $m($e['base_per_'.$period]) }}</td>
                <td class="num"><strong>{{ $m($e['boosted_per_'.$period]) }}</strong></td>
                @if ($typical)<td class="num">{{ $m($e['per_year'] / $div) }}</td>@endif
            </tr>
        @endforeach
        </tbody>
    </table>
    <p class="muted small" style="margin-bottom:0">
        Base = no ad boost. With ad boost = boosted all day at your {{ ae_num($e['boost_multiplier'], 2) }}× tier{{ $sb ? ', plus '.ae_num($e['super_days'], 2).' Super Boost days (SBd) a year at '.ae_num($e['super_multiplier'], 2).'×' : '' }}.
        Month = a twelfth of a year. Rates from {!! ae_settings_link() !!}; your country and boosted hours are on <a href="{{ route('account.edit') }}#preferences">your account</a>.
    </p>
</div>

<h2 style="margin-top:.5rem">Locations</h2>
<div class="grid grid-2x2">
    <div class="card">
        <h2>👑 Where I'm in charge</h2>
        @forelse ($leading as $board)
            @php($row = $board->rowFor($me))
            @php($second = $board->rows->first(fn ($r) => $r->player->id !== $me->id))
            <div style="display:flex;justify-content:space-between;gap:.5rem;padding:.35rem 0;border-bottom:1px solid var(--border)">
                <div>
                    <a href="{{ route('locations.show', $board->location) }}"><strong>{{ $board->location->displayName() }}</strong></a>
                    <span class="chip chip-gold">{{ $board->location->leaderTitle() }}</span>
                    @if ($board->leaders()->count() > 1)<span class="chip chip-muted">tied</span>@endif
                </div>
                <div class="nowrap">{{ number_format($row->parcels) }}
                    <span class="muted small">· lead {{ $second ? number_format($row->parcels - $second->parcels) : '-' }}</span></div>
            </div>
        @empty
            <div class="empty">Not leading anywhere yet.</div>
        @endforelse
    </div>

    <div class="card">
        <h2>🎯 Closest to taking over</h2>
        @forelse ($chasing->take(10) as $board)
            @php($row = $board->rowFor($me))
            <div style="display:flex;justify-content:space-between;gap:.5rem;padding:.35rem 0;border-bottom:1px solid var(--border)">
                <div>
                    <a href="{{ route('locations.show', $board->location) }}"><strong>{{ $board->location->displayName() }}</strong></a>
                    <span class="muted small">#{{ $row->rank }} · {{ $board->leader()->player->username }} leads with {{ number_format($board->leader()->parcels) }}</span>
                </div>
                <div class="nowrap"><strong>{{ number_format($board->parcelsToLead($me)) }}</strong> <span class="muted small">to lead</span></div>
            </div>
        @empty
            <div class="empty">{{ $me ? 'Nothing to chase - nice.' : 'Mark a player as you to see this.' }}</div>
        @endforelse
    </div>

    <div class="card">
        <h2>Recent snapshots</h2>
        @forelse ($recent as $snapshot)
            <div style="display:flex;justify-content:space-between;padding:.3rem 0;border-bottom:1px solid var(--border)">
                <a href="{{ route('locations.show', $snapshot->location) }}">{{ $snapshot->location->displayName() }}</a>
                <span class="muted">{{ $snapshot->recorded_on->format('j M Y') }}</span>
            </div>
        @empty
            <div class="empty">No snapshots yet. <a href="{{ route('locations.create') }}">Add a location</a> to start.</div>
        @endforelse
    </div>

    <div class="card">
        <h2>Due an update</h2>
        <p class="muted small" style="margin-top:0">Locations with the oldest snapshot.</p>
        @forelse ($stale as $board)
            <div style="display:flex;justify-content:space-between;align-items:center;padding:.3rem 0;border-bottom:1px solid var(--border)">
                <div><a href="{{ route('locations.show', $board->location) }}">{{ $board->location->displayName() }}</a>
                    <span class="muted small">{{ $board->latest->recorded_on->diffForHumans() }}</span></div>
                <a class="btn btn-sm btn-ghost" href="{{ route('locations.snapshots.create', $board->location) }}">+ Snapshot</a>
            </div>
        @empty
            <div class="empty">Nothing yet.</div>
        @endforelse
    </div>
</div>

<div style="margin-top:1.25rem"></div>
@if ($history->count() > 1)
    <div class="card" style="margin-top:1.25rem">
        <h2>Parcels &amp; estimated earnings over time</h2>
        <div class="chart-box"><canvas id="history"></canvas></div>
    </div>
@endif

<div class="card table-wrap" style="margin-top:1.25rem">
    <h2>Parcel history</h2>
    <table>
        <thead><tr><th>Date</th>@foreach ($calc::RARITIES as $r)<th class="num">{{ ucfirst($r) }}</th>@endforeach<th class="num">Total</th><th></th></tr></thead>
        <tbody>
        @forelse ($holdings as $h)
            <tr>
                <td class="nowrap">{{ $h->recorded_on->format('j M Y') }}</td>
                @foreach ($calc::RARITIES as $r)<td class="num">{{ number_format($h->$r) }}</td>@endforeach
                <td class="num"><strong>{{ number_format($h->total()) }}</strong></td>
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

@endsection

@push('scripts')
@if ($history->count() > 1)
<script>
    (function () {
        const h = @json($history);
        const opts = AE.baseOptions();
        opts.scales.y.title = { display: true, text: 'Parcels', color: AE.textColor() };
        opts.plugins.tooltip = AE.moneyTooltip();
        opts.scales.y2 = { position: 'right', beginAtZero: true, ticks: { color: AE.textColor() }, grid: { drawOnChartArea: false },
            title: { display: true, text: 'Est. per day', color: AE.textColor() } };
        new Chart(document.getElementById('history'), {
            type: 'line',
            data: {
                labels: h.map(r => r.date),
                datasets: [
                    { label: 'Parcels', data: h.map(r => r.parcels), borderColor: AE.palette[0], backgroundColor: AE.palette[0], stepped: true },
                    { label: 'Est. per day', money: true, data: h.map(r => r.per_day), borderColor: AE.palette[1], backgroundColor: AE.palette[1], yAxisID: 'y2', stepped: true },
                ],
            },
            options: opts,
        });
    })();
</script>
@endif
@endpush
