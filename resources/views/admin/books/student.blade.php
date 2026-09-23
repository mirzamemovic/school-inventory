<x-layouts.admin :title="$person->name" :back="route('admin.books.titles')" :back-label="__('books.title')">
    <x-slot:subtitle>
        <p class="text-grey-600">{{ collect([$person->type->label(), $person->class])->filter()->join(' · ') }}</p>
    </x-slot:subtitle>

    <x-flash />

    <div class="grid gap-8 lg:grid-cols-2">
        <section>
            <h2 class="mb-3 text-lg font-bold tracking-tight">{{ __('books.holdings') }}</h2>

            @if ($titles->isEmpty())
                <p class="text-sm text-grey-600">{{ __('books.no_titles_for_grade') }}</p>
            @else
                {{-- Mid-year: tick or untick a title. Nothing more. --}}
                <form method="POST" action="{{ route('admin.books.student.update', $person) }}">
                    @csrf
                    @method('PUT')

                    <ul class="divide-y divide-grey-200 rounded border border-grey-200 bg-white">
                        @foreach ($titles as $title)
                            <li>
                                <label class="flex min-h-tap cursor-pointer items-center gap-3 px-3 py-2 hover:bg-grey-50 sm:px-4">
                                    <input type="checkbox" name="titles[]" value="{{ $title->id }}"
                                           class="size-5 rounded border-grey-300"
                                           @checked(in_array($title->id, $held, true))>
                                    <span class="flex-1">{{ $title->title }}</span>
                                    <span class="text-sm text-grey-600">{{ $title->subject }}</span>
                                </label>
                            </li>
                        @endforeach
                    </ul>

                    <button type="submit" class="btn-primary mt-4 w-full sm:w-auto" data-busy-text="{{ __('common.saving') }}">
                        {{ __('books.save_holdings') }}
                    </button>
                </form>
            @endif
        </section>

        <section>
            <h2 class="mb-3 text-lg font-bold tracking-tight">{{ __('books.previous_years') }}</h2>

            @if ($previous->isEmpty())
                <p class="text-sm text-grey-600">{{ __('books.no_previous') }}</p>
            @else
                <ul class="divide-y divide-grey-200 rounded border border-grey-200 bg-white">
                    @foreach ($previous as $loan)
                        <li class="flex min-h-tap flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2 sm:px-4">
                            <span class="min-w-0 flex-1">{{ $loan->title->title }}</span>
                            <span class="text-sm text-grey-600 tabular-nums">{{ $loan->academic_year }}</span>
                            @if ($loan->condition_on_return)
                                <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $loan->condition_on_return->badgeClasses() }}">
                                    {{ $loan->condition_on_return->label() }}
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-layouts.admin>
