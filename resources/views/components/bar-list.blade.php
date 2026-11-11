@props(['items', 'empty' => 'No confirmed sales for this month yet.'])
{{-- $items: list of ['label' => string, 'value' => float, 'display' => string, 'sub' => ?string] --}}
@php($max = max(1, ...array_map(fn ($i) => (float) $i['value'], $items ?: [['value' => 1]])))
@if (count($items))
    <ul class="flex flex-col gap-3">
        @foreach ($items as $item)
            <li class="grid grid-cols-[minmax(0,10rem)_minmax(0,1fr)_auto] items-center gap-3 sm:grid-cols-[minmax(0,13rem)_minmax(0,1fr)_7rem]">
                <span class="min-w-0">
                    <span class="block truncate font-semibold">{{ $item['label'] }}</span>
                    @isset($item['sub'])
                        <span class="block truncate text-[12px] text-muted">{{ $item['sub'] }}</span>
                    @endisset
                </span>
                <span class="bar-track" aria-hidden="true"><span class="bar-fill" style="width: {{ max(2, round((float) $item['value'] / $max * 100)) }}%"></span></span>
                <span class="num text-right">{{ $item['display'] }}</span>
            </li>
        @endforeach
    </ul>
@else
    <x-empty>{{ $empty }}</x-empty>
@endif
