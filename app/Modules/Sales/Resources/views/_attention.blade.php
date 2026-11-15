<li class="attn">
    <span class="attn-icon bg-{{ $a['tone'] }}-soft text-{{ $a['tone'] }}"><x-icon :name="$icon[$a['tone']] ?? 'alert'" /></span>
    <span class="flex min-w-0 flex-1 flex-col">
        <b class="truncate">{{ $a['what'] }}</b>
        <span class="text-[13px] text-muted">{{ $a['detail'] }}</span>
    </span>
    <a href="{{ $a['url'] }}" class="btn btn-sm flex-none">{{ $a['cta'] }}</a>
</li>
