@extends('layouts.app')
@section('title', 'Review import')
@section('content')
<div class="page-head">
    <div>
        <h1>Review import</h1>
        <div class="muted">{{ $import->tabLabel() }} leaderboard for <strong>{{ $import->name }}</strong>@if ($import->region), {{ $import->region }}@endif
            · {{ count($import->rows) }} players</div>
    </div>
</div>

@foreach ($import->warnings as $warning)
    <div class="notice">{{ $warning }}</div>
@endforeach

<form method="post" action="{{ route('import.save', $token) }}">
    @csrf
    <div class="grid grid-2">
        <div class="card">
            <h2>Save to</h2>
            <div class="field">
                <label for="location">Location</label>
                <select id="location" name="location" onchange="window.location.href='{{ route('import.review', $token) }}?location=' + this.value">
                    <option value="new" @selected(! $location)>+ Create a new location</option>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}" @selected($location?->id === $loc->id)>{{ $loc->displayName() }} ({{ $loc->type }})@if ($matched?->id === $loc->id) - matched @endif</option>
                    @endforeach
                </select>
                @if ($location && $matched?->id === $location->id)
                    <div class="hint">Matched automatically{{ $location->atlas_key ? ' from a previous import' : ' by name' }}.</div>
                @elseif (! $location && ! $matched)
                    <div class="hint">No existing location matched - check the list in case it's there under another name.</div>
                @endif
            </div>

            @unless ($location)
                <div class="form-row">
                    <div class="field"><label for="name">Name</label><input id="name" name="name" value="{{ old('name', $import->name) }}" required></div>
                    <div class="field"><label for="type">Type</label>
                        <select id="type" name="type">
                            @foreach (config('aetracker.location_types') as $type => $title)
                                <option value="{{ $type }}" @selected(old('type', $import->defaultType()) === $type)>{{ ucfirst($type) }} ({{ $title }})</option>
                            @endforeach
                        </select></div>
                    <div class="field"><label for="region">Region</label><input id="region" name="region" value="{{ old('region', $import->region) }}"></div>
                </div>
            @endunless

            <div class="form-row">
                <div class="field"><label for="recorded_on">Date</label><input type="date" id="recorded_on" name="recorded_on" value="{{ old('recorded_on', $date) }}" required></div>
                <div class="field"><label for="notes">Notes</label><input id="notes" name="notes" value="{{ old('notes') }}"></div>
            </div>
            @if ($existing)
                <div class="notice">There's already a snapshot for {{ $location->name }} today - saving will replace it.</div>
            @endif
            <button class="btn">Save snapshot</button>
            <a href="{{ route('import.index') }}" style="margin-left:.5rem">Cancel</a>
        </div>

        <div class="card">
            <h2>Summary</h2>
            @php($top = collect($rows)->first())
            @if ($top)
                <p style="margin-top:0">👑 <strong>{{ $top->name }}</strong> leads with {{ number_format($top->parcels) }} parcels.</p>
            @endif
            @if ($location && $previous)
                <ul style="padding-left:1.2rem">
                    <li>Compared with {{ $previous->recorded_on->format('j M Y') }}.</li>
                    <li>{{ collect($rows)->where('is_new', true)->count() }} new on the board, {{ $gone->count() }} gone.</li>
                    <li>Parcels on board: {{ number_format(collect($rows)->sum('parcels')) }} ({!! ae_change(collect($rows)->sum('parcels') - $previous->entries->sum('parcels')) !!}).</li>
                </ul>
            @elseif ($location)
                <p class="muted">This will be the first snapshot for {{ $location->name }}.</p>
            @else
                <p class="muted">A new location will be created.</p>
            @endif
            <p class="muted small">Each “Anonymous” becomes its own anonymous player for this location, numbered biggest first.</p>
            @if ($import->source)<p class="muted small">From <code>{{ Str::limit($import->source, 80) }}</code></p>@endif
        </div>
    </div>
</form>

<div class="card table-wrap" style="margin-top:1.25rem">
    <table>
        <thead><tr><th>#</th><th>Player</th><th class="num">Parcels</th><th class="num">Change</th><th class="hide-sm">Title</th></tr></thead>
        <tbody>
        @foreach ($rows as $i => $row)
            @php($src = collect($import->rows)->firstWhere('username', $row->name))
            <tr @class(['me' => $row->is_me])>
                <td>{{ $i + 1 }}</td>
                <td>{{ $row->name }} @if ($row->is_me)<span class="chip">me</span>@endif
                    @if (! $row->known && ! str_starts_with($row->name, 'Anonymous'))<span class="chip chip-muted" title="Not seen in any location yet">new player</span>@endif</td>
                <td class="num"><strong>{{ number_format($row->parcels) }}</strong></td>
                <td class="num">{!! $location && $previous ? ae_change($row->change, $row->is_new) : '' !!}</td>
                <td class="hide-sm small muted">@if ($src && $src['title']){{ $src['title'] }} of {{ $src['title_place'] }}@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @if ($gone->isNotEmpty())
        <p class="small muted" style="margin-bottom:0">No longer on the board:
            @foreach ($gone as $entry){{ $entry->player->username }} ({{ $entry->parcels }})@if (! $loop->last), @endif @endforeach</p>
    @endif
</div>
@endsection
