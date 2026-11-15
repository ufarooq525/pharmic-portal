@props(['id', 'title', 'icon', 'tone' => 'c1'])
{{-- Card heading: tinted icon, title, and anything on the right (a note, a count, a link). --}}
<div class="card-head">
    <span class="card-icon" style="--tone: var(--sky-{{ $tone }})"><x-icon :name="$icon" /></span>
    <h2 id="{{ $id }}" class="card-title min-w-0 flex-1">{{ $title }}</h2>
    @if ($slot->isNotEmpty())
        <div class="flex flex-none items-center gap-2 text-[13px] text-muted">{{ $slot }}</div>
    @endif
</div>
