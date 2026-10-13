@extends('layouts.app')
@section('title', $location->exists ? 'Edit '.$location->name : 'Add location')
@section('content')
<div class="page-head"><h1>{{ $location->exists ? 'Edit '.$location->name : 'Add a location' }}</h1></div>

<div class="card" style="max-width:640px">
    <form method="post" action="{{ $location->exists ? route('locations.update', $location) : route('locations.store') }}">
        @csrf
        @if ($location->exists) @method('PUT') @endif
        <div class="form-row">
            <div class="field">
                <label for="name">Name</label>
                <input id="name" name="name" value="{{ old('name', $location->name) }}" required autofocus>
            </div>
            <div class="field">
                <label for="type">Type</label>
                <select id="type" name="type">
                    @foreach (config('aetracker.location_types') as $type => $title)
                        <option value="{{ $type }}" @selected(old('type', $location->type) === $type)>{{ ucfirst($type) }} ({{ $title }})</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="field">
            <label for="region">Region <span class="muted">(optional)</span></label>
            <input id="region" name="region" value="{{ old('region', $location->region) }}" placeholder="e.g. Essex">
            <div class="hint">Useful when two places share a name.</div>
        </div>
        <div class="field">
            <label for="notes">Notes <span class="muted">(optional)</span></label>
            <textarea id="notes" name="notes" style="min-height:4rem;font-family:inherit">{{ old('notes', $location->notes) }}</textarea>
        </div>
        <div class="actions">
            <button class="btn">{{ $location->exists ? 'Save' : 'Add location' }}</button>
            <a href="{{ $location->exists ? route('locations.show', $location) : route('locations.index') }}">Cancel</a>
        </div>
    </form>
</div>

@if ($location->exists)
    <div class="card" style="max-width:640px">
        <h3>Delete location</h3>
        <p class="muted small">This also deletes all of its snapshots. Players stay.</p>
        <form method="post" action="{{ route('locations.destroy', $location) }}" onsubmit="return confirm('Delete {{ $location->name }} and all its snapshots?')">
            @csrf @method('DELETE')
            <button class="btn btn-danger">Delete {{ $location->name }}</button>
        </form>
    </div>
@endif
@endsection
