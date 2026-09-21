@if ($paginator->hasPages())
    <nav>
        <ul class="pagination">
            {{-- Previous Page Link --}}
            @if ($paginator->onFirstPage())

                @if($paginator->showEndButtons())
                <li class="page-item disabled" aria-disabled="true" aria-label="first">
                    <span class="page-link" aria-hidden="true">&lsaquo;&lsaquo;</span>
                </li>
                @endif

                <li class="page-item disabled" aria-disabled="true" aria-label="previous">
                    <span class="page-link" aria-hidden="true">&lsaquo;</span>
                </li>
            @else
                @if($paginator->showEndButtons())
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->firstPageUrl() }}" rel="first" aria-label="first">&lsaquo;&lsaquo;</a>
                </li>
                @endif

                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="previous">&lsaquo;</a>
                </li>
            @endif

            {{-- Pagination Elements --}}
            @foreach ($paginator->elements as $element)
                {{-- "Three Dots" Separator --}}
                @if (is_string($element))
                    <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                @endif

                {{-- Array Of Links --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                        @else
                            <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="next">&rsaquo;</a>
                </li>

                @if($paginator->showEndButtons())
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->lastPageUrl() }}" rel="next" aria-label="last">&rsaquo;&rsaquo;</a>
                </li>
                @endif
            @else
                <li class="page-item disabled" aria-disabled="true" aria-label="next">
                    <span class="page-link" aria-hidden="true">&rsaquo;</span>
                </li>

                @if($paginator->showEndButtons())
                <li class="page-item disabled" aria-disabled="true" aria-label="last">
                    <span class="page-link" aria-hidden="true">&rsaquo;&rsaquo;</span>
                </li>
                @endif
            @endif
        </ul>
    </nav>
@endif
