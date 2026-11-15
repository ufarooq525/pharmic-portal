@props(['current'])
@php
    $q = request()->only('period');
    $tabs = \App\Modules\Access\Support\Navigation::masterTabs(auth()->user());
@endphp
<x-page-header title="Master data" sub="The setup every import depends on." />
@if (count($tabs) > 1)
    <nav class="tabs" aria-label="Master data">
        @foreach ($tabs as [$key, $route, $label])
            <a href="{{ route($route, $q) }}" class="tab" @if ($key === $current) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
@endif
