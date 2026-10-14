<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · AE Tracker</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/chart.umd.min.js') }}"></script>
    <script src="{{ asset('js/charts.js') }}"></script>
    <script>window.AE_CURRENCY = @json(ae_currency());</script>
</head>
<body>
<header class="top">
    <div class="inner">
        <a href="{{ route('dashboard') }}" class="brand">AE <span>Tracker</span></a>
        <nav>
            <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])>Dashboard</a>
            <a href="{{ route('locations.index') }}" @class(['active' => request()->routeIs('locations.*', 'snapshots.*')])>Locations</a>
            <a href="{{ route('import.index') }}" @class(['active' => request()->routeIs('import.*')])>Import</a>
            <a href="{{ route('players.index') }}" @class(['active' => request()->routeIs('players.*')])>Players</a>
            <a href="{{ route('portfolio.planner') }}" @class(['active' => request()->routeIs('portfolio.planner')])>Planner</a>
            <a href="{{ route('projection.index') }}" @class(['active' => request()->routeIs('projection.*')])>Projection</a>
            <a href="{{ route('settings.edit') }}" @class(['active' => request()->routeIs('settings.*')])>Settings</a>
        </nav>
    </div>
</header>
<main>
    @if (session('status'))
        <div class="alert alert-ok">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-err">
            Please fix the following:
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @yield('content')
</main>
@stack('scripts')
</body>
</html>
