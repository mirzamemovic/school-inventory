<x-layouts.admin :title="__('people.title')">
    <x-flash />

    <div class="mb-4 flex flex-wrap items-center gap-4">
        <a href="{{ route('admin.people.create') }}"
           class="inline-flex min-h-tap items-center rounded bg-navy px-4 font-semibold text-white hover:bg-navy-deep">
            {{ __('people.add') }}
        </a>
        <a href="{{ route('admin.people.import.form') }}" class="text-sm font-medium text-navy underline">
            {{ __('people.import') }}
        </a>
    </div>

    <form method="GET" action="{{ route('admin.people.index') }}" class="mb-4 space-y-3">
        <label for="q" class="sr-only">{{ __('people.search_placeholder') }}</label>
        <x-input name="q" id="q" type="search" :value="request('q')" :placeholder="__('people.search_placeholder')" autofocus />

        <div class="flex flex-wrap gap-2">
            <x-select name="type" :options="\App\Enums\PersonType::options()" :selected="request('type')"
                      :placeholder="__('people.labels.type')" class="w-auto" />
            <x-select name="class" :options="$classes->mapWithKeys(fn ($c) => [$c => $c])" :selected="request('class')"
                      :placeholder="__('people.labels.class')" class="w-auto" />
            <button type="submit" class="min-h-tap rounded border border-grey-300 bg-white px-4 text-sm font-medium">
                {{ __('common.search') }}
            </button>
        </div>
    </form>

    @if ($people->isEmpty())
        <x-empty-state :message="__('people.none_yet')" />
    @else
        <ul class="divide-y divide-grey-200 rounded border border-grey-200 bg-white">
            @foreach ($people as $person)
                <li class="flex min-h-tap flex-wrap items-center gap-x-3 gap-y-1 p-3">
                    <span class="min-w-0 flex-1">
                        <span class="block font-medium {{ $person->active ? '' : 'text-grey-500' }}">{{ $person->name }}</span>
                        <span class="block text-sm text-grey-500">
                            {{ collect([$person->type->label(), $person->class])->filter()->join(' · ') }}
                        </span>
                    </span>

                    @unless ($person->active)
                        <span class="rounded bg-status-inactive-soft px-2 py-0.5 text-xs font-semibold text-status-inactive">
                            {{ __('people.inactive') }}
                        </span>
                    @endunless

                    @if ($person->books_out > 0)
                        <a href="{{ route('admin.books.student', $person) }}" class="text-sm text-navy underline tabular-nums">
                            {{ trans_choice('people.books_out', $person->books_out, ['count' => $person->books_out]) }}
                        </a>
                    @endif

                    <a href="{{ route('admin.people.edit', $person) }}" class="min-h-tap text-sm text-navy underline">
                        {{ __('common.edit') }}
                    </a>

                    {{-- Deactivate, never delete: old book loans point here. --}}
                    <form method="POST" action="{{ route($person->active ? 'admin.people.deactivate' : 'admin.people.reactivate', $person) }}">
                        @csrf
                        <button type="submit" class="min-h-tap text-sm text-grey-600 underline">
                            {{ $person->active ? __('people.deactivate') : __('people.reactivate') }}
                        </button>
                    </form>
                </li>
            @endforeach
        </ul>

        <div class="mt-4">{{ $people->links() }}</div>
    @endif
</x-layouts.admin>
