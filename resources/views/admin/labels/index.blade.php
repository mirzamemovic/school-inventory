@php
    $columns = config('school.labels.columns');
@endphp
<x-layouts.admin :title="__('labels.title')">
    <x-flash />

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded border border-grey-200 bg-white p-4">
            <h2 class="mb-1 text-lg font-bold tracking-tight">{{ __('labels.queue_heading') }}</h2>
            <p class="mb-4 text-sm text-grey-600">
                {{ trans_choice('labels.queue_count', $queued->count(), ['count' => $queued->count()]) }}
            </p>

            @if ($queued->isEmpty())
                <p class="text-sm text-grey-500">{{ __('labels.queue_empty') }}</p>
            @else
                <ul class="mb-4 divide-y divide-grey-200 rounded border border-grey-200">
                    @foreach ($queued as $item)
                        <li class="flex min-h-tap items-center gap-3 px-3">
                            <span class="min-w-0 flex-1 truncate text-sm">{{ $item->asset->displayName() }}</span>
                            <span class="font-mono text-xs text-grey-500">{{ $item->asset->asset_tag }}</span>
                            <form method="POST" action="{{ route('admin.labels.queue.remove', $item->asset) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="min-h-tap text-xs text-navy underline">{{ __('assets.actions.remove_from_queue') }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>

                <form method="POST" action="{{ route('admin.labels.queue.print') }}">
                    @csrf
                    <x-sheet-position :per-sheet="$perSheet" :columns="$columns" />
                    <button type="submit" class="inline-flex min-h-tap items-center rounded bg-navy px-5 font-semibold text-white hover:bg-navy-deep">
                        {{ __('labels.print_sheet') }}
                    </button>
                    <p class="mt-2 text-xs text-grey-500">{{ __('labels.print_sheet_help') }}</p>
                </form>
            @endif
        </section>

        <section class="rounded border border-grey-200 bg-white p-4">
            <h2 class="mb-1 text-lg font-bold tracking-tight">{{ __('labels.blank_heading') }}</h2>
            <p class="mb-4 text-sm text-grey-600">{{ __('labels.blank_help') }}</p>

            <form method="POST" action="{{ route('admin.labels.blank') }}">
                @csrf

                <x-field :label="__('labels.blank_count')" name="count" required>
                    <x-input name="count" type="number" min="1" max="480" :value="old('count', $perSheet)" required class="max-w-32" />
                </x-field>

                <x-sheet-position :per-sheet="$perSheet" :columns="$columns" />

                <button type="submit" class="inline-flex min-h-tap items-center rounded bg-navy px-5 font-semibold text-white hover:bg-navy-deep">
                    {{ __('labels.generate') }}
                </button>
            </form>
        </section>
    </div>

    <section class="mt-6 rounded border border-grey-200 bg-white p-4">
        <h2 class="mb-1 text-lg font-bold tracking-tight">{{ __('labels.reprint_heading') }}</h2>
        <p class="mb-4 text-sm text-grey-600">{{ __('labels.reprint_help') }}</p>

        <form method="GET" action="{{ route('admin.labels.index') }}" class="mb-4 max-w-md">
            <label for="q" class="sr-only">{{ __('assets.search_placeholder') }}</label>
            <x-input name="q" id="q" type="search" :value="request('q')" :placeholder="__('assets.search_placeholder')" />
        </form>

        @if ($reprintable->isNotEmpty())
            <form method="POST" action="{{ route('admin.labels.reprint') }}">
                @csrf

                <ul class="mb-4 max-h-80 divide-y divide-grey-200 overflow-y-auto rounded border border-grey-200">
                    @foreach ($reprintable as $asset)
                        <li>
                            <label class="flex min-h-tap cursor-pointer items-center gap-3 px-3">
                                <input type="checkbox" name="asset_ids[]" value="{{ $asset->id }}" class="size-5 rounded border-grey-300">
                                <span class="min-w-0 flex-1 truncate text-sm">{{ $asset->displayName() }}</span>
                                <span class="font-mono text-xs text-grey-500">{{ $asset->asset_tag }}</span>
                            </label>
                        </li>
                    @endforeach
                </ul>

                <x-sheet-position :per-sheet="$perSheet" :columns="$columns" />

                <button type="submit" class="inline-flex min-h-tap items-center rounded border border-grey-300 px-5 font-semibold hover:bg-grey-50">
                    {{ __('common.print') }}
                </button>
            </form>
        @else
            <p class="text-sm text-grey-500">{{ __('assets.no_matches') }}</p>
        @endif
    </section>
</x-layouts.admin>
