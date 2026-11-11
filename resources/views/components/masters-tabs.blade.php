@props(['current'])
@php
    $q = request()->only('period');
    $tabs = [
        'products' => ['products.index', 'Products & taxes'],
        'distributors' => ['distributors.index', 'Distributors & formats'],
        'reps' => ['reps.index', 'Medical reps'],
        'aliases' => ['aliases.index', 'Name matching'],
    ];
@endphp
<x-page-header title="Master data" sub="The setup every import depends on." />
<nav class="tabs" aria-label="Master data">
    @foreach ($tabs as $key => [$route, $label])
        <a href="{{ route($route, $q) }}" class="tab" @if ($key === $current) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
