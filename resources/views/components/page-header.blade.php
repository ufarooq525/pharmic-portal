@props(['title', 'sub' => null])
<div class="flex flex-wrap items-end justify-between gap-3">
    <div class="flex max-w-[72ch] flex-col gap-1">
        <h1 class="text-[28px] font-extrabold tracking-[-0.025em] sm:text-[30px]">{{ $title }}</h1>
        @if ($sub)
            <p class="text-muted">{{ $sub }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="flex flex-wrap items-end gap-2">{{ $slot }}</div>
    @endif
</div>
