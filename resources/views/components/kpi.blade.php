@props(['label', 'value', 'note' => null, 'delta' => null, 'icon' => null])
{{-- $delta: percentage change against the previous month, or null when there is nothing to compare. --}}
@php
    $icons = [
        'box' => '<path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/><path d="m3 8 9 5 9-5"/><path d="M12 13v8"/>',
        'trend' => '<path d="m3 17 6-6 4 4 8-8"/><path d="M14 7h7v7"/>',
        'profit' => '<circle cx="12" cy="12" r="9"/><path d="M15 9.5c-.6-1-1.7-1.5-3-1.5-1.7 0-3 .9-3 2.2 0 3 6 1.6 6 4.6 0 1.3-1.3 2.2-3 2.2-1.4 0-2.5-.6-3-1.6M12 6.5V8m0 8v1.5"/>',
        'receipt' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
        'wallet' => '<path d="M3 7a2 2 0 0 1 2-2h13v4"/><path d="M3 7v11a2 2 0 0 0 2 2h15V9H5a2 2 0 0 1-2-2Z"/><circle cx="16" cy="14.5" r="1.2"/>',
        'people' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.4 3.4-5.5 6.5-5.5s5.7 2.1 6.5 5.5"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18.5 14.8c1.6.8 2.7 2.6 3 5.2"/>',
        'gift' => '<rect x="3" y="8" width="18" height="5" rx="1"/><path d="M5 13v8h14v-8M12 8v13M12 8c-1.5-3-5-3.5-5-1.2C7 8 12 8 12 8Zm0 0c1.5-3 5-3.5 5-1.2C17 8 12 8 12 8Z"/>',
    ];
@endphp
<div {{ $attributes->class(['kpi']) }}>
    <span class="flex items-center justify-between gap-2">
        <span class="kpi-label">{{ $label }}</span>
        @if ($icon && isset($icons[$icon]))
            <span class="stat-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$icon] !!}</svg></span>
        @endif
    </span>
    <span class="kpi-value">{{ $value }}</span>
    <span class="flex flex-wrap items-center gap-1.5">
        @if ($delta !== null)
            <span class="chip {{ $delta >= 0 ? 'chip-ok' : 'chip-bad' }}">
                <span aria-hidden="true">{{ $delta >= 0 ? '▲' : '▼' }}</span>
                <span class="sr-only">{{ $delta >= 0 ? 'Up' : 'Down' }}</span>
                {{ fmt_pct(abs($delta)) }} vs last month
            </span>
        @endif
        @if ($note)
            <span class="kpi-note">{{ $note }}</span>
        @endif
    </span>
</div>
