@extends('layouts.app')
@section('title', 'Import from Atlas Earth')
@section('content')
<div class="page-head">
    <div><h1>Import from Atlas Earth</h1>
        <div class="muted">Grab a leaderboard straight from the Atlas Earth map in your browser - no copying and pasting.</div></div>
</div>

@if ($insecureTarget)
    <div class="alert alert-err">
        <strong>The button below would send to an insecure <code>http://</code> address</strong>, but you're viewing this site over https
        (e.g. through Cloudflare). Your browser will warn that the form isn't secure, and the import won't arrive.<br>
        Fix: set <code>APP_URL</code> to your https address (e.g. <code>APP_URL: "https://{{ request()->getHost() }}"</code>) in
        <code>docker-compose.yml</code>, run <code>docker compose up -d</code>, then come back here and drag the button to your bookmarks bar again.
    </div>
@endif

<div class="grid grid-2">
    <div class="card">
        <h2>1. Add the button to your bookmarks bar</h2>
        <p>Drag this button onto your browser's bookmarks bar:</p>
        <p style="text-align:center;margin:1.25rem 0">
            <a href="{{ $bookmarklet }}" class="btn" onclick="alert('Drag this button to your bookmarks bar, then click it while you are on the Atlas Earth map.'); return false;">⬇ Send to AE Tracker</a>
        </p>
        <p class="muted small">Can't see the bookmarks bar? Press <strong>Ctrl+Shift+B</strong> (Cmd+Shift+B on a Mac).
            If you can't drag it, right-click the button, choose <em>Copy link</em>, then add a new bookmark and paste that in as its URL.</p>
        <p class="muted small">The button points at <code>{{ route('import.receive') }}</code>. If you move the tracker to a different address, come back here and add the button again.</p>
    </div>

    <div class="card">
        <h2>2. Use it</h2>
        <ol style="padding-left:1.2rem;margin-top:0">
            <li>Log in at <strong>app.atlasearth.com/map</strong> and go to a place.</li>
            <li>Open the leaderboard (trophy button) and pick the <strong>town/city</strong> tab - or the county or country tab.</li>
            <li>Click <strong>Send to AE Tracker</strong> in your bookmarks bar.</li>
            <li>A tracker tab opens showing the place, the players and what's changed. Check it and click <strong>Save snapshot</strong>.</li>
        </ol>
        <p class="muted small">The first time you import a place, choose which of your locations it is (or create it). After that it's matched automatically.
            Nothing is saved until you click Save, and your Atlas Earth password is never seen by the tracker.</p>
    </div>
</div>

<div class="card">
    <h2>Didn't open?</h2>
    <p class="muted small" style="margin-top:0">If your browser blocked the tracker tab, the bookmarklet also copied the leaderboard to your clipboard. Paste it here:</p>
    <form method="post" action="{{ route('import.receive') }}">
        @csrf
        <textarea name="payload" rows="5" placeholder='{"tab":"city","place":"marston moretaine, bdf, eng", ...}'>{{ old('payload') }}</textarea>
        <div style="margin-top:.75rem"><button class="btn btn-ghost">Review import</button></div>
    </form>
</div>
@endsection
