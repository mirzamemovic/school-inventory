{{--
    The header search box, on every admin page.

    The form posts to the full results page, so it works with no JavaScript at
    all: type, press Enter, get a page of results. HTMX layers the dropdown on
    top of that, and search.js adds the "/" shortcut and arrow-key movement.
--}}
<div class="relative" data-search>
    <form method="GET" action="{{ route('admin.search') }}" role="search">
        <label for="global-search" class="sr-only">{{ __('search.label') }}</label>

        <input type="search"
               name="q"
               id="global-search"
               autocomplete="off"
               placeholder="{{ __('search.placeholder') }}"
               aria-describedby="global-search-hint"
               aria-expanded="false"
               aria-controls="global-search-results"
               data-search-input
               hx-get="{{ route('admin.search') }}"
               hx-trigger="input changed delay:200ms, focus"
               hx-target="#global-search-results"
               hx-swap="innerHTML"
               hx-indicator="#global-search-working"
               class="min-h-tap w-36 rounded border border-white/25 bg-white/10 px-3 text-sm text-white placeholder:text-white/60 focus:w-56 focus:border-white/60 focus:bg-white focus:text-grey-900 focus:placeholder:text-grey-500 sm:w-44 sm:focus:w-72">

        <span id="global-search-hint" class="sr-only">{{ __('search.shortcut_hint') }}</span>

        <span id="global-search-working" class="htmx-indicator absolute top-1/2 right-2 -translate-y-1/2 text-xs text-grey-500">
            {{ __('common.working') }}
        </span>
    </form>

    {{-- Hidden until there is something to show; search.js toggles it. --}}
    <div id="global-search-results"
         data-search-panel
         role="region"
         aria-label="{{ __('search.heading') }}"
         hidden
         class="absolute top-full right-0 z-40 mt-1 max-h-[70vh] w-[min(24rem,calc(100vw-2rem))] overflow-y-auto rounded border border-grey-200 bg-white text-grey-900 shadow-lg"></div>
</div>
