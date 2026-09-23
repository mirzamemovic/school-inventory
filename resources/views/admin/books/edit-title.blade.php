<x-layouts.admin :title="$title->title">
    <x-flash />

    <form method="POST" action="{{ route('admin.books.titles.update', $title) }}" class="max-w-md">
        @csrf
        @method('PUT')
        @include('admin.books._title-fields')

        <div class="flex items-center gap-4">
            <button type="submit" class="inline-flex min-h-tap items-center rounded bg-navy px-5 font-semibold text-white hover:bg-navy-deep">
                {{ __('common.save_changes') }}
            </button>
            <a href="{{ route('admin.books.titles') }}" class="text-sm text-grey-600 underline">{{ __('common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
