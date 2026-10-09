<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · Narciana TRMS</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-64.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <script>
        (function() {
            try {
                var mode = localStorage.getItem('narciana_nav_mode');
                if (mode && ['rail', 'expanded', 'collapsed'].indexOf(mode) !== -1) {
                    document.documentElement.classList.add('side-' + mode);
                }
            } catch (e) {}
            document.documentElement.classList.add('preload-nav');
        })();
    </script>
    @stack('head')
</head>
<body class="preload-nav">
<script>
    (function() {
        try {
            var mode = localStorage.getItem('narciana_nav_mode');
            if (mode && ['rail', 'expanded', 'collapsed'].indexOf(mode) !== -1) {
                document.body.classList.add('side-' + mode);
            }
        } catch (e) {}
    })();
</script>
@php($me = auth()->user())
<div class="app">
    <aside class="side" id="side">
        <div class="brand">
            <img src="{{ asset('images/logo-64.png') }}" alt="Narciana TRMS">
            <div class="brand-text">
                <b>Narciana House</b>
                <span>Tenant and Rental Management</span>
            </div>
        </div>
        <nav class="nav">
            @foreach (config('narciana.nav') as $group => $items)
                @php($visibleItems = collect($items)->filter(function ($item) use ($me) {
                    if (str_starts_with($item[0], 'leases.') && !\Illuminate\Support\Facades\Gate::allows('manage-leases')) {
                        return false;
                    }
                    if ((str_starts_with($item[0], 'bills.') || str_starts_with($item[0], 'payments.') || str_starts_with($item[0], 'meter-readings.')) && !\Illuminate\Support\Facades\Gate::allows('manage-billing')) {
                        return false;
                    }
                    return $me?->hasRole(...$item[2]);
                }))
                @continue($visibleItems->isEmpty())

                @if ($group)
                    <div class="nav-heading">{{ $group }}</div>
                @endif

                @foreach ($visibleItems as $item)
                    @php([$routeName, $label, $roles, $icon] = $item)
                    @if (str_starts_with($routeName, 'leases.'))
                        @can('manage-leases')
                            <a href="{{ route($routeName) }}"
                               class="{{ request()->routeIs(Str::before($routeName, '.').'*', ...($item[4] ?? [])) ? 'on' : '' }}"
                               title="{{ $label }}"
                               data-tooltip="{{ $label }}">
                                <x-icon :name="$icon" />{{ $label }}
                            </a>
                        @endcan
                    @else
                        <a href="{{ route($routeName) }}"
                           class="{{ request()->routeIs(Str::before($routeName, '.').'*', ...($item[4] ?? [])) ? 'on' : '' }}"
                           title="{{ $label }}"
                           data-tooltip="{{ $label }}">
                            <x-icon :name="$icon" />{{ $label }}
                        </a>
                    @endif
                @endforeach
            @endforeach
        </nav>
        <div class="side-footer">
            <button type="button" class="side-rail-toggle-btn" id="side-rail-toggle-btn"
                    aria-label="Toggle navigation width" title="Toggle navigation rail"
                    onclick="toggleSidebarWidth()">
                <x-icon name="panel-left" size="18" />
                <span class="side-toggle-label">Toggle rail</span>
            </button>
        </div>
    </aside>
    <div class="scrim" onclick="document.body.classList.remove('nav-open')"></div>

    <div class="main">
        <header class="top">
            <div class="top-left">
                <button type="button" class="menu-btn" aria-label="Open menu"
                        onclick="document.body.classList.toggle('nav-open')">&#9776;</button>
                <button type="button" class="rail-toggle-btn" id="header-rail-toggle-btn"
                        aria-label="Toggle navigation width" title="Toggle navigation rail (Rail / Full / Collapsed)"
                        onclick="toggleSidebarWidth()">
                    <x-icon name="panel-left" size="18" />
                </button>
                <h1>@yield('title', 'Dashboard')</h1>
            </div>
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
            @if (session('error'))
                <div class="alert alert-err">{{ session('error') }}</div>
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
<script src="{{ asset('js/sidebar.js') }}?v={{ filemtime(public_path('js/sidebar.js')) }}"></script>
@stack('scripts')
</body>
</html>
