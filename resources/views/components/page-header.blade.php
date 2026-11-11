@props(['title', 'sub' => null])
<header class="flex flex-wrap items-end justify-between gap-3">
    <div class="flex max-w-[72ch] flex-col gap-1">
        <h1 class="page-title">{{ $title }}</h1>
        @if ($sub)
            <p class="m-0 text-muted">{{ $sub }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="flex flex-wrap items-end gap-2">{{ $slot }}</div>
    @endif
</header>
