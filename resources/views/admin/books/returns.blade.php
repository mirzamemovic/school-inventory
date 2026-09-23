<x-layouts.admin :title="__('books.returns')">
    <x-flash />

    @if ($classes->isEmpty())
        <x-empty-state :message="__('books.no_classes')" />
    @else
        <div class="print:hidden">
            @include('admin.books._class-picker', ['action' => route('admin.books.returns.form')])
        </div>

        @if ($class)
            <h2 class="text-lg font-bold tracking-tight">{{ __('books.returns_heading', ['class' => $class]) }}</h2>

            @php $anyOut = $students->contains(fn ($s) => $s->outstandingLoans->isNotEmpty()); @endphp

            @if (! $anyOut)
                <x-empty-state class="mt-4" :message="__('books.nothing_out')" />
            @else
                <p class="mt-1 mb-4 text-sm text-grey-600 print:hidden">{{ __('books.returns_help') }}</p>

                <form method="POST" action="{{ route('admin.books.returns') }}">
                    @csrf
                    <input type="hidden" name="class" value="{{ $class }}">

                    <ul class="divide-y divide-grey-200 rounded border border-grey-200 bg-white">
                        @foreach ($students as $student)
                            @continue($student->outstandingLoans->isEmpty())
                            <li class="p-3">
                                <p class="font-medium">
                                    <a href="{{ route('admin.books.student', $student) }}" class="text-navy underline print:no-underline print:text-grey-900">
                                        {{ $student->name }}
                                    </a>
                                </p>

                                <ul class="mt-2 space-y-1">
                                    @foreach ($student->outstandingLoans as $loan)
                                        <li class="flex flex-wrap items-center gap-3">
                                            <label class="flex min-h-tap flex-1 items-center gap-3 text-sm">
                                                <input type="checkbox" class="size-5 rounded border-grey-300 print:hidden"
                                                       name="returned[{{ $loan->id }}]" value="good">
                                                <span>{{ $loan->title->title }}</span>
                                            </label>

                                            @if ($loan->isOverdue())
                                                <span class="rounded bg-status-broken-soft px-1.5 py-0.5 text-xs font-semibold text-status-broken">
                                                    {{ __('books.overdue') }}
                                                </span>
                                            @elseif ($loan->due_at)
                                                <span class="text-xs text-grey-500 tabular-nums">
                                                    {{ __('books.due', ['date' => $loan->due_at->format('j M Y')]) }}
                                                </span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </li>
                        @endforeach
                    </ul>

                    <button type="submit" class="mt-4 inline-flex min-h-tap items-center rounded bg-navy px-5 font-semibold text-white hover:bg-navy-deep print:hidden">
                        {{ __('books.take_back') }}
                    </button>
                </form>
            @endif

            {{-- The class teacher gets a link, not an account (spec section 3.1). --}}
            <section class="mt-8 rounded border border-grey-200 bg-white p-4 print:hidden">
                <h3 class="font-semibold">{{ __('books.share_heading') }}</h3>
                <p class="mt-1 text-sm text-grey-600">{{ __('books.share_help') }}</p>
                <input type="text" readonly value="{{ $shareUrl }}"
                       onfocus="this.select()"
                       class="mt-3 block w-full rounded border border-grey-300 bg-grey-50 px-3 py-2 font-mono text-xs">
            </section>
        @endif
    @endif
</x-layouts.admin>
