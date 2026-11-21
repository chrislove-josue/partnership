@if ($paginator->hasPages())
    @php
        $chevronLeft = '<svg class="lh-pagination__arrow" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>';
        $chevronRight = '<svg class="lh-pagination__arrow" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>';
    @endphp

    <style>
        .lh-pagination {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding-top: 1rem;
            font-size: 0.8125rem;
            color: #4b5563;
        }

        .lh-pagination__summary {
            margin: 0;
        }

        .lh-pagination__summary strong {
            color: #111827;
            font-weight: 600;
        }

        .lh-pagination__list {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.25rem;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .lh-pagination__item {
            display: inline-flex;
        }

        .lh-pagination__link,
        .lh-pagination__current,
        .lh-pagination__disabled {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            min-width: 2rem;
            height: 2rem;
            padding: 0 0.6rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            background: #fff;
            color: #374151;
            font-size: 0.8125rem;
            line-height: 1;
            text-decoration: none;
        }

        .lh-pagination__link:hover {
            background: #f3f4f6;
        }

        .lh-pagination__current {
            background: #1f4e78;
            border-color: #1f4e78;
            color: #fff;
            font-weight: 600;
        }

        .lh-pagination__disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        .lh-pagination__arrow {
            width: 0.9rem;
            height: 0.9rem;
            flex: none;
        }

        .dark .lh-pagination {
            color: #d1d5db;
        }

        .dark .lh-pagination__summary strong {
            color: #f9fafb;
        }

        .dark .lh-pagination__link,
        .dark .lh-pagination__current,
        .dark .lh-pagination__disabled {
            background: #1f2937;
            border-color: #374151;
            color: #e5e7eb;
        }

        .dark .lh-pagination__current {
            background: #1f4e78;
            border-color: #1f4e78;
            color: #fff;
        }
    </style>

    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="lh-pagination">
        <p class="lh-pagination__summary">
            @if ($paginator->firstItem())
                {{ __('Showing') }}
                <strong>{{ $paginator->firstItem() }}</strong>
                {{ __('to') }}
                <strong>{{ $paginator->lastItem() }}</strong>
            @else
                <strong>{{ $paginator->count() }}</strong>
            @endif
            {{ __('of') }}
            <strong>{{ $paginator->total() }}</strong>
            {{ __('results') }}
        </p>

        <ul class="lh-pagination__list">
            @if ($paginator->onFirstPage())
                <li class="lh-pagination__item">
                    <span class="lh-pagination__disabled" aria-disabled="true">
                        {!! $chevronLeft !!}
                        <span>{{ __('pagination.previous') }}</span>
                    </span>
                </li>
            @else
                <li class="lh-pagination__item">
                    <a class="lh-pagination__link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}">
                        {!! $chevronLeft !!}
                        <span>{{ __('pagination.previous') }}</span>
                    </a>
                </li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="lh-pagination__item">
                        <span class="lh-pagination__disabled">{{ $element }}</span>
                    </li>
                @else
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <li class="lh-pagination__item">
                                <span class="lh-pagination__current" aria-current="page">{{ $page }}</span>
                            </li>
                        @else
                            <li class="lh-pagination__item">
                                <a class="lh-pagination__link" href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li class="lh-pagination__item">
                    <a class="lh-pagination__link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}">
                        <span>{{ __('pagination.next') }}</span>
                        {!! $chevronRight !!}
                    </a>
                </li>
            @else
                <li class="lh-pagination__item">
                    <span class="lh-pagination__disabled" aria-disabled="true">
                        <span>{{ __('pagination.next') }}</span>
                        {!! $chevronRight !!}
                    </span>
                </li>
            @endif
        </ul>
    </nav>
@endif
