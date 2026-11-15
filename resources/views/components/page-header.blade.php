@props(['title', 'sub' => null])
{{-- Page banner in the dashboard's style: the section and page icon come from the route; buttons in the slot restyle for the dark background. --}}
@php
    $route = (string) request()->route()?->getName();
    [$section, $icon] = match (true) {
        str_starts_with($route, 'imports.show'), str_starts_with($route, 'imports.review') => ['Month end', 'file-search'],
        str_starts_with($route, 'imports.') => ['Month end', 'upload'],
        str_starts_with($route, 'customers.') => ['Month end', 'people'],
        str_starts_with($route, 'inventory.'), str_starts_with($route, 'products.') => ['Stock & money', 'box'],
        str_starts_with($route, 'distributors.'), str_starts_with($route, 'aliases.') => ['Setup', 'truck'],
        str_starts_with($route, 'reps.') => ['Setup', 'people'],
        str_starts_with($route, 'reports.') => ['Stock & money', 'chart'],
        str_starts_with($route, 'invoices.') => ['Stock & money', 'receipt'],
        str_starts_with($route, 'ledger.') => ['Stock & money', 'book'],
        str_starts_with($route, 'payroll.') => ['Stock & money', 'wallet'],
        str_starts_with($route, 'users.') => ['Setup', 'user-check'],
        str_starts_with($route, 'activity.') => ['Setup', 'clock'],
        str_starts_with($route, 'account.') => ['Your account', 'key'],
        str_starts_with($route, 'my.') => ['My territory', 'pin'],
        default => ['Setup', 'database'],
    };
@endphp
<header class="hero page-hero">
    <span class="hero-glow" aria-hidden="true"></span>
    <div class="relative flex flex-wrap items-center justify-between gap-4 max-sm:flex-col max-sm:items-start">
        <div class="flex min-w-0 max-w-[80ch] flex-1 items-start gap-4">
            <span class="page-hero-icon"><x-icon :name="$icon" :size="22" /></span>
            <div class="flex min-w-0 flex-col gap-1">
                <span class="text-[12px] font-bold uppercase tracking-[0.06em] text-[#9fb0d6]">{{ $section }}</span>
                <h1 class="m-0 text-[24px] font-extrabold tracking-[-0.025em] sm:text-[28px]">{{ $title }}</h1>
                @if ($sub)
                    <p class="hero-sub m-0 text-[14px]">{{ $sub }}</p>
                @endif
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            {{ $slot }}
            <x-refresh-button />
        </div>
    </div>
</header>
