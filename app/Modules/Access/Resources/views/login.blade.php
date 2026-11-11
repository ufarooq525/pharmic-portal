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
            <input id="password" name="password" type="password" autocomplete="current-password" required class="field">
        </div>
        <label class="flex items-center gap-2 text-[13px]">
            <input type="checkbox" name="remember" value="1" class="size-4 accent-[var(--app-accent)]"> Keep me signed in on this device
        </label>
        <button class="btn btn-primary">Sign in</button>
    </form>
</main>
</body>
</html>
