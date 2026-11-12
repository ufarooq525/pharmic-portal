{{-- Skyline pagination, used for every paginated list (set in AppServiceProvider). --}}
@if ($paginator->hasPages())
    <nav class="flex flex-wrap items-center justify-between gap-3 pt-1" aria-label="Pages">
        <span class="text-[13px] text-muted">
            Showing <b class="num text-ink">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</b> of <b class="num text-ink">{{ $paginator->total() }}</b>
        </span>
        <ul class="m-0 flex list-none flex-wrap items-center gap-1 p-0">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="btn btn-sm pointer-events-none opacity-45" aria-disabled="true">‹ Previous</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn btn-sm">‹ Previous</a>
                @endif
            </li>
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="px-2 text-muted">…</span></li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="btn btn-sm min-w-9 border-accent bg-accent text-white">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="btn btn-sm min-w-9" aria-label="Page {{ $page }}">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach
            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn btn-sm">Next ›</a>
                @else
                    <span class="btn btn-sm pointer-events-none opacity-45" aria-disabled="true">Next ›</span>
                @endif
            </li>
        </ul>
    </nav>
@endif
