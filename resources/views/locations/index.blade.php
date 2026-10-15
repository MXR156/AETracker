@extends('layouts.app')
@section('title', 'Locations')
@section('content')
<div class="page-head">
    <div><h1>Locations</h1><div class="muted">Who runs each place, from the latest snapshot.</div></div>
    @auth<a href="{{ route('locations.create') }}" class="btn">+ Add location</a>@endauth
</div>

<form class="filters" method="get">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name or region">
    <select name="type" onchange="this.form.submit()">
        <option value="">All types</option>
        @foreach (config('aetracker.location_types') as $type => $title)
            <option value="{{ $type }}" @selected(request('type') === $type)>{{ ucfirst($type) }}</option>
        @endforeach
    </select>
    @if ($me)
        <span class="tabs">
            @foreach (['' => 'All', 'mine' => "I'm on the board", 'mayor' => "I'm leader", 'not-mayor' => "I'm not leader"] as $key => $label)
                <a href="{{ request()->fullUrlWithQuery(['filter' => $key ?: null]) }}" @class(['active' => (string) request('filter') === $key])>{{ $label }}</a>
            @endforeach
        </span>
    @endif
    <button class="btn btn-ghost btn-sm">Filter</button>
</form>

<div class="card table-wrap">
    <table>
        <thead><tr>
            <th>Location</th><th class="hide-sm">Type</th><th>Leader</th><th class="num">Parcels</th>
            @if ($me)<th class="num">Me</th><th class="num">To lead</th>@endif
            <th class="num hide-sm">Players</th><th class="hide-sm">Last snapshot</th>
        </tr></thead>
        <tbody>
        @forelse ($boards as $board)
            @php($leader = $board->leader())
            @php($mine = $board->rowFor($me))
            <tr>
                <td><a href="{{ route('locations.show', $board->location) }}"><strong>{{ $board->location->name }}</strong></a>
                    @if ($board->location->region)<div class="muted small">{{ $board->location->region }}</div>@endif</td>
                <td class="hide-sm">{{ ucfirst($board->location->type) }}</td>
                <td>
                    @if ($leader)
                        👑 <a href="{{ route('players.show', $leader->player) }}">{{ $leader->player->username }}</a>
                        @if ($board->leaders()->count() > 1)<span class="chip chip-muted">tied ×{{ $board->leaders()->count() }}</span>@endif
                    @else <span class="muted">no snapshots</span> @endif
                </td>
                <td class="num">{{ $leader ? number_format($leader->parcels) : '' }}</td>
                @if ($me)
                    <td class="num">@if ($mine) #{{ $mine->rank }} · {{ number_format($mine->parcels) }} @else <span class="muted">-</span> @endif</td>
                    <td class="num">
                        @if (! $leader) @elseif ($mine && $mine->rank === 1 && $board->leaders()->count() === 1) <span class="chip chip-gold">{{ $board->location->leaderTitle() }}</span>
                        @else {{ number_format($board->parcelsToLead($me)) }} @endif
                    </td>
                @endif
                <td class="num hide-sm">{{ $board->rows->count() }}</td>
                <td class="hide-sm">{{ $board->latest?->recorded_on->format('j M Y') ?? '-' }}
                    <span class="muted small">({{ $board->snapshots->count() }})</span></td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">No locations found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
