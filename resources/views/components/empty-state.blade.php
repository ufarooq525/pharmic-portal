@props(['icon' => 'chart', 'title', 'action' => null, 'href' => null])
{{-- Empty card body: says why it is empty and, when there is one, what to do about it. --}}
<div {{ $attributes->class(['empty-state']) }}>
    <span class="empty-state-icon"><x-icon :name="$icon" :size="22" /></span>
    <p class="m-0 font-bold text-ink">{{ $title }}</p>
    @if ($slot->isNotEmpty())<p class="m-0 max-w-[42ch] text-[13px] text-muted">{{ $slot }}</p>@endif
    @if ($action && $href)<a href="{{ $href }}" class="btn btn-sm mt-1">{{ $action }}</a>@endif
</div>
