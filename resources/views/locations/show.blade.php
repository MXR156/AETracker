@extends('layouts.app')
@section('title', $location->name)
@section('content')
@php($leader = $board->leader())
@php($mine = $board->rowFor($me))
<div class="page-head">
    <div>
        <h1>{{ $location->name }}</h1>
        <div class="muted">{{ ucfirst($location->type) }}@if ($location->region) · {{ $location->region }}@endif
            · {{ $board->snapshots->count() }} {{ Str::plural('snapshot', $board->snapshots->count()) }}
            @if ($board->latest) · last updated {{ $board->latest->recorded_on->format('j M Y') }} ({{ $board->latest->recorded_on->diffForHumans() }}) @endif
        </div>
        @if ($location->notes)<div class="small" style="margin-top:.35rem">{{ $location->notes }}</div>@endif
    </div>
    <div class="actions">
        <a href="{{ route('locations.snapshots.create', $location) }}" class="btn">+ Add snapshot</a>
        <a href="{{ route('import.index') }}" class="btn btn-ghost">Import from Atlas Earth</a>
        <a href="{{ route('locations.edit', $location) }}" class="btn btn-ghost">Edit</a>
    </div>
</div>

@if ($leader)
    @php($runnerUp = $board->rows->first(fn ($r) => $r->rank > 1))
    <div class="mayor-banner">
        <div class="crown">👑</div>
        <div>
            <div class="muted small">{{ $location->leaderTitle() }} of {{ $location->name }}</div>
            <div class="who">
                @foreach ($board->leaders() as $l)
                    <a href="{{ route('players.show', $l->player) }}">{{ $l->player->username }}</a>@if (! $loop->last) &amp; @endif
                @endforeach
                <span class="muted" style="font-weight:400">· {{ number_format($leader->parcels) }} parcels</span>
            </div>
            <div class="small">
                @if ($board->leaders()->count() > 1)
                    Tied at the top - one more parcel takes it outright.
                @elseif ($runnerUp)
                    Leads {{ $runnerUp->player->username }} by {{ number_format($leader->parcels - $runnerUp->parcels) }}.
                @endif
                @if ($me && $mine && $mine->rank === 1 && $board->leaders()->count() === 1)
                    <strong>That's you!</strong>
                @elseif ($me)
                    You need <strong>{{ number_format($board->parcelsToLead($me)) }}</strong> more {{ Str::plural('parcel', $board->parcelsToLead($me)) }} to become {{ $location->leaderTitle() }}.
                @endif
            </div>
        </div>
    </div>
@endif

@if (! $board->latest)
    <div class="card empty">No snapshots yet. <a href="{{ route('locations.snapshots.create', $location) }}">Add the first one</a>.</div>
