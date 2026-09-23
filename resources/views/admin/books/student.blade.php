<x-layouts.admin :title="$person->name">
    <x-flash />

    <p class="mb-6 text-grey-600">
        {{ collect([$person->type->label(), $person->class])->filter()->join(' · ') }}
    </p>

    <div class="grid gap-6 lg:grid-cols-2">
        <section>
            <h2 class="mb-3 text-lg font-bold tracking-tight">{{ __('books.holdings') }}</h2>

            @if ($titles->isEmpty())
                <p class="text-sm text-grey-500">{{ __('books.no_titles_for_grade') }}</p>
            @else
                {{-- Mid-year: tick or untick a title. Nothing more. --}}
                <form method="POST" action="{{ route('admin.books.student.update', $person) }}">
                    @csrf
                    @method('PUT')

                    <ul class="divide-y divide-grey-200 rounded border border-grey-200 bg-white">
                        @foreach ($titles as $title)
                            <li>
                                <label class="flex min-h-tap cursor-pointer items-center gap-3 p-3 text-sm">
                                    <input type="checkbox" name="titles[]" value="{{ $title->id }}"
                                           class="size-5 rounded border-grey-300"
                                           @checked(in_array($title->id, $held, true))>
                                    <span class="flex-1">{{ $title->title }}</span>
                                    <span class="text-xs text-grey-500">{{ $title->subject }}</span>
                                </label>
                            </li>
                        @endforeach
                    </ul>

                    <button type="submit" class="mt-4 inline-flex min-h-tap items-center rounded bg-navy px-5 font-semibold text-white hover:bg-navy-deep">
                        {{ __('books.save_holdings') }}
                    </button>
                </form>
            @endif
        </section>

        <section>
            <h2 class="mb-3 text-lg font-bold tracking-tight">{{ __('books.previous_years') }}</h2>

            @if ($previous->isEmpty())
                <p class="text-sm text-grey-500">{{ __('books.no_previous') }}</p>
            @else
                <ul class="divide-y divide-grey-200 rounded border border-grey-200 bg-white">
                    @foreach ($previous as $loan)
                        <li class="flex min-h-tap flex-wrap items-center gap-x-3 gap-y-1 p-3 text-sm">
                            <span class="min-w-0 flex-1">{{ $loan->title->title }}</span>
                            <span class="text-xs text-grey-500 tabular-nums">{{ $loan->academic_year }}</span>
                            @if ($loan->condition_on_return)
                                <span class="rounded px-1.5 py-0.5 text-xs font-semibold {{ $loan->condition_on_return->badgeClasses() }}">
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
