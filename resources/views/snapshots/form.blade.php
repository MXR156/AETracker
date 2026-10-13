@extends('layouts.app')
@section('title', ($snapshot->exists ? 'Edit' : 'New').' snapshot · '.$location->name)
@section('content')
@php($rows = old('rows', $rows))
@php($prevCounts = $previous ? $previous->entries->mapWithKeys(fn ($e) => [mb_strtolower($e->player->username) => $e->parcels]) : collect())
<div class="page-head">
    <div>
        <h1>{{ $snapshot->exists ? 'Edit snapshot' : 'New snapshot' }} · <a href="{{ route('locations.show', $location) }}">{{ $location->name }}</a></h1>
        <div class="muted">
            @if ($previous && ! $snapshot->exists) Pre-filled from {{ $previous->recorded_on->format('j M Y') }} - just change the numbers that moved. @endif
            Set a player to 0 (or blank) to remove them from this snapshot.
        </div>
    </div>
</div>

<form method="post" action="{{ $snapshot->exists ? route('snapshots.update', $snapshot) : route('locations.snapshots.store', $location) }}">
    @csrf
    @if ($snapshot->exists) @method('PUT') @endif

    <div class="grid grid-2">
        <div class="card">
            <div class="form-row">
                <div class="field">
                    <label for="recorded_on">Date</label>
                    <input type="date" id="recorded_on" name="recorded_on" value="{{ old('recorded_on', $snapshot->recorded_on?->format('Y-m-d')) }}" required>
                </div>
                <div class="field">
                    <label for="notes">Notes <span class="muted">(optional)</span></label>
                    <input id="notes" name="notes" value="{{ old('notes', $snapshot->notes) }}">
                </div>
            </div>

            <table class="entry-table" id="entries">
                <thead><tr><th>Player</th><th class="num" style="width:120px">Parcels</th>@if ($previous)<th class="num" style="width:90px">Was</th>@endif<th style="width:30px"></th></tr></thead>
                <tbody>
                @foreach ($rows as $i => $row)
                    <tr>
                        <td><input name="rows[{{ $i }}][username]" value="{{ $row['username'] ?? '' }}" list="players"></td>
                        <td><input type="number" min="0" name="rows[{{ $i }}][parcels]" value="{{ $row['parcels'] ?? '' }}" class="right"></td>
                        @if ($previous)<td class="num muted">{{ $prevCounts->get(mb_strtolower($row['username'] ?? ''), '-') }}</td>@endif
                        <td><button type="button" class="link-btn" title="Remove" onclick="this.closest('tr').remove()">✕</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="actions" style="margin-top:.75rem">
                <button type="button" class="btn btn-ghost btn-sm" onclick="addRow()">+ Add player</button>
            </div>
        </div>

        <div class="card">
            <h3>Quick paste</h3>
            <p class="muted small" style="margin-top:0">One player per line with their parcel count last, e.g.<br>
                <code>SuperSparrow 104</code> · <code>2. LogisticHawk - 85</code> · <code>Kage_uvlf,22</code><br>
                Pasted players are added to (or override) the table.</p>
            <textarea name="paste" rows="12" placeholder="SuperSparrow 104&#10;LogisticHawk 85">{{ old('paste') }}</textarea>
        </div>
    </div>

    <div class="actions" style="margin-top:1rem">
        <button class="btn">Save snapshot</button>
        <a href="{{ route('locations.show', $location) }}">Cancel</a>
    </div>
</form>

<datalist id="players">
    @foreach (\App\Models\Player::orderBy('username')->pluck('username') as $name)<option value="{{ $name }}">@endforeach
</datalist>
@endsection

@push('scripts')
<script>
    let rowIndex = {{ count($rows) + 1000 }};
    function addRow() {
        const i = rowIndex++;
        const tr = document.createElement('tr');
        tr.innerHTML = `<td><input name="rows[${i}][username]" list="players"></td>
            <td><input type="number" min="0" name="rows[${i}][parcels]" class="right"></td>
            @if ($previous)<td class="num muted">new</td>@endif
            <td><button type="button" class="link-btn" title="Remove" onclick="this.closest('tr').remove()">✕</button></td>`;
        document.querySelector('#entries tbody').appendChild(tr);
        tr.querySelector('input').focus();
    }
    @if (empty($rows)) for (let n = 0; n < 5; n++) addRow(); @endif
</script>
@endpush
