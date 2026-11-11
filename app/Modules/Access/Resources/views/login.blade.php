<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · Pharmic LH</title>
    <meta name="description" content="Sign in to the Pharmic LH distribution portal.">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="color-scheme" content="light dark">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <script>
        (function () {
            var t = null;
            try { t = localStorage.getItem('pharmic-theme'); } catch (e) {}
            if (!t) t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.dataset.theme = t;
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center px-4 py-10">
<main class="card flex w-full max-w-[420px] flex-col gap-5 p-7">
    <div class="flex items-center gap-2.5">
        <span class="flex size-10 items-center justify-center rounded-xl bg-accent text-lg font-extrabold text-white" aria-hidden="true">P</span>
        <div>
            <h1 class="text-xl font-extrabold tracking-tight">Pharmic LH</h1>
            <p class="text-[13px] text-muted">Distribution portal</p>
        </div>
    </div>

    <x-flash />

    <form method="post" action="{{ url('login') }}" class="flex flex-col gap-4">
        @csrf
        <div class="flex flex-col gap-1.5">
            <label for="email" class="label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus class="field">
        </div>
        <div class="flex flex-col gap-1.5">
            <label for="password" class="label">Password</label>
            <div class="relative">
                <input id="password" name="password" type="password" autocomplete="current-password" required class="field pr-12">
                <button type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false"
                        class="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-r-xl text-muted hover:text-ink">
                    <svg data-eye="show" aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg data-eye="hide" class="hidden" aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.6 5.1A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-2.4 3.4M6.6 6.6A17.4 17.4 0 0 0 2 12s3.5 7 10 7a10 10 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/><path d="m2 2 20 20"/></svg>
                </button>
            </div>
        </div>
        <label class="flex items-center gap-2 text-[13px]">
            <input type="checkbox" name="remember" value="1" class="size-4 accent-[var(--sky-accent)]"> Keep me signed in on this device
        </label>
        <button class="btn btn-primary" data-busy-label="Signing in…">Sign in</button>
    </form>
</main>
</body>
</html>
