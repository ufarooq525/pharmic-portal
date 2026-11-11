@props(['label', 'value', 'note' => null, 'delta' => null])
{{-- $delta: percentage change against the previous month, or null when there is nothing to compare. --}}
<div class="card flex flex-col gap-1.5 p-[18px]">
    <span class="text-[13px] font-semibold text-muted">{{ $label }}</span>
    <span class="num text-[24px] font-extrabold tracking-[-0.02em]">{{ $value }}</span>
    <span class="flex flex-wrap items-center gap-2 text-[12px] text-muted">
        @if ($delta !== null)
            <span class="chip {{ $delta >= 0 ? 'chip-ok' : 'chip-bad' }}">
                <span aria-hidden="true">{{ $delta >= 0 ? '▲' : '▼' }}</span>
                <span class="sr-only">{{ $delta >= 0 ? 'Up' : 'Down' }}</span>
                {{ fmt_pct(abs($delta)) }}
            </span>
            <span>vs last month</span>
        @endif
        @if ($note)
            <span>{{ $note }}</span>
        @endif
    </span>
</div>
