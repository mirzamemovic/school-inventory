{{--
    The header search box, on every admin page.

    The form posts to the full results page, so it works with no JavaScript at
    all: type, press Enter, get a page of results. HTMX layers the dropdown on
    top of that, and search.js adds the "/" shortcut and arrow-key movement.
--}}
{{-- Static on a phone, so the results panel spans the header rather than
     hanging off the left edge of the screen; anchored under the box from sm. --}}
<div class="shrink-0 sm:relative" data-search>
    <form method="GET" action="{{ route('admin.search') }}" role="search" class="relative">
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
               class="min-h-tap w-28 rounded border border-white/25 bg-white/10 px-3 text-base text-white transition-[width] duration-150 placeholder:text-white/70 focus:w-44 focus:border-white/60 focus:bg-white focus:text-grey-900 focus:placeholder:text-grey-500 sm:w-44 sm:text-sm sm:focus:w-72">

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
         class="absolute inset-x-2 top-full z-40 mt-1 max-h-[70vh] overflow-y-auto rounded border border-grey-200 bg-white text-grey-900 shadow-lg sm:inset-x-auto sm:right-0 sm:w-96"></div>
</div>
