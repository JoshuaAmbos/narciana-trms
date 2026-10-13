<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in · Narciana TRMS</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-64.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
<div class="login">
    <form method="POST" action="{{ route('login.attempt') }}" class="lbox" novalidate>
        @csrf
        <img src="{{ asset('images/logo-192.png') }}" alt="" class="login-logo">
        <div class="login-title">Narciana House Space Rental</div>
        <div class="muted center">Tenant and Rental Management System</div>

        @if ($timedOut)
            <div class="alert alert-warn">You were logged out after {{ $minutes }} minutes of inactivity. Please log in again.</div>
        @endif
        @if (session('status'))
            <div class="alert alert-ok">{{ session('status') }}</div>
        @endif

        <div class="f @error('username') has-err @enderror">
            <label for="username">Username <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
            <input id="username" name="username" type="text" value="{{ old('username') }}"
                   autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" required autofocus>
            @error('username') <div class="err">{{ $message }}</div> @enderror
        </div>

        <div class="f @error('password') has-err @enderror">
            <label for="password">Password <span class="req-star" style="color: #dc2626; font-weight: 700; margin-left: 2px;" aria-hidden="true">*</span></label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            @error('password') <div class="err">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="btn block">Log in</button>
    </form>
</div>
</body>
</html>
