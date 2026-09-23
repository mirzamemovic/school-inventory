@php
    $columns = config('school.labels.columns');

    // Whichever job is waiting is the primary one: printing the queue when
    // there is something in it, otherwise making blank labels.
    $queueFirst = $queued->isNotEmpty();
@endphp
<x-layouts.admin :title="__('labels.title')" :back="route('admin.assets.index')" :back-label="__('assets.title')">
    <x-flash />

    <div class="grid gap-4 lg:grid-cols-2 lg:items-start">
        <section class="rounded border border-grey-200 bg-white p-4" aria-labelledby="queue-heading">
            <h2 id="queue-heading" class="text-lg font-bold tracking-tight">{{ __('labels.queue_heading') }}</h2>

            @if ($queued->isEmpty())
                <p class="mt-3 rounded border border-dashed border-grey-300 px-3 py-4 text-center text-grey-700">{{ __('labels.queue_empty') }}</p>
            @else
                <p class="mb-4 text-sm text-grey-600 tabular-nums">
                    {{ trans_choice('labels.queue_count', $queued->count(), ['count' => $queued->count()]) }}
                </p>

                <ul class="mb-4 divide-y divide-grey-200 rounded border border-grey-200">
                    @foreach ($queued as $item)
                        <li class="flex min-h-tap items-center gap-3 pl-3">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold">{{ $item->asset->displayName() }}</span>
                                <span class="block font-mono text-xs text-grey-700">{{ $item->asset->asset_tag }}</span>
                            </span>
                            <form method="POST" action="{{ route('admin.labels.queue.remove', $item->asset) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="link-action px-3 text-sm font-normal text-grey-700">{{ __('assets.actions.remove_from_queue') }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>

                <form method="POST" action="{{ route('admin.labels.queue.print') }}">
                    @csrf
                    <x-sheet-position :per-sheet="$perSheet" :columns="$columns" />
                    <button type="submit" class="btn-primary w-full sm:w-auto" data-busy-text="{{ __('common.working') }}">
                        {{ __('labels.print_sheet') }}
                    </button>
                    <p class="mt-2 text-sm text-grey-600">{{ __('labels.print_sheet_help') }}</p>
                </form>
            @endif
        </section>

        <section class="rounded border border-grey-200 bg-white p-4" aria-labelledby="blank-heading">
            <h2 id="blank-heading" class="text-lg font-bold tracking-tight">{{ __('labels.blank_heading') }}</h2>
            <p class="mb-4 text-sm text-grey-600">{{ __('labels.blank_help') }}</p>

            <form method="POST" action="{{ route('admin.labels.blank') }}">
                @csrf

                <x-field :label="__('labels.blank_count')" name="count" required>
                    <x-input name="count" type="number" min="1" max="480" inputmode="numeric" :value="old('count', $perSheet)" required class="max-w-32" />
                </x-field>

                <x-sheet-position :per-sheet="$perSheet" :columns="$columns" />

                <button type="submit" class="{{ $queueFirst ? 'btn-secondary' : 'btn-primary' }} w-full sm:w-auto" data-busy-text="{{ __('common.working') }}">
                    {{ __('labels.generate') }}
                </button>
            </form>
        </section>
    </div>

    <section class="mt-4 rounded border border-grey-200 bg-white p-4" aria-labelledby="reprint-heading">
        <h2 id="reprint-heading" class="text-lg font-bold tracking-tight">{{ __('labels.reprint_heading') }}</h2>
        <p class="mb-4 text-sm text-grey-600">{{ __('labels.reprint_help') }}</p>

        <form method="GET" action="{{ route('admin.labels.index') }}" class="mb-4 max-w-md" role="search">
            <label for="q" class="sr-only">{{ __('assets.search_placeholder') }}</label>
            <x-input name="q" id="q" type="search" :value="request('q')" :placeholder="__('assets.search_placeholder')" />
        </form>

        @if ($reprintable->isNotEmpty())
            <form method="POST" action="{{ route('admin.labels.reprint') }}">
                @csrf

                <ul class="mb-4 max-h-80 divide-y divide-grey-200 overflow-y-auto rounded border border-grey-200">
                    @foreach ($reprintable as $asset)
                        <li>
                            <label class="flex min-h-tap cursor-pointer items-center gap-3 px-3 hover:bg-grey-50">
                                <input type="checkbox" name="asset_ids[]" value="{{ $asset->id }}" class="size-5 rounded border-grey-300">
                                <span class="min-w-0 flex-1 truncate text-sm">{{ $asset->displayName() }}</span>
                                <span class="font-mono text-xs text-grey-700">{{ $asset->asset_tag }}</span>
                            </label>
                        </li>
                    @endforeach
                </ul>

                <x-sheet-position :per-sheet="$perSheet" :columns="$columns" />

                <button type="submit" class="btn-secondary w-full sm:w-auto" data-busy-text="{{ __('common.working') }}">
                    {{ __('labels.reprint_heading') }}
                </button>
            </form>
        @else
            <p class="text-sm text-grey-600">{{ __('assets.no_matches') }}</p>
        @endif
    </section>
</x-layouts.admin>
