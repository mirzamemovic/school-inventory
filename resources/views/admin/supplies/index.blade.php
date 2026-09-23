<x-layouts.admin :title="__('supplies.title')">
    <x-flash />

    <form method="GET" action="{{ route('admin.supplies.index') }}" class="mb-4 max-w-md">
        <label for="q" class="sr-only">{{ __('supplies.search_placeholder') }}</label>
        <x-input name="q" id="q" type="search" :value="request('q')" :placeholder="__('supplies.search_placeholder')" autofocus />
    </form>

    @if ($supplies->isEmpty())
        <x-empty-state :message="__('supplies.none_yet')" />
    @else
        <ul class="mb-8 divide-y divide-grey-200 rounded border border-grey-200 bg-white">
            @foreach ($supplies as $supply)
                @include('admin.supplies._row')
            @endforeach
        </ul>
    @endif

    <h2 class="mb-3 text-lg font-bold tracking-tight">{{ __('supplies.add') }}</h2>

    <form method="POST" action="{{ route('admin.supplies.store') }}" class="max-w-md">
        @csrf
        @include('admin.supplies._fields', ['supply' => new \App\Models\Supply])

        <button type="submit" class="inline-flex min-h-tap items-center rounded bg-navy px-5 font-semibold text-white hover:bg-navy-deep">
            {{ __('common.add') }}
        </button>
    </form>
</x-layouts.admin>
