@props(['title', 'description' => 'Pharmic LH distribution portal: distributor imports, stock, sales, taxes and reports.'])
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · Pharmic LH</title>
    <meta name="description" content="{{ $description }}">
    {{-- Private portal: never indexed by search or AI answer engines (also sent as an X-Robots-Tag header). --}}
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#fefefe" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#171e2e" media="(prefers-color-scheme: dark)">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <script>
        {{-- Set the theme before first paint so dark mode never flashes white. --}}
        (function () {
            var t = null;
            try { t = localStorage.getItem('pharmic-theme'); } catch (e) {}
            if (!t) t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.dataset.theme = t;
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-surface focus:px-4 focus:py-2">Skip to content</a>

<header class="border-b border-line bg-surface">
    <div class="mx-auto flex max-w-[1320px] flex-col gap-3 px-4 pt-3.5 sm:px-7">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 text-ink no-underline">
                <span class="flex size-9 items-center justify-center rounded-xl bg-accent font-extrabold text-white" aria-hidden="true">P</span>
                <span class="text-[18px] font-extrabold tracking-[-0.02em]">Pharmic LH</span>
            </a>

            <div class="flex flex-wrap items-center gap-2.5">
                <form method="get" action="{{ url()->current() }}" class="flex items-center gap-1.5 rounded-full bg-soft py-1 pl-3 pr-1.5 text-[13px] font-bold text-softfg">
                    @foreach (request()->except(['period', 'page']) as $key => $value)
                        @if (is_string($value))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <label for="period">Month end ·</label>
                    <input id="period" name="period" type="month" value="{{ $period->format('Y-m') }}" class="min-h-9 cursor-pointer rounded-full border-0 bg-transparent px-1.5 font-bold text-softfg" data-autosubmit>
                    <noscript><button class="btn btn-sm">Go</button></noscript>
                </form>

                <button type="button" class="min-h-11 rounded-full border border-line2 bg-surface px-3.5 py-1.5 text-[13px] font-bold text-ink" data-theme-toggle aria-pressed="false">
                    <span data-theme-label>Dark mode</span>
                </button>

                <span class="text-[13px] text-muted">{{ auth()->user()->role->label() }}</span>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button class="min-h-11 rounded-full border border-line2 bg-surface px-3.5 py-1.5 text-[13px] font-bold text-ink">Log out</button>
                </form>
            </div>
        </div>

        <nav aria-label="Pages" class="-mb-px flex flex-wrap gap-0.5">
            @foreach ($navItems as $item)
                <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif
                   class="flex min-h-11 items-center gap-1.5 border-b-[3px] px-3 pb-[9px] pt-2.5 no-underline {{ $item['active'] ? 'border-accent-text font-extrabold text-accent-text' : 'border-transparent font-semibold text-nav hover:text-ink' }}">
                    {{ $item['label'] }}
                    @if ($item['badge'])
                        <span class="rounded-[10px] bg-[#ffe3cc] px-[7px] text-[11px] font-extrabold text-[#8a3a07]">{{ $item['badge'] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>
    </div>
</header>

<main id="main" class="mx-auto flex max-w-[1320px] flex-col gap-5 px-4 pb-14 pt-7 sm:px-7">
    <x-flash />
    {{ $slot }}
</main>
</body>
</html>
