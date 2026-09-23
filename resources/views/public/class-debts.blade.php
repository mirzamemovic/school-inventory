{{--
    A read-only page reached by a signed link. No navigation, nothing to click,
    nothing that writes. Printable, because the class teacher will want it on
    paper (spec section 7C).
--}}
<x-layouts.public :title="__('books.class_debts_heading', ['class' => $class])">
    <h1 class="text-2xl font-bold tracking-tight">{{ __('books.class_debts_heading', ['class' => $class]) }}</h1>
    <p class="mt-1 text-sm text-grey-600 tabular-nums">{{ __('books.class_debts_year', ['year' => $academicYear]) }}</p>

    @if ($students->isEmpty())
        <p class="mt-6 rounded border border-status-ok-soft bg-status-ok-soft p-4 font-medium text-status-ok">
            {{ __('books.class_debts_none') }}
        </p>
    @else
        <ul class="mt-6 divide-y divide-grey-200 rounded border border-grey-200">
            @foreach ($students as $student)
                <li class="p-3">
                    <p class="font-semibold">{{ $student->name }}</p>
                    <ul class="mt-1 space-y-1">
                        @foreach ($student->outstandingLoans as $loan)
                            <li class="flex flex-wrap items-baseline gap-2 text-sm">
                                <span class="flex-1">{{ $loan->title->title }}</span>
                                @if ($loan->isOverdue())
                                    <span class="rounded bg-status-broken-soft px-1.5 py-0.5 text-xs font-semibold text-status-broken">
                                        {{ __('books.overdue') }}
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.public>
