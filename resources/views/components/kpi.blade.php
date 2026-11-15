@props(['label', 'value', 'note' => null, 'delta' => null, 'icon' => null, 'tone' => 'c1'])
{{-- $delta: percentage change against the previous month, or null when there is nothing to compare. --}}
<div {{ $attributes->class(['kpi']) }} style="--tone: var(--sky-{{ $tone }})">
    <span class="flex items-center justify-between gap-2">
        <span class="kpi-label">{{ $label }}</span>
        @if ($icon)
            <span class="kpi-icon"><x-icon :name="$icon" /></span>
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
