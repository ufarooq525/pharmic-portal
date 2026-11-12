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
            document.documentElement.classList.add('js');
            try { if (localStorage.getItem('pharmic-sidebar') === 'collapsed') document.documentElement.classList.add('sb-collapsed'); } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div id="page-loader" class="page-loader" role="progressbar" aria-label="Loading" aria-hidden="true"></div>
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-surface focus:px-4 focus:py-2">Skip to content</a>

@php
    // Sidebar icons, by route name (24px stroke icons).
    $navIcons = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'imports.index' => '<path d="M12 15V3"/><path d="m7 8 5-5 5 5"/><path d="M4 15v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4"/>',
        'customers.index' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.4 3.4-5.5 6.5-5.5s5.7 2.1 6.5 5.5"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18.5 14.8c1.6.8 2.7 2.6 3 5.2"/>',
        'inventory.index' => '<path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/><path d="m3 8 9 5 9-5"/><path d="M12 13v8"/>',
        'reports.show' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'invoices.index' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
        'ledger.index' => '<path d="M4 4h12a4 4 0 0 1 4 4v12H8a4 4 0 0 1-4-4V4Z"/><path d="M8 9h8M8 13h8M8 17h5"/>',
        'payroll.index' => '<path d="M3 7a2 2 0 0 1 2-2h13v4"/><path d="M3 7v11a2 2 0 0 0 2 2h15V9H5a2 2 0 0 1-2-2Z"/><circle cx="16" cy="14.5" r="1.2"/>',
        'products.index' => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
        'users.index' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4.2-6 8-6s7 2 8 6"/><path d="m17.5 3.5 1.5 1.5 3-3"/>',
        'activity.index' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'my.index' => '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/>',
    ];
    $user = auth()->user();
@endphp

<div class="flex min-h-screen">
    {{-- Sidebar: fixed on wide screens, slides in from the left on phones and tablets --}}
    <aside id="sidebar" class="sidebar" aria-label="Main">
        <div class="sb-head flex items-center justify-between gap-1 px-2 pb-2 pt-1">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 text-ink no-underline">
                <span class="flex size-9 items-center justify-center rounded-xl bg-accent font-extrabold text-white" aria-hidden="true">P</span>
                <span class="sb-label flex flex-col leading-tight"><span class="text-[17px] font-extrabold tracking-[-0.02em]">Pharmic LH</span><span class="text-[11.5px] font-semibold text-muted">Distribution portal</span></span>
            </a>
            <button type="button" class="icon-btn sb-toggle hidden lg:inline-flex" data-sidebar-collapse aria-pressed="false" aria-label="Collapse menu" title="Collapse menu">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2.5"/><path d="M9 4v16"/><path class="sb-chevron" d="m15.5 10-2 2 2 2"/></svg>
            </button>
            <button type="button" class="icon-btn lg:hidden" data-sidebar-close aria-label="Close menu">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>

        <a href="#period" class="side-link sb-only" title="Month end: {{ $period->format('F Y') }}" data-expand-to-period>
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2.5"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
            <span class="sr-only">Month end {{ $period->format('F Y') }}</span>
        </a>
        <form method="get" action="{{ url()->current() }}" class="sb-label mx-1 flex items-center gap-1.5 rounded-[14px] bg-soft py-1 pl-3 pr-1.5 text-[13px] font-bold text-softfg">
            @foreach (request()->except(['period', 'page']) as $key => $value)
                @if (is_string($value))
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            <label for="period" class="whitespace-nowrap">Month end</label>
            <input id="period" name="period" type="month" value="{{ $period->format('Y-m') }}" class="min-h-9 w-full min-w-0 cursor-pointer rounded-[10px] border-0 bg-transparent px-1.5 font-bold text-softfg" data-autosubmit>
            <noscript><button class="btn btn-sm">Go</button></noscript>
        </form>

        <nav aria-label="Pages" class="flex flex-1 flex-col gap-0.5 overflow-y-auto py-2 [scrollbar-width:thin]">
            @foreach ($navItems as $item)
                <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif class="side-link" title="{{ $item['label'] }}">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $navIcons[$item['route']] ?? '<circle cx="12" cy="12" r="3"/>' !!}</svg>
                    <span class="sb-label flex-1">{{ $item['label'] }}</span>
                    @if ($item['badge'])
                        <span class="side-badge rounded-[10px] bg-warn-soft px-[7px] text-[11px] font-extrabold text-warn">{{ $item['badge'] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="flex flex-col gap-2 border-t border-line px-1 pt-3">
            <button type="button" class="side-link w-full text-left" data-theme-toggle aria-pressed="false" title="Light / dark mode">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
                <span class="sb-label" data-theme-label>Dark mode</span>
            </button>
            <div class="sb-user flex items-center gap-2.5 rounded-[14px] bg-sunk px-3 py-2.5" title="{{ $user->name }} · {{ $user->role->label() }}">
                <span class="flex size-9 flex-none items-center justify-center rounded-full bg-seg text-[13px] font-extrabold text-nav" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                <span class="sb-label flex min-w-0 flex-1 flex-col leading-tight"><b class="truncate text-[13.5px]">{{ $user->name }}</b><span class="text-[12px] text-muted">{{ $user->role->label() }}</span></span>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button class="icon-btn" aria-label="Log out" title="Log out">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="m10 17-5-5 5-5"/><path d="M5 12h12"/></svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>
    <div class="sidebar-backdrop" data-sidebar-close aria-hidden="true"></div>

    <div class="flex min-w-0 flex-1 flex-col">
        {{-- Phone/tablet top bar --}}
        <header class="sticky top-0 z-30 flex items-center gap-3 border-b border-line bg-surface/90 px-4 py-2.5 backdrop-blur lg:hidden">
            <button type="button" class="icon-btn" data-sidebar-open aria-controls="sidebar" aria-expanded="false" aria-label="Open menu">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <span class="font-extrabold">Pharmic LH</span>
            <span class="ml-auto rounded-full bg-soft px-3 py-1 text-[12px] font-bold text-softfg">{{ $period->format('M Y') }}</span>
        </header>

        <main id="main" class="mx-auto flex w-full max-w-[1320px] flex-col gap-5 px-4 pb-14 pt-6 sm:px-7 lg:pt-8">
            <x-flash />
            {{ $slot }}
        </main>
    </div>
</div>

{{-- Drawer: add and edit forms open here, from the right, without leaving the page (like Upwork) --}}
<div id="drawer" class="drawer" hidden>
    <div class="drawer-backdrop" data-drawer-close></div>
    <section class="drawer-panel" role="dialog" aria-modal="true" aria-labelledby="drawer-title">
        <header class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
            <h2 id="drawer-title" class="m-0 text-[19px] font-extrabold tracking-[-0.01em]"></h2>
            <button type="button" class="icon-btn" data-drawer-close aria-label="Close">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </header>
        <div id="drawer-body" class="flex flex-1 flex-col gap-4 overflow-y-auto p-5"></div>
    </section>
</div>
<div id="toast" class="toast" role="status" aria-live="polite" hidden></div>
</body>
</html>
