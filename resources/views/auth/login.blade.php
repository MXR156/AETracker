@extends('layouts.app')
@section('title', 'Log in')
@section('content')
<div class="card auth-box">
    <h1 style="margin-bottom:1rem">Log in</h1>
    @if ($noUsers)
        <div class="notice">There are no accounts yet. Create the first (admin) one on the server:<br>
            <code>php artisan aetracker:user you@example.com --admin</code><br>
            or with Docker: <code>docker compose exec aetracker php artisan aetracker:user you@example.com --admin</code></div>
    @endif
    <form method="post" action="{{ route('login') }}">
        @csrf
        <div class="field"><label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></div>
        <div class="field"><label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password"></div>
        <div class="field"><label style="font-weight:400"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Keep me logged in on this device</label></div>
        <button class="btn" style="width:100%">Log in</button>
    </form>
</div>
@endsection
