<x-layouts.admin :title="__('people.title')" :back="route('admin.manage')" :back-label="__('manage.title')">
    <x-flash />

    <div class="mb-5 flex flex-wrap items-center gap-x-5 gap-y-2">
        <a href="{{ route('admin.people.create') }}" class="btn-primary">
            {{ __('people.add') }}
        </a>
        <a href="{{ route('admin.people.import.form') }}" class="link-action">
            {{ __('people.import') }}
        </a>
    </div>

    @php $filtering = request()->hasAny(['q', 'type', 'class']); @endphp

    <form method="GET" action="{{ route('admin.people.index') }}" class="mb-5 space-y-2" role="search">
        <label for="q" class="sr-only">{{ __('people.search_placeholder') }}</label>
        <x-input name="q" id="q" type="search" :value="request('q')" :placeholder="__('people.search_placeholder')" autofocus />

        <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center">
            <label for="filter-type" class="sr-only">{{ __('people.labels.type') }}</label>
            <x-select name="type" id="filter-type" :options="\App\Enums\PersonType::options()" :selected="request('type')"
                      :placeholder="__('people.filters.type')" class="sm:w-auto" />

            <label for="filter-class" class="sr-only">{{ __('people.labels.class') }}</label>
            <x-select name="class" id="filter-class" :options="$classes->mapWithKeys(fn ($c) => [$c => $c])" :selected="request('class')"
                      :placeholder="__('people.filters.class')" class="sm:w-auto" />

            <button type="submit" class="btn-secondary text-sm">
                {{ __('common.search') }}
            </button>

            @if ($filtering)
                <a href="{{ route('admin.people.index') }}" class="link-action text-sm text-grey-600">{{ __('activity.clear') }}</a>
            @endif
        </div>
    </form>

    @if ($people->isEmpty())
        <x-empty-state :message="$filtering ? __('assets.no_matches') : __('people.none_yet')">
            @unless ($filtering)
                <a href="{{ route('admin.people.create') }}" class="link-action">{{ __('people.add') }}</a>
                <a href="{{ route('admin.people.import.form') }}" class="link-action">{{ __('people.import') }}</a>
            @endunless
        </x-empty-state>
    @else
        <ul class="divide-y divide-grey-200 rounded border border-grey-200 bg-white">
            @foreach ($people as $person)
                <li class="flex flex-wrap items-center gap-x-5 gap-y-1 px-3 py-2.5 sm:px-4">
                    <span class="min-w-0 flex-1 basis-52">
                        <span class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold {{ $person->active ? '' : 'text-grey-600' }}">{{ $person->name }}</span>
                            @unless ($person->active)
                                <span class="rounded bg-status-inactive-soft px-2 py-0.5 text-xs font-semibold text-status-inactive">
                                    {{ __('people.inactive') }}
                                </span>
                            @endunless
                        </span>
                        <span class="block text-sm text-grey-600">
                            {{ collect([$person->type->label(), $person->class])->filter()->join(' · ') }}
                        </span>
                    </span>

                    <span class="flex flex-wrap items-center gap-x-4 text-sm">
                        @if ($person->books_out > 0)
                            <a href="{{ route('admin.books.student', $person) }}" class="link-action tabular-nums">
                                {{ trans_choice('people.books_out', $person->books_out, ['count' => $person->books_out]) }}
                            </a>
                        @endif

                        <a href="{{ route('admin.people.edit', $person) }}" class="link-action">
                            {{ __('common.edit') }}
                        </a>

                        {{-- Deactivate, never delete: old book loans point here. --}}
                        <form method="POST" action="{{ route($person->active ? 'admin.people.deactivate' : 'admin.people.reactivate', $person) }}">
                            @csrf
                            <button type="submit" class="link-action font-normal text-grey-700">
                                {{ $person->active ? __('people.deactivate') : __('people.reactivate') }}
                            </button>
                        </form>
                    </span>
                </li>
            @endforeach
        </ul>

        <div class="mt-4">{{ $people->links() }}</div>
    @endif
</x-layouts.admin>
