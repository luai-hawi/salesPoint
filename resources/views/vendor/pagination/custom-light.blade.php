@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="inline-flex items-center space-x-1 text-sm font-medium text-gray-700">
        {{-- Previous Page Link --}}
        @if ($paginator->onFirstPage())
            <span class="px-3 py-1 rounded bg-gray-200 text-gray-400 cursor-not-allowed" aria-disabled="true" aria-label="@lang('pagination.previous')">
                &laquo;
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="px-3 py-1 rounded bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-900" aria-label="@lang('pagination.previous')">&laquo;</a>
        @endif

        {{-- Pagination Elements --}}
        @foreach ($elements as $element)
            {{-- "Three Dots" Separator --}}
            @if (is_string($element))
                <span class="px-3 py-1 rounded bg-white border border-gray-300 text-gray-500">{{ $element }}</span>
            @endif

            {{-- Array Of Links --}}
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page" class="px-3 py-1 rounded bg-blue-100 text-blue-700 border border-blue-300">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="px-3 py-1 rounded bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-900">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next Page Link --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="px-3 py-1 rounded bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-900" aria-label="@lang('pagination.next')">&raquo;</a>
        @else
            <span class="px-3 py-1 rounded bg-gray-200 text-gray-400 cursor-not-allowed" aria-disabled="true" aria-label="@lang('pagination.next')">&raquo;</span>
        @endif
    </nav>
@endif
