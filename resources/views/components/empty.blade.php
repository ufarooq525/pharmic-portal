{{-- Empty list: a quiet dashed box with an icon, as on the dashboard. --}}
<div {{ $attributes->class(['flex items-center gap-3 rounded-2xl border border-dashed border-line px-4 py-4 text-muted']) }}>
    <span class="flex size-9 flex-none items-center justify-center rounded-full bg-sunk"><x-icon name="file" /></span>
    <span class="min-w-0">{{ $slot }}</span>
</div>
