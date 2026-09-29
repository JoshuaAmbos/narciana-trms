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
            @foreach (config('narciana.nav') as $item)
                @php([$routeName, $label, $roles, $icon] = $item)
                @continue(! $me?->hasRole(...$roles))
                <a href="{{ Route::has($routeName) ? route($routeName) : '#' }}"
                   class="{{ request()->routeIs(Str::before($routeName, '.').'*', ...($item[4] ?? [])) ? 'on' : '' }}">
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
            @if (session('warning'))
                <div class="alert alert-warn">{{ session('warning') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-err">Please check the highlighted fields.</div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

@if ($me)
    {{-- Inactivity logout: warning 1 minute before, then back to the login page (public/js/idle-timer.js). --}}
    <div id="idle-warning" class="idle-warning" role="alertdialog" aria-live="assertive"
         aria-labelledby="idle-title" hidden>
        <div class="idle-box">
            <div id="idle-title" class="idle-title">Are you still there?</div>
            <p>For security, you will be logged out in <b id="idle-seconds">60</b> seconds because of inactivity.</p>
            <button type="button" id="idle-stay" class="btn block">Stay logged in</button>
        </div>
    </div>
    <script src="{{ asset('js/idle-timer.js') }}?v={{ filemtime(public_path('js/idle-timer.js')) }}"
            data-lifetime="{{ (int) config('session.lifetime') }}"
            data-keep-alive-url="{{ route('keep-alive') }}"
            data-login-url="{{ route('login', ['timeout' => 1]) }}"></script>
@endif
@stack('scripts')
</body>
</html>
