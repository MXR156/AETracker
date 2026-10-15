@extends('layouts.app')
@section('title', 'Create an account')
@section('content')
<div class="card auth-box">
    <h1 style="margin-bottom:.25rem">Create an account</h1>
    <p class="muted small" style="margin-top:0">Track your own parcels, earnings and progress against other players. Your stats are private to you.</p>
    <form method="post" action="{{ route('register') }}">
        @csrf
        <div class="field"><label for="name">Name</label>
            <input id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"></div>
        <div class="field"><label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="username"></div>
        <div class="field"><label for="password">Password</label>
            <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
            <div class="hint">At least 8 characters.</div></div>
        <div class="field"><label for="password_confirmation">Password again</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"></div>
        <button class="btn" style="width:100%">Create account</button>
    </form>
    <p class="small" style="margin-bottom:0;text-align:center">Already have one? <a href="{{ route('login') }}">Log in</a></p>
</div>
@endsection
