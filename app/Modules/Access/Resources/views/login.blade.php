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
    @include('partials.view-transition-guard')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
<div class="grid min-h-screen content-start lg:content-stretch lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)]">
    {{-- Brand panel in the dashboard's dark style --}}
    <aside class="login-hero" aria-label="About Pharmic LH">
        <span class="hero-glow" aria-hidden="true"></span>
        <span class="login-hero-glow" aria-hidden="true"></span>
        <div class="relative flex items-center gap-3">
            <span class="flex size-11 items-center justify-center rounded-xl bg-accent text-lg font-extrabold text-white" aria-hidden="true">P</span>
            <span class="flex flex-col leading-tight"><span class="text-[19px] font-extrabold tracking-[-0.02em]">Pharmic LH</span><span class="text-[12.5px] font-semibold text-[#9fb0d6]">Distribution portal</span></span>
        </div>

        <div class="relative flex flex-col gap-5 max-lg:hidden">
            <span class="hero-eyebrow self-start"><x-icon name="checklist" :size="14" /> Month end, in one place</span>
            <p class="m-0 max-w-[24ch] text-[38px] font-extrabold leading-[1.1] tracking-[-0.03em]">From distributor files to payslips.</p>
            <ul class="m-0 flex list-none flex-col gap-3.5 p-0">
                @foreach ([['upload', 'Upload each distributor’s file', 'Totals are checked before anything is posted.'], ['cash', 'Stock, invoices and ledger', 'Every confirmed sale flows through on its own.'], ['wallet', 'MR payroll', 'Incentives worked out from confirmed sales.']] as [$icon, $title, $text])
                    <li class="flex items-start gap-3">
                        <span class="login-feature-icon"><x-icon :name="$icon" /></span>
                        <span class="flex flex-col"><b class="font-bold">{{ $title }}</b><span class="hero-sub text-[13.5px]">{{ $text }}</span></span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="relative flex flex-col gap-2 max-lg:hidden" aria-hidden="true">
            <div class="close-track max-w-[340px]">
                <span class="close-seg" data-tone="ok"></span><span class="close-seg" data-tone="ok"></span><span class="close-seg" data-tone="ok"></span><span class="close-seg" data-tone="warn"></span><span class="close-seg"></span><span class="close-seg"></span>
            </div>
            <span class="hero-sub text-[12px]">Only confirmed files count.</span>
        </div>
    </aside>

    <main class="flex items-center justify-center px-4 py-10 sm:px-8">
        <div class="card flex w-full max-w-[420px] flex-col gap-5 p-7 sm:p-8">
            <div class="flex flex-col gap-1">
                <h1 class="m-0 text-[26px] font-extrabold tracking-[-0.025em]">Welcome back</h1>
                <p class="m-0 text-[14px] text-muted">Sign in to continue to the portal.</p>
            </div>

            {{-- Email and password errors sit under their fields; anything else still gets the summary box. --}}
            <x-flash :summary="collect($errors->keys())->diff(['email', 'password'])->isNotEmpty()" />

            <form method="post" action="{{ url('login') }}" class="flex flex-col gap-4">
                @csrf
                <div class="flex flex-col gap-1.5">
                    <label for="email" class="label">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required
                           @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                           @if (! $errors->hasAny(['email', 'password']) || $errors->has('email')) autofocus @endif
                           class="field">
                    @error('email')<p id="email-error" class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="password" class="label">Password</label>
                    <div class="relative">
                        <input id="password" name="password" type="password" autocomplete="current-password" required
                               @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                               @if (! $errors->has('email') && $errors->has('password')) autofocus @endif
                               class="field pr-12">
                        <button type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false"
                                class="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-r-xl text-muted hover:text-ink">
                            <svg data-eye="show" aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg data-eye="hide" class="hidden" aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.6 5.1A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-2.4 3.4M6.6 6.6A17.4 17.4 0 0 0 2 12s3.5 7 10 7a10 10 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/><path d="m2 2 20 20"/></svg>
                        </button>
                    </div>
                    @error('password')<p id="password-error" class="field-error">{{ $message }}</p>@enderror
                </div>
                <label class="flex min-h-11 items-center gap-2 text-[13px]">
                    <input type="checkbox" name="remember" value="1" class="size-4 accent-[var(--sky-accent)]"> Keep me signed in on this device
                </label>
                <button class="btn btn-primary" data-busy-label="Signing in…">Sign in</button>
            </form>
        </div>
    </main>
</div>
</body>
</html>
