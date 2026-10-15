@extends('layouts.app')
@section('title', 'My account')
@section('content')
<div class="page-head"><h1>My account</h1></div>
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
