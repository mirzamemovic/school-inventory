<x-layouts.admin :title="__('rooms.title')" :back="route('admin.manage')" :back-label="__('manage.title')">
    <x-flash />

    <div class="mb-5">
        <a href="{{ route('admin.rooms.create') }}" class="btn-primary">
            {{ __('rooms.add') }}
        </a>
    </div>

    <form method="GET" action="{{ route('admin.rooms.index') }}" class="mb-4 max-w-md" role="search">
        <label for="q" class="sr-only">{{ __('rooms.search_placeholder') }}</label>
        <x-input name="q" id="q" type="search" :value="request('q')" :placeholder="__('rooms.search_placeholder')" autofocus />
    </form>

    @if ($rooms->isEmpty())
        <x-empty-state :message="request('q') ? __('assets.no_matches') : __('rooms.none_yet')">
            @unless (request('q'))
                <a href="{{ route('admin.rooms.create') }}" class="link-action">{{ __('rooms.add') }}</a>
            @endunless
        </x-empty-state>
    @else
        <ul class="divide-y divide-grey-200 rounded border border-grey-200 bg-white">
            @foreach ($rooms as $room)
                <li class="flex flex-wrap items-center gap-x-5 gap-y-1 px-3 py-3 sm:px-4">
                    <span class="min-w-0 flex-1 basis-48">
                        <a href="{{ route('admin.rooms.show', $room) }}" class="link block text-base font-semibold text-grey-900">{{ $room->name }}</a>
                        <span class="block text-sm text-grey-600">
                            {{ collect([$room->type->label(), $room->floor])->filter()->join(' · ') }}
                        </span>
                    </span>

                    <span class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-grey-700 tabular-nums">
                        <span>{{ trans_choice('rooms.asset_count', $room->assets_count, ['count' => $room->assets_count]) }}</span>
                        <span>{{ trans_choice('rooms.furniture_count', $room->contents_count, ['count' => $room->contents_count]) }}</span>
                        <span class="text-grey-600">
                            {{ $room->last_counted_at
                                ? __('rooms.last_counted', ['date' => $room->last_counted_at->format('j M Y')])
                                : __('rooms.never_counted') }}
                        </span>
                    </span>

                    <a href="{{ route('admin.rooms.edit', $room) }}" class="link-action text-sm">
                        {{ __('common.edit') }}
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.admin>
