<x-layouts.admin :title="__('search.heading')">
    <x-flash />

    <form method="GET" action="{{ route('admin.search') }}" class="mb-4 max-w-xl">
        <label for="q" class="sr-only">{{ __('search.label') }}</label>
        <x-input name="q" id="q" type="search" :value="$term"
                 :placeholder="__('search.placeholder_long')" autofocus />
    </form>

    @if ($term !== '' && ! $tooShort && $groups->isNotEmpty())
        @php $count = $groups->sum(fn ($g) => $g['items']->count()); @endphp
        <p class="mb-3 text-sm text-grey-600 tabular-nums">
            {{ trans_choice('search.count', $count, ['count' => $count]) }}
        </p>
    @endif

    <div class="max-w-xl overflow-hidden rounded border border-grey-200 bg-white">
        @include('admin.search._results')
    </div>
</x-layouts.admin>
