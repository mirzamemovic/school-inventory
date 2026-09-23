<x-layouts.admin :title="__('books.issue')" :back="route('admin.books.titles')" :back-label="__('books.title')">
    <x-flash />

    @if ($classes->isEmpty())
        <x-empty-state :message="__('books.no_classes')">
            <a href="{{ route('admin.people.index') }}" class="link-action">{{ __('people.title') }}</a>
        </x-empty-state>
    @else
        @include('admin.books._class-picker', ['action' => route('admin.books.issue.form')])

        @if ($class && $students->isEmpty())
            <x-empty-state :message="__('books.no_students')" />
        @elseif ($class && $titles->isEmpty())
            <x-empty-state :message="__('books.no_titles_for_grade')">
                <a href="{{ route('admin.books.titles') }}" class="link-action">{{ __('books.add_title') }}</a>
            </x-empty-state>
        @elseif ($class)
            <h2 class="text-xl font-bold tracking-tight">{{ __('books.issue_heading', ['class' => $class]) }}</h2>
            <p class="mt-1 mb-4 max-w-prose text-grey-700">
                {{ __('books.issue_help') }}
                <span class="text-grey-600 tabular-nums">· {{ trans_choice('books.issue_count', $students->count(), ['count' => $students->count()]) }}</span>
            </p>

            <form method="POST" action="{{ route('admin.books.issue') }}">
                @csrf
                <input type="hidden" name="class" value="{{ $class }}">

                {{-- On a phone the table scrolls sideways; the names stay put so
                     every tick still belongs to someone. --}}
                <div class="overflow-x-auto rounded border border-grey-200 bg-white">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-grey-200 bg-grey-50 text-left align-bottom">
                                <th scope="col" class="sticky left-0 z-10 border-r border-grey-200 bg-grey-50 p-3 font-semibold">{{ __('people.labels.name') }}</th>
                                @foreach ($titles as $title)
                                    <th scope="col" class="min-w-24 p-3 text-center text-xs leading-snug font-semibold text-grey-700">{{ $title->title }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-grey-200">
                            @foreach ($students as $student)
                                @php $alreadyHeld = $held[$student->id] ?? []; @endphp
                                <tr class="hover:bg-grey-50">
                                    <th scope="row" class="sticky left-0 z-10 border-r border-grey-200 bg-white p-3 text-left font-medium whitespace-nowrap">
                                        <a href="{{ route('admin.books.student', $student) }}" class="link text-grey-900">
                                            {{ $student->name }}
                                        </a>
                                    </th>
                                    @foreach ($titles as $title)
                                        <td class="p-1 text-center">
                                            @if (in_array($title->id, $alreadyHeld, true))
                                                <span class="text-xs text-grey-600">{{ __('books.already_has') }}</span>
                                            @else
                                                <label class="inline-flex min-h-tap min-w-tap cursor-pointer items-center justify-center">
                                                    <span class="sr-only">{{ $student->name }} — {{ $title->title }}</span>
                                                    <input type="checkbox" class="size-5 rounded border-grey-300"
                                                           name="selection[{{ $student->id }}][]" value="{{ $title->id }}" checked>
                                                </label>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <button type="submit" class="btn-primary mt-4 w-full sm:w-auto" data-busy-text="{{ __('common.saving') }}">
                    {{ __('books.confirm_class') }}
                </button>
            </form>
        @endif
    @endif
</x-layouts.admin>
