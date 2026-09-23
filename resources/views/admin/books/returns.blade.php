<x-layouts.admin :title="__('books.returns')" :back="route('admin.books.titles')" :back-label="__('books.title')">
    <x-flash />

    @if ($classes->isEmpty())
        <x-empty-state :message="__('books.no_classes')">
            <a href="{{ route('admin.people.index') }}" class="link-action">{{ __('people.title') }}</a>
        </x-empty-state>
    @else
        @include('admin.books._class-picker', ['action' => route('admin.books.returns.form')])

        @if ($class)
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-bold tracking-tight">{{ __('books.returns_heading', ['class' => $class]) }}</h2>

                {{-- The class teacher will want this on paper (spec section 7C). --}}
                <button type="button" onclick="window.print()" class="btn-secondary text-sm print:hidden">
                    {{ __('books.print') }}
                </button>
            </div>

            @php $anyOut = $students->contains(fn ($s) => $s->outstandingLoans->isNotEmpty()); @endphp

            @if (! $anyOut)
                <div class="rounded border border-status-ok-soft bg-status-ok-soft px-4 py-5">
                    <p class="font-semibold text-status-ok">{{ __('books.nothing_out') }}</p>
                </div>
            @else
                <p class="mb-4 text-grey-700 print:hidden">{{ __('books.returns_help') }}</p>

                <form method="POST" action="{{ route('admin.books.returns') }}">
                    @csrf
                    <input type="hidden" name="class" value="{{ $class }}">

                    <ul class="divide-y divide-grey-200 rounded border border-grey-200 bg-white">
                        @foreach ($students as $student)
                            @continue($student->outstandingLoans->isEmpty())
                            <li class="px-3 py-3 sm:px-4">
                                <p class="font-semibold">
                                    <a href="{{ route('admin.books.student', $student) }}" class="link text-grey-900 print:no-underline">
                                        {{ $student->name }}
                                    </a>
                                </p>

                                <ul class="mt-1">
                                    @foreach ($student->outstandingLoans as $loan)
                                        <li class="flex flex-wrap items-center gap-x-3">
                                            <label class="flex min-h-tap flex-1 cursor-pointer items-center gap-3">
                                                <input type="checkbox" class="size-5 rounded border-grey-300 print:hidden"
                                                       name="returned[{{ $loan->id }}]" value="good">
                                                {{-- A box to tick with a pen on the printed copy. --}}
                                                <span aria-hidden="true" class="hidden print:inline">☐</span>
                                                <span>{{ $loan->title->title }}</span>
                                            </label>

                                            @if ($loan->isOverdue())
                                                <span class="rounded bg-status-broken-soft px-2 py-0.5 text-xs font-semibold text-status-broken">
                                                    {{ __('books.overdue') }}
                                                </span>
                                            @elseif ($loan->due_at)
                                                <span class="text-sm text-grey-600 tabular-nums">
                                                    {{ __('books.due', ['date' => $loan->due_at->format('j M Y')]) }}
                                                </span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </li>
                        @endforeach
                    </ul>

                    <button type="submit" class="btn-primary mt-4 w-full sm:w-auto print:hidden" data-busy-text="{{ __('common.saving') }}">
                        {{ __('books.take_back') }}
                    </button>
                </form>
            @endif

            {{-- The class teacher gets a link, not an account (spec section 3.1). --}}
            <section class="mt-8 rounded border border-grey-200 bg-white p-4 print:hidden" aria-labelledby="share-heading">
                <h3 id="share-heading" class="font-bold">{{ __('books.share_heading') }}</h3>
                <p class="mt-1 text-sm text-grey-600">{{ __('books.share_help') }}</p>
                <input type="text" readonly value="{{ $shareUrl }}" aria-labelledby="share-heading"
                       onfocus="this.select()"
                       class="mt-3 block min-h-tap w-full rounded border border-grey-300 bg-grey-50 px-3 font-mono text-sm text-grey-800">
            </section>
        @endif
    @endif
</x-layouts.admin>
