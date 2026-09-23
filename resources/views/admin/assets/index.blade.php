<x-layouts.admin :title="__('assets.title')">
    <x-flash />

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.assets.create') }}"
           class="inline-flex min-h-tap items-center rounded bg-navy px-4 font-semibold text-white hover:bg-navy-deep">
            {{ __('assets.add') }}
        </a>

        <a href="{{ route('admin.intake.start') }}" class="text-sm font-medium text-navy underline">
            {{ __('intake.title') }}
        </a>

        <a href="{{ route('admin.labels.index') }}" class="text-sm font-medium text-navy underline">
            {{ trans_choice('labels.queue_count', $queueCount, ['count' => $queueCount]) }}
        </a>
    </div>

    <form method="GET" action="{{ route('admin.assets.index') }}" class="mb-4 space-y-3">
        <label for="q" class="sr-only">{{ __('assets.search_placeholder') }}</label>
        <x-input name="q" id="q" type="search" :value="request('q')"
                 :placeholder="__('assets.search_placeholder')" autofocus />

        <div class="flex flex-wrap gap-2">
            <x-select name="room" :options="$rooms->pluck('name', 'id')" :selected="request('room')"
                      :placeholder="__('assets.labels.room')" class="w-auto" />
            <x-select name="category" :options="$categories->pluck('name', 'id')" :selected="request('category')"
                      :placeholder="__('assets.labels.category')" class="w-auto" />
            <x-select name="status" :options="\App\Enums\AssetStatus::options()" :selected="request('status')"
                      :placeholder="__('assets.labels.status')" class="w-auto" />
            <button type="submit" class="min-h-tap rounded border border-grey-300 bg-white px-4 text-sm font-medium">
                {{ __('common.search') }}
            </button>
        </div>
    </form>

    @if ($assets->isEmpty())
        <x-empty-state :message="request()->hasAny(['q', 'room', 'category', 'status']) ? __('assets.no_matches') : __('assets.none_yet')" />
    @else
        <p class="mb-2 text-sm text-grey-600">{{ trans_choice('assets.count', $assets->total(), ['count' => $assets->total()]) }}</p>

        <ul class="divide-y divide-grey-200 rounded border border-grey-200 bg-white">
            @foreach ($assets as $asset)
                <li>
                    <a href="{{ route('admin.assets.show', $asset) }}"
                       class="flex min-h-tap items-center gap-3 p-3 hover:bg-grey-50">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium">{{ $asset->displayName() }}</span>
                            <span class="block truncate text-sm text-grey-500">
                                {{ collect([$asset->make, $asset->model, $asset->room?->name])->filter()->join(' · ') }}
                            </span>
                        </span>
                        <span class="font-mono text-xs text-grey-500">{{ $asset->asset_tag }}</span>
                        <x-status-badge :status="$asset->status" />
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="mt-4">{{ $assets->links() }}</div>
    @endif
</x-layouts.admin>
