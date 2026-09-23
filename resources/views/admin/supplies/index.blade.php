<x-layouts.admin :title="__('supplies.title')" :back="route('admin.manage')" :back-label="__('manage.title')">
    <x-flash />

    <form method="GET" action="{{ route('admin.supplies.index') }}" class="mb-4 max-w-md" role="search">
        <label for="q" class="sr-only">{{ __('supplies.search_placeholder') }}</label>
        <x-input name="q" id="q" type="search" :value="request('q')" :placeholder="__('supplies.search_placeholder')" autofocus />
    </form>

    @if ($supplies->isEmpty())
        <x-empty-state class="mb-8" :message="request('q') ? __('assets.no_matches') : __('supplies.none_yet')" />
    @else
        <ul class="mb-8 divide-y divide-grey-200 rounded border border-grey-200 bg-white">
            @foreach ($supplies as $supply)
                @include('admin.supplies._row')
            @endforeach
        </ul>
    @endif

    {{-- Adding is occasional; adjusting is what this page is for. It opens by
         itself when there is nothing yet or the last attempt failed. --}}
    <details class="max-w-md rounded border border-grey-200 bg-white" @if (($supplies->isEmpty() && ! request('q')) || $errors->any()) open @endif>
        <summary class="disclosure flex min-h-tap cursor-pointer items-center px-4 py-2 text-lg font-bold">
            {{ __('supplies.add') }}
        </summary>

        <form method="POST" action="{{ route('admin.supplies.store') }}" class="border-t border-grey-200 p-4">
            @csrf
            @include('admin.supplies._fields', ['supply' => new \App\Models\Supply])

            <button type="submit" class="btn-secondary" data-busy-text="{{ __('common.saving') }}">
                {{ __('supplies.add') }}
            </button>
        </form>
    </details>
</x-layouts.admin>
