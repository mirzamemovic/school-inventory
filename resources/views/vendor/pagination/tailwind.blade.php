{{--
    Replaces the framework's pagination view. That one is written against the
    default Tailwind palette, which this application clears on purpose, so its
    grey classes did not exist and its focus:outline-none left keyboard users
    with no visible focus at all.

    Previous, where you are, next. Nothing else is needed for a list of a
    hundred people.
--}}
@if ($paginator->hasPages())
    <nav aria-label="{{ __('common.pages') }}" class="flex items-center justify-between gap-3">
        @if ($paginator->onFirstPage())
            <span class="inline-flex min-h-tap items-center gap-2 rounded border border-grey-200 bg-grey-50 px-4 font-semibold text-grey-500" aria-disabled="true">
                <span aria-hidden="true">←</span> {{ __('common.previous_page') }}
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn-secondary">
                <span aria-hidden="true">←</span> {{ __('common.previous_page') }}
            </a>
        @endif

        <p class="text-sm text-grey-600 tabular-nums">
            {{ __('common.page_of', ['page' => $paginator->currentPage(), 'pages' => $paginator->lastPage()]) }}
        </p>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn-secondary">
                {{ __('common.next_page') }} <span aria-hidden="true">→</span>
            </a>
        @else
            <span class="inline-flex min-h-tap items-center gap-2 rounded border border-grey-200 bg-grey-50 px-4 font-semibold text-grey-500" aria-disabled="true">
                {{ __('common.next_page') }} <span aria-hidden="true">→</span>
            </span>
        @endif
    </nav>
@endif
