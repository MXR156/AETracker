@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<div class="page-head">
    <div>
        <h1>Dashboard</h1>
        <div class="muted">
            @if ($me) Tracking as <a href="{{ route('players.show', $me) }}"><strong>{{ $me->username }}</strong></a>
            @else No player is marked as you yet - open your player page and click <em>“This is me”</em>. @endif
        </div>
    </div>
    <div class="actions">
        <a href="{{ route('locations.create') }}" class="btn">+ Add location</a>
    </div>
</div>

<div class="stats">
    <div class="stat"><div class="label">Locations tracked</div><div class="value">{{ $boards->count() }}</div></div>
    @if ($me)
        <div class="stat"><div class="label">My locations</div><div class="value">{{ $leading->count() + $chasing->count() }}</div><div class="sub">{{ number_format($leading->sum(fn($b) => $b->rowFor($me)->parcels) + $chasing->sum(fn($b) => $b->rowFor($me)->parcels)) }} parcels on boards</div></div>
        <div class="stat"><div class="label">Leader in</div><div class="value">{{ $leading->count() }}</div><div class="sub">of {{ $leading->count() + $chasing->count() }}</div></div>
    @endif
    <div class="stat"><div class="label">Est. earnings / day</div><div class="value">{{ ae_money($earnings['per_day']) }}</div><div class="sub">{{ ae_money($earnings['per_month'], 2) }} / month · <a href="{{ route('portfolio.index') }}">details</a></div></div>
</div>

<div class="grid grid-2">
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
@endsection
