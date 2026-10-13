@extends('layouts.app')
@section('title', $player->username)
@section('content')
<div class="page-head">
    <div>
        <h1>{{ $player->username }} @if ($player->is_me)<span class="chip">me</span>@endif</h1>
        <div class="muted">On {{ $rows->count() }} current {{ Str::plural('board', $rows->count()) }} ·
            {{ number_format($rows->sum(fn ($r) => $r->row->parcels)) }} parcels in tracked locations ·
            {{ $rows->where('is_leader', true)->count() }} × leader</div>
        @if ($player->notes)<div class="small" style="margin-top:.35rem">{{ $player->notes }}</div>@endif
    </div>
    @unless ($player->is_me)
        <form method="post" action="{{ route('players.me', $player) }}">@csrf<button class="btn btn-ghost">This is me</button></form>
    @endunless
</div>

<div class="card table-wrap">
    <h2>Locations</h2>
    <table>
        <thead><tr>
            <th>Location</th><th class="num">Rank</th><th class="num">Parcels</th><th class="num">Change</th><th class="num hide-sm">Since first seen</th>
            @if ($me && ! $player->is_me)<th class="num">Me</th><th class="num">Diff</th>@endif
            <th class="hide-sm">As of</th>
        </tr></thead>
        <tbody>
        @forelse ($rows as $r)
            <tr>
                <td><a href="{{ route('locations.show', $r->location) }}">{{ $r->location->displayName() }}</a>
                    @if ($r->is_leader)<span class="chip chip-gold">{{ $r->location->leaderTitle() }}</span>@endif</td>
                <td class="num">{{ $r->is_leader ? '👑' : '#'.$r->row->rank }}</td>
                <td class="num"><strong>{{ number_format($r->row->parcels) }}</strong></td>
                <td class="num">{!! ae_change($r->row->change, $r->row->is_new) !!}</td>
                <td class="num hide-sm">{!! ae_change($r->row->since_first) !!}</td>
                @if ($me && ! $player->is_me)
                    <td class="num">{{ $r->mine ? number_format($r->mine->parcels) : '-' }}</td>
                    <td class="num">{!! $r->mine ? ae_change($r->mine->parcels - $r->row->parcels) : '' !!}</td>
                @endif
                <td class="hide-sm muted small">{{ $r->board->latest->recorded_on->format('j M Y') }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">Not on any current leaderboard.</td></tr>
        @endforelse
        </tbody>
    </table>
    @if ($past->isNotEmpty())
        <p class="small muted" style="margin-bottom:0">Previously seen in:
            @foreach ($past as $loc)<a href="{{ route('locations.show', $loc) }}">{{ $loc->name }}</a>@if (! $loop->last), @endif @endforeach
        </p>
    @endif
</div>

<div class="grid grid-2">
    <div class="card">
        <h3>Edit player</h3>
        <form method="post" action="{{ route('players.update', $player) }}">
            @csrf @method('PUT')
            <div class="field"><label for="username">Username</label><input id="username" name="username" value="{{ old('username', $player->username) }}" required></div>
            <div class="field"><label for="notes">Notes</label><textarea id="notes" name="notes" style="min-height:3rem;font-family:inherit">{{ old('notes', $player->notes) }}</textarea></div>
            <button class="btn btn-sm">Save</button>
        </form>
    </div>
    <div class="card">
        <h3>Delete player</h3>
        <p class="muted small">Removes {{ $player->username }} from every snapshot. Use this to clean up typos.</p>
        <form method="post" action="{{ route('players.destroy', $player) }}" onsubmit="return confirm('Delete {{ $player->username }} from every snapshot?')">
            @csrf @method('DELETE')
            <button class="btn btn-danger btn-sm">Delete</button>
        </form>
    </div>
</div>
@endsection
