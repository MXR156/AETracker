@extends('layouts.app')
@section('title', 'Atlas Earth leaderboards')
@section('content')
<div class="home-hero">
    <div>
        <h1 style="font-size:2rem">Who owns Atlas Earth?</h1>
        <p class="muted" style="font-size:1.05rem;max-width:560px">Leaderboards, mayors and growth for the places we track, updated from the Atlas Earth map.
            Log in or create an account to track your own parcels, earnings and how you stack up.</p>
        <div class="stats" style="margin-top:1.25rem">
            <div class="stat"><div class="label">Locations</div><div class="value">{{ number_format($stats['locations']) }}</div></div>
            <div class="stat"><div class="label">Players</div><div class="value">{{ number_format($stats['players']) }}</div></div>
            <div class="stat"><div class="label">Snapshots</div><div class="value">{{ number_format($stats['snapshots']) }}</div></div>
            <div class="stat"><div class="label">Parcels on boards</div><div class="value">{{ number_format($stats['parcels']) }}</div></div>
        </div>
        <a href="{{ route('locations.index') }}" class="btn btn-ghost">Browse all locations →</a>
    </div>

    <div class="card" style="margin:0">
        <h2>Log in</h2>
        <form method="post" action="{{ route('login') }}">
            @csrf
            <div class="field"><label for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="username"></div>
            <div class="field"><label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password"></div>
            <div class="field"><label style="font-weight:400"><input type="checkbox" name="remember" value="1"> Keep me logged in</label></div>
            <button class="btn" style="width:100%">Log in</button>
        </form>
        @if ($canRegister)
            <p class="small" style="margin-bottom:0;text-align:center">New here? <a href="{{ route('register') }}">Create an account</a> - it's free and your stats stay private.</p>
        @endif
    </div>
</div>

<div class="grid grid-2x2" style="margin-top:1.5rem">
    <div class="card">
        <h2>👑 Mayors</h2>
        @forelse ($mayors as $board)
            @php($runnerUp = $board->rows->first(fn ($r) => $r->rank > 1))
            <div class="list-row">
                <div><a href="{{ route('locations.show', $board->location) }}"><strong>{{ $board->location->displayName() }}</strong></a>
                    <span class="muted small">{{ $board->leaders()->pluck('player.username')->implode(' & ') }}</span></div>
                <div class="nowrap">{{ number_format($board->leader()->parcels) }}
                    @if ($runnerUp)<span class="muted small">· lead {{ number_format($board->leader()->parcels - $runnerUp->parcels) }}</span>@endif</div>
            </div>
        @empty
            <div class="empty">No locations yet.</div>
        @endforelse
    </div>

    <div class="card">
        <h2>Biggest landowners</h2>
        <p class="muted small" style="margin-top:0">Across the locations tracked here.</p>
        @forelse ($landowners as $i => $l)
            <div class="list-row">
                <div><span class="muted">{{ $i + 1 }}.</span> <a href="{{ route('players.show', $l->player) }}">{{ $l->player->username }}</a>
                    <span class="muted small">{{ $l->locations }} {{ Str::plural('location', $l->locations) }}@if ($l->leads) · 👑 {{ $l->leads }}@endif</span></div>
                <div class="nowrap"><strong>{{ number_format($l->parcels) }}</strong></div>
            </div>
        @empty
            <div class="empty">No players yet.</div>
        @endforelse
    </div>

    <div class="card">
        <h2>Biggest recent gains</h2>
        <p class="muted small" style="margin-top:0">Since each location's previous snapshot.</p>
        @forelse ($gains as $g)
            <div class="list-row">
                <div><a href="{{ route('players.show', $g->row->player) }}">{{ $g->row->player->username }}</a>
                    <span class="muted small">in <a href="{{ route('locations.show', $g->board->location) }}">{{ $g->board->location->name }}</a></span></div>
                <div class="nowrap">{!! ae_change($g->row->change) !!} <span class="muted small">→ {{ number_format($g->row->parcels) }}</span></div>
            </div>
        @empty
            <div class="empty">Gains show up once a location has two snapshots.</div>
        @endforelse
    </div>

    <div class="card">
        <h2>Recently updated</h2>
        @forelse ($recent as $snapshot)
            <div class="list-row">
                <a href="{{ route('locations.show', $snapshot->location) }}">{{ $snapshot->location->displayName() }}</a>
                <span class="muted">{{ $snapshot->recorded_on->format('j M Y') }}</span>
            </div>
        @empty
            <div class="empty">Nothing yet.</div>
        @endforelse
    </div>
</div>
@endsection
