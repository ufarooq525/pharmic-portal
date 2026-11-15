@props(['page', 'current'])
{{-- Tabs of a page with sections (Inventory: Stock / Products & prices / Stock receipts; Distributors: Profiles / Name matching).
     Only the tabs this person can open; hidden when there is just one. --}}
@php
    $q = request()->only('period');
    $tabs = \App\Modules\Access\Support\Navigation::tabs($page, auth()->user());
@endphp
@if (count($tabs) > 1)
    <nav class="tabs" aria-label="{{ ucfirst($page) }} sections">
        @foreach ($tabs as [$key, $route, $label])
            <a href="{{ route($route, $q) }}" class="tab" @if ($key === $current) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
@endif
