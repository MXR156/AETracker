@extends('layouts.app')
@section('title', 'My account')
@section('content')
<div class="page-head"><h1>My account</h1></div>

<div class="card" style="max-width:560px" id="preferences">
    <h2>My game settings</h2>
    <form method="post" action="{{ route('account.preferences') }}">
        @csrf @method('PUT')
        <div class="form-row">
            <div class="field"><label for="boost_region_id">My country</label>
                <select id="boost_region_id" name="boost_region_id">
                    @foreach ($regions as $region)
                        <option value="{{ $region->id }}" @selected((string) $region->id === (string) $settings['boost_region_id'])>{{ $region->name }}{{ $region->is_default ? ' (default)' : '' }}</option>
                    @endforeach
                </select>
                <div class="hint">Sets which boost and badge tiers apply to you.</div></div>
            <div class="field"><label for="boost_hours_per_day">Hours boosted per day</label>
                <input type="number" step="0.5" min="0" max="24" id="boost_hours_per_day" name="boost_hours_per_day" value="{{ old('boost_hours_per_day', $settings['boost_hours_per_day']) }}">
                <div class="hint">24 = always boosted.</div></div>
        </div>
        <button class="btn">Save</button>
    </form>
    <p class="muted small" style="margin-bottom:0">Your parcels, badges, projection and “me” player are private to your account - nobody else can see them.</p>
</div>

<h2 style="margin-top:1.5rem">Login details</h2>
<div class="card" style="max-width:560px">
    <form method="post" action="{{ route('account.update') }}">
        @csrf @method('PUT')
        <div class="form-row">
            <div class="field"><label for="name">Name</label><input id="name" name="name" value="{{ old('name', $user->name) }}" required></div>
            <div class="field"><label for="email">Email</label><input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="username"></div>
        </div>
        <h3 style="margin-top:.5rem">Change password</h3>
        <p class="muted small" style="margin-top:0">Leave blank to keep your current password.</p>
        <div class="field"><label for="current_password">Current password</label>
            <input type="password" id="current_password" name="current_password" autocomplete="current-password"></div>
        <div class="form-row">
            <div class="field"><label for="password">New password</label>
                <input type="password" id="password" name="password" autocomplete="new-password" minlength="8"></div>
            <div class="field"><label for="password_confirmation">New password again</label>
                <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password"></div>
        </div>
        <button class="btn">Save</button>
    </form>
</div>
@endsection
