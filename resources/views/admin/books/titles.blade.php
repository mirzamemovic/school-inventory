<x-layouts.admin :title="__('books.title')">
    <x-flash />

    <div class="mb-4 flex flex-wrap items-center gap-4">
        <a href="{{ route('admin.books.issue.form') }}"
           class="inline-flex min-h-tap items-center rounded bg-navy px-4 font-semibold text-white hover:bg-navy-deep">
            {{ __('books.issue') }}
        </a>
        <a href="{{ route('admin.books.returns.form') }}" class="text-sm font-medium text-navy underline">
            {{ __('books.returns') }}
        </a>
    </div>

    <form method="GET" action="{{ route('admin.books.titles') }}" class="mb-4 max-w-md">
        <label for="q" class="sr-only">{{ __('books.search_placeholder') }}</label>
        <x-input name="q" id="q" type="search" :value="request('q')" :placeholder="__('books.search_placeholder')" autofocus />
    </form>

    @if ($titles->isEmpty())
        <x-empty-state :message="__('books.no_titles')" />
    @else
        <ul class="mb-8 divide-y divide-grey-200 rounded border border-grey-200 bg-white">
            @foreach ($titles as $title)
                <li class="flex min-h-tap flex-wrap items-center gap-x-4 gap-y-1 p-3">
                    <span class="min-w-0 flex-1">
                        <span class="block font-medium">{{ $title->title }}</span>
                        <span class="block text-sm text-grey-500">
                            {{ collect([$title->subject, __('books.grade', ['grade' => $title->grade])])->filter()->join(' · ') }}
                        </span>
                    </span>

                    <span class="text-sm tabular-nums text-grey-600">
                        {{ __('books.owned') }} {{ $title->total_copies }}
                        · {{ __('books.out') }} {{ $title->copies_out_count }}
                        · {{ __('books.available') }} {{ max(0, $title->total_copies - $title->copies_out_count) }}
                    </span>

                    <a href="{{ route('admin.books.titles.edit', $title) }}" class="min-h-tap text-sm text-navy underline">
                        {{ __('common.edit') }}
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    <h2 class="mb-3 text-lg font-bold tracking-tight">{{ __('books.add_title') }}</h2>

    <form method="POST" action="{{ route('admin.books.titles.store') }}" class="max-w-md">
        @csrf
        @include('admin.books._title-fields', ['title' => new \App\Models\BookTitle])

        <button type="submit" class="inline-flex min-h-tap items-center rounded bg-navy px-5 font-semibold text-white hover:bg-navy-deep">
            {{ __('common.add') }}
        </button>
    </form>
</x-layouts.admin>
