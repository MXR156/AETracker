@extends('layouts.app')
@section('title', 'Players')
@section('content')
<div class="page-head">
    <div><h1>Players</h1><div class="muted">Everyone seen on a leaderboard and where they currently hold parcels.</div></div>
</div>

<form class="filters" method="get">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search username">
    <span class="tabs">
        @foreach (['' => 'All', '2' => 'In 2+ locations', '3' => 'In 3+ locations'] as $key => $label)
            <a href="{{ request()->fullUrlWithQuery(['min' => $key ?: null]) }}" @class(['active' => (string) request('min') === (string) $key])>{{ $label }}</a>
        @endforeach
    </span>
    <button class="btn btn-ghost btn-sm">Search</button>
</form>

<div class="card table-wrap">
    <table>
        <thead><tr>
            <th>Player</th><th class="num">Parcels*</th><th class="num">Leader of</th>@if ($me)<th class="num hide-sm">Shared with me</th>@endif<th>Locations</th>
        </tr></thead>
        <tbody>
        @forelse ($players as $p)
            <tr @class(['me' => $p->player->is_me])>
                <td><a href="{{ route('players.show', $p->player) }}"><strong>{{ $p->player->username }}</strong></a>
                    @if ($p->player->is_me)<span class="chip">me</span>@endif</td>
                <td class="num">{{ number_format($p->total) }}</td>
                <td class="num">{{ $p->leads ?: '' }}</td>
                @if ($me)<td class="num hide-sm">{{ $p->shared ?: '' }}</td>@endif
                <td class="small">
                    @foreach ($p->locations->sortByDesc(fn ($l) => $l->row->parcels) as $pos)
                        <a href="{{ route('locations.show', $pos->location) }}">{{ $pos->location->name }}</a>
                        <span class="muted">{{ $pos->is_leader ? '👑' : '#'.$pos->row->rank }} {{ $pos->row->parcels }}</span>@if (! $loop->last) · @endif
                    @endforeach
                    @if ($p->locations->isEmpty())<span class="muted">not on any current board</span>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">No players yet - they're added automatically when you save a snapshot.</td></tr>
        @endforelse
        </tbody>
    </table>
    <p class="muted small" style="margin-bottom:0">* Total parcels across the locations you track (latest snapshot of each), not their whole account.</p>
</div>
@endsection
