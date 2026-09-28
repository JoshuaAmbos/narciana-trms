<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · Narciana TRMS</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-64.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @stack('head')
</head>
<body>
<div class="app">
    <aside class="side" id="side">
        <div class="brand">
            <img src="{{ asset('images/logo-64.png') }}" alt="">
            <div>
                <b>Narciana House</b>
                <span>Tenant and Rental Management</span>
            </div>
        </div>
        <nav class="nav">
            @foreach (config('narciana.nav') as [$routeName, $label, $roles])
                <a href="{{ Route::has($routeName) ? route($routeName) : '#' }}"
                   class="{{ request()->routeIs(Str::before($routeName, '.').'*') ? 'on' : '' }}">
                    <span class="ico"></span>{{ $label }}
                </a>
            @endforeach
        </nav>
    </aside>
    <div class="scrim" onclick="document.body.classList.remove('nav-open')"></div>

    <div class="main">
        <header class="top">
            <button type="button" class="menu-btn" aria-label="Open menu"
                    onclick="document.body.classList.toggle('nav-open')">&#9776;</button>
            <h1>@yield('title', 'Dashboard')</h1>
            <div class="who">
                @auth
                    Logged in as <b>{{ auth()->user()->role }}</b>
                @else
                    <b>Not logged in</b>
                @endauth
                <span class="when">&nbsp;|&nbsp; {{ now()->format('l, j F Y') }}</span>
            </div>
        </header>

        <main class="body">
            @if (session('status'))
                <div class="alert alert-ok">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-err">Please check the highlighted fields.</div>
            @endif

            @yield('content')
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
