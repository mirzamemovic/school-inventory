<x-layouts.admin :title="$title->title" :back="route('admin.books.titles')" :back-label="__('books.title')">
    <x-flash />

    <form method="POST" action="{{ route('admin.books.titles.update', $title) }}" class="max-w-md">
        @csrf
        @method('PUT')
        @include('admin.books._title-fields')

        <div class="mt-6 flex flex-wrap items-center gap-x-5 gap-y-2">
            <button type="submit" class="btn-primary" data-busy-text="{{ __('common.saving') }}">
                {{ __('common.save_changes') }}
            </button>
            <a href="{{ route('admin.books.titles') }}" class="link-action text-grey-700">{{ __('common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
