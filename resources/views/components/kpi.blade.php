@props(['label', 'value', 'note' => null, 'delta' => null])
{{-- $delta: percentage change against the previous month, or null when there is nothing to compare. --}}
<div class="kpi">
    <span class="kpi-label">{{ $label }}</span>
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