@else
    <div class="stats">
        <div class="stat"><div class="label">Players</div><div class="value">{{ $board->rows->count() }}</div></div>
        <div class="stat"><div class="label">Parcels on board</div><div class="value">{{ number_format($board->rows->sum('parcels')) }}</div>
            @if ($board->previous)<div class="sub">{!! ae_change($board->rows->sum('parcels') - $board->previous->entries->sum('parcels')) !!} since {{ $board->previous->recorded_on->format('j M') }}</div>@endif</div>
        @if ($mine)
            <div class="stat"><div class="label">My position</div><div class="value">#{{ $mine->rank }}</div><div class="sub">{{ number_format($mine->parcels) }} parcels ({{ number_format($mine->share, 1) }}%)</div></div>
            <div class="stat"><div class="label">My growth</div><div class="value">{!! ae_change($mine->since_first) !!}</div><div class="sub">since {{ $mine->first_seen->format('j M Y') }}</div></div>
        @endif
    </div>

    <div class="card">
        <div class="page-head" style="margin-bottom:.5rem">
            <h2 style="margin:0">Leaderboard <span class="muted small" style="font-weight:400">{{ $board->latest->recorded_on->format('j M Y') }}</span></h2>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr>
                    <th>#</th><th>Player</th><th class="num">Parcels</th>
                    <th class="num">Change @if ($board->previous)<div class="small" style="text-transform:none">vs {{ $board->previous->recorded_on->format('j M') }}</div>@endif</th>
                    <th class="num hide-sm">Gain since first seen</th><th class="hide-sm">Share</th><th class="num">Gap to #1</th>
                </tr></thead>
                <tbody>
                @foreach ($board->rows as $row)
                    <tr @class(['me' => $row->player->is_me])>
                        <td><span @class(['rank', 'rank-1' => $row->rank === 1])>{{ $row->rank === 1 ? '👑' : $row->rank }}</span></td>
                        <td><a href="{{ route('players.show', $row->player) }}">{{ $row->player->username }}</a>
                            @if ($row->player->is_me)<span class="chip">me</span>@endif
                            @if ($row->player->isGhost())<span class="chip chip-muted" title="Deleted account or no name set">anon</span>@endif</td>
                        <td class="num"><strong>{{ number_format($row->parcels) }}</strong></td>
                        <td class="num">{!! ae_change($row->change, $row->is_new) !!}</td>
                        <td class="num hide-sm">{!! ae_change($row->since_first) !!} <span class="muted small">({{ $row->first_seen->format('j M y') }})</span></td>
                        <td class="hide-sm"><div class="bar" title="{{ number_format($row->share, 1) }}%"><span style="width:{{ min(100, $row->share) }}%"></span></div></td>
                        <td class="num">{{ $row->rank === 1 ? '' : number_format($leader->parcels - $row->parcels) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if ($board->dropped()->isNotEmpty())
            <p class="small muted" style="margin-bottom:0">Gone since {{ $board->previous->recorded_on->format('j M') }}:
                @foreach ($board->dropped() as $entry)
                    <a href="{{ route('players.show', $entry->player) }}">{{ $entry->player->username }}</a> ({{ $entry->parcels }})@if (! $loop->last), @endif
                @endforeach
            </p>
        @endif
    </div>

    <div class="card">
        <div class="page-head" style="margin-bottom:.5rem">
            <h2 style="margin:0">Growth over time</h2>
            <form method="get" class="filters" style="margin:0">
                <label for="top" style="margin:0" class="small muted">Show top</label>
                <select id="top" name="top" onchange="this.form.submit()">
                    @foreach ([5, 10, 20, 50] as $n)<option value="{{ $n }}" @selected((int) request('top', 10) === $n)>{{ $n }}</option>@endforeach
                </select>
            </form>
        </div>
        @if ($board->snapshots->count() < 2)
            <p class="muted small">Add another snapshot to see the trend lines.</p>
        @endif
        <div class="chart-box"><canvas id="growth"></canvas></div>
    </div>

    <div class="card table-wrap">
        <h2>Snapshot history</h2>
        <table>
            <thead><tr><th>Date</th><th class="num">Players</th><th class="num">Parcels</th><th>Leader</th><th class="hide-sm">Notes</th><th></th></tr></thead>
            <tbody>
            @foreach ($board->snapshots->reverse() as $snapshot)
                @php($top = $snapshot->entries->sortByDesc('parcels')->first())
                <tr>
                    <td class="nowrap">{{ $snapshot->recorded_on->format('j M Y') }}</td>
                    <td class="num">{{ $snapshot->entries->count() }}</td>
                    <td class="num">{{ number_format($snapshot->entries->sum('parcels')) }}</td>
                    <td>{{ $top?->player->username }} <span class="muted small">{{ $top?->parcels }}</span></td>
                    <td class="hide-sm small muted">{{ Str::limit($snapshot->notes, 60) }}</td>
                    <td class="right nowrap">
                        <a href="{{ route('snapshots.edit', $snapshot) }}">Edit</a>
                        <form class="inline" method="post" action="{{ route('snapshots.destroy', $snapshot) }}" onsubmit="return confirm('Delete the {{ $snapshot->recorded_on->format('j M Y') }} snapshot?')">
                            @csrf @method('DELETE')
                            <button class="link-btn" style="color:var(--down);margin-left:.5rem">Delete</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection

@push('scripts')
@if ($board->latest)
<script>
    AE.playerLines(document.getElementById('growth'), @json($chart));
</script>
@endif
@endpush
