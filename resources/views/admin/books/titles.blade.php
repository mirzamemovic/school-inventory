<x-layouts.admin :title="__('books.title')">
    <x-flash />

    <div class="mb-5 flex flex-wrap items-center gap-x-5 gap-y-2">
        <a href="{{ route('admin.books.issue.form') }}" class="btn-primary">
            {{ __('books.issue') }}
        </a>
        <a href="{{ route('admin.books.returns.form') }}" class="link-action">
            {{ __('books.returns') }}
        </a>
    </div>

    {{-- At the top, where adding something is expected, but folded: the list
         is what this page is for. It opens by itself when there is nothing yet
         or the last attempt failed, so the errors are the first thing seen. --}}
    <details class="mb-5 max-w-md rounded border border-grey-200 bg-white" @if ($titles->isEmpty() && ! request('q') || $errors->any()) open @endif>
        <summary class="disclosure flex min-h-tap cursor-pointer items-center px-4 py-2 text-lg font-bold">
            {{ __('books.add_title') }}
        </summary>

        <form method="POST" action="{{ route('admin.books.titles.store') }}" class="border-t border-grey-200 p-4">
            @csrf
            @include('admin.books._title-fields', ['title' => new \App\Models\BookTitle])

            <button type="submit" class="btn-secondary" data-busy-text="{{ __('common.saving') }}">
                {{ __('books.add_title') }}
            </button>
        </form>
    </details>

    {{-- Not focused after a failed save: on a phone that would scroll the
         errors above it out of sight. --}}
    <form method="GET" action="{{ route('admin.books.titles') }}" class="mb-4 max-w-md" role="search">
        <label for="q" class="sr-only">{{ __('books.search_placeholder') }}</label>
        <x-input name="q" id="q" type="search" :value="request('q')" :placeholder="__('books.search_placeholder')" :autofocus="! $errors->any()" />
    </form>

    @if ($titles->isEmpty())
        <x-empty-state :message="request('q') ? __('assets.no_matches') : __('books.no_titles')" />
    @else
        <ul class="divide-y divide-grey-200 rounded border border-grey-200 bg-white">
            @foreach ($titles as $title)
                @php $available = max(0, $title->total_copies - $title->copies_out_count); @endphp
                <li class="flex flex-wrap items-center gap-x-6 gap-y-2 px-3 py-3 sm:px-4">
                    <span class="min-w-0 flex-1 basis-56">
                        <span class="block font-semibold">{{ $title->title }}</span>
                        <span class="block text-sm text-grey-600">
                            {{ collect([$title->subject, __('books.grade', ['grade' => $title->grade])])->filter()->join(' · ') }}
                        </span>
                    </span>

                    <dl class="flex gap-5 text-sm tabular-nums">
                        <div>
                            <dt class="text-grey-600">{{ __('books.owned') }}</dt>
                            <dd class="font-semibold">{{ $title->total_copies }}</dd>
                        </div>
                        <div>
                            <dt class="text-grey-600">{{ __('books.out') }}</dt>
                            <dd class="font-semibold">{{ $title->copies_out_count }}</dd>
                        </div>
                        <div>
                            <dt class="text-grey-600">{{ __('books.available') }}</dt>
                            <dd class="font-semibold">{{ $available }}</dd>
                        </div>
                    </dl>

                    <a href="{{ route('admin.books.titles.edit', $title) }}" class="link-action text-sm">
                        {{ __('common.edit') }}
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.admin>
