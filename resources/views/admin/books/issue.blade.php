<x-layouts.admin :title="__('books.issue')">
    <x-flash />

    @if ($classes->isEmpty())
        <x-empty-state :message="__('books.no_classes')">
            <a href="{{ route('admin.people.index') }}" class="text-sm text-navy underline">{{ __('people.title') }}</a>
        </x-empty-state>
    @else
        @include('admin.books._class-picker', ['action' => route('admin.books.issue.form')])

        @if ($class && $students->isEmpty())
            <x-empty-state :message="__('books.no_students')" />
        @elseif ($class && $titles->isEmpty())
            <x-empty-state :message="__('books.no_titles_for_grade')" />
        @elseif ($class)
            <h2 class="text-lg font-bold tracking-tight">{{ __('books.issue_heading', ['class' => $class]) }}</h2>
            <p class="mt-1 mb-4 text-sm text-grey-600">{{ __('books.issue_help') }}</p>

            <form method="POST" action="{{ route('admin.books.issue') }}">
                @csrf
                <input type="hidden" name="class" value="{{ $class }}">

                <div class="overflow-x-auto rounded border border-grey-200 bg-white">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-grey-200 text-left">
                                <th scope="col" class="p-3 font-semibold">{{ __('people.labels.name') }}</th>
                                @foreach ($titles as $title)
                                    <th scope="col" class="p-3 text-center font-medium">{{ $title->title }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-grey-200">
                            @foreach ($students as $student)
                                @php $alreadyHeld = $held[$student->id] ?? []; @endphp
                                <tr>
                                    <th scope="row" class="p-3 text-left font-medium whitespace-nowrap">
                                        <a href="{{ route('admin.books.student', $student) }}" class="text-navy underline">
                                            {{ $student->name }}
                                        </a>
                                    </th>
                                    @foreach ($titles as $title)
                                        <td class="p-3 text-center">
                                            @if (in_array($title->id, $alreadyHeld, true))
                                                <span class="text-xs text-grey-500">{{ __('books.already_has') }}</span>
                                            @else
                                                <label class="inline-flex min-h-tap min-w-tap items-center justify-center">
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

                <button type="submit" class="mt-4 inline-flex min-h-tap items-center rounded bg-navy px-5 font-semibold text-white hover:bg-navy-deep">
                    {{ __('books.confirm_class') }}
                </button>
            </form>
        @endif
    @endif
</x-layouts.admin>
