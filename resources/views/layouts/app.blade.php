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
@php($me = auth()->user())
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
            @foreach (config('narciana.nav') as [$routeName, $label, $roles, $icon])
                @continue(! $me?->hasRole(...$roles))
                <a href="{{ Route::has($routeName) ? route($routeName) : '#' }}"
                   class="{{ request()->routeIs(Str::before($routeName, '.').'*') ? 'on' : '' }}">
                    <x-icon :name="$icon" />{{ $label }}
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
                @if ($me)
                    <span class="who-text">
                        Logged in as <b>{{ $me->role }}</b>
                        <span class="when">&nbsp;|&nbsp; {{ now()->format('l, j F Y') }}</span>
                    </span>
                    <a href="{{ route('password.edit') }}" class="top-btn" title="My password">
                        <x-icon name="key-round" size="18" /><span class="top-btn-label">My password</span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="top-btn" title="Log out">
                            <x-icon name="log-out" size="18" /><span class="top-btn-label">Log out</span>
                        </button>
                    </form>
                @endif
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

@if ($me)
    {{-- Inactivity logout: the server ends the session after SESSION_LIFETIME minutes without a request.
         This timer then takes a tablet left open back to the login page so no tenant data stays on screen. --}}
    <script>
        setTimeout(function () {
            window.location.href = @json(route('login', ['timeout' => 1]));
        }, {{ (int) config('session.lifetime') }} * 60 * 1000 + 5000);
    </script>
@endif
@stack('scripts')
</body>
</html>
