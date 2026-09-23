<x-layouts.admin :title="__('assets.title')">
    <x-flash />

    <div class="mb-5 flex flex-wrap items-center gap-x-5 gap-y-2">
        <a href="{{ route('admin.assets.create') }}" class="btn-primary">
            {{ __('assets.add') }}
        </a>

        <a href="{{ route('admin.intake.start') }}" class="link-action text-sm">
            {{ __('intake.title') }}
        </a>

        {{-- The queue count lives in the section header, so labels waiting
             to be printed are never forgotten (spec section 6.3). --}}
        <a href="{{ route('admin.labels.index') }}" class="link-action gap-2 text-sm">
            {{ __('labels.title') }}
            @if ($queueCount > 0)
                <span class="rounded bg-grey-100 px-1.5 py-0.5 text-xs font-semibold text-grey-800 no-underline tabular-nums">
                    {{ trans_choice('labels.queue_count', $queueCount, ['count' => $queueCount]) }}
                </span>
            @endif
        </a>
    </div>

    @php $filtering = request()->hasAny(['q', 'room', 'category', 'status']); @endphp

    <form method="GET" action="{{ route('admin.assets.index') }}" class="mb-5 space-y-2" role="search">
        <label for="q" class="sr-only">{{ __('assets.search_placeholder') }}</label>
        <x-input name="q" id="q" type="search" :value="request('q')"
                 :placeholder="__('assets.search_placeholder')" autofocus />

        <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center">
            <label for="filter-room" class="sr-only">{{ __('assets.labels.room') }}</label>
            <x-select name="room" id="filter-room" :options="$rooms->pluck('name', 'id')" :selected="request('room')"
                      :placeholder="__('assets.filters.room')" class="sm:w-auto" />

            <label for="filter-category" class="sr-only">{{ __('assets.labels.category') }}</label>
            <x-select name="category" id="filter-category" :options="$categories->pluck('name', 'id')" :selected="request('category')"
                      :placeholder="__('assets.filters.category')" class="sm:w-auto" />

            <label for="filter-status" class="sr-only">{{ __('assets.labels.status') }}</label>
            <x-select name="status" id="filter-status" :options="\App\Enums\AssetStatus::options()" :selected="request('status')"
                      :placeholder="__('assets.filters.status')" class="sm:w-auto" />

            <button type="submit" class="btn-secondary text-sm">
                {{ __('common.search') }}
            </button>

            @if ($filtering)
                <a href="{{ route('admin.assets.index') }}" class="link-action col-span-2 text-sm text-grey-600 sm:col-span-1">
                    {{ __('activity.clear') }}
                </a>
            @endif
        </div>
    </form>

    @if ($assets->isEmpty())
        <x-empty-state :message="$filtering ? __('assets.no_matches') : __('assets.none_yet')">
            @unless ($filtering)
                <a href="{{ route('admin.assets.create') }}" class="link-action">{{ __('assets.add') }}</a>
                <a href="{{ route('admin.labels.index') }}" class="link-action">{{ __('labels.generate') }}</a>
            @endunless
        </x-empty-state>
    @else
        <p class="mb-2 text-sm text-grey-600 tabular-nums">{{ trans_choice('assets.count', $assets->total(), ['count' => $assets->total()]) }}</p>

        <ul class="divide-y divide-grey-200 rounded border border-grey-200 bg-white">
            @foreach ($assets as $asset)
                <li>
                    <a href="{{ route('admin.assets.show', $asset) }}"
                       class="flex min-h-tap items-center gap-3 px-3 py-2.5 hover:bg-grey-50 sm:px-4">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold">{{ $asset->displayName() }}</span>
                            <span class="mt-0.5 flex flex-wrap items-baseline gap-x-3 text-sm text-grey-600">
                                <span class="font-mono text-xs text-grey-700">{{ $asset->asset_tag }}</span>
                                @if ($asset->room)
                                    <span>{{ $asset->room->name }}</span>
                                @endif
                                @php $makeModel = collect([$asset->make, $asset->model])->filter()->join(' '); @endphp
                                {{-- Only when it adds something the name does not already say. --}}
                                @if ($makeModel !== '' && ! str_contains(mb_strtolower($asset->displayName()), mb_strtolower($makeModel)))
                                    <span class="truncate">{{ $makeModel }}</span>
                                @endif
                            </span>
                        </span>

                        {{-- Status always; condition only when it needs someone. --}}
                        <span class="flex shrink-0 flex-col items-end gap-1">
                            <x-status-badge :status="$asset->status" />
                            @if ($asset->condition && $asset->condition !== \App\Enums\AssetCondition::Good)
                                <x-status-badge :status="$asset->condition" />
                            @endif
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="mt-4">{{ $assets->links() }}</div>
    @endif
</x-layouts.admin>
