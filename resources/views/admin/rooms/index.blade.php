<x-layouts.admin :title="__('rooms.title')">
    <x-flash />

    <div class="mb-4">
        <a href="{{ route('admin.rooms.create') }}"
           class="inline-flex min-h-tap items-center rounded bg-navy px-4 font-semibold text-white hover:bg-navy-deep">
            {{ __('rooms.add') }}
        </a>
    </div>

    <form method="GET" action="{{ route('admin.rooms.index') }}" class="mb-4">
        <label for="q" class="sr-only">{{ __('rooms.search_placeholder') }}</label>
        <x-input name="q" id="q" type="search" :value="request('q')" :placeholder="__('rooms.search_placeholder')" autofocus />
    </form>

    @if ($rooms->isEmpty())
        <x-empty-state :message="__('rooms.none_yet')" />
    @else
        <ul class="divide-y divide-grey-200 rounded border border-grey-200 bg-white">
            @foreach ($rooms as $room)
                <li class="flex min-h-tap flex-wrap items-center gap-x-3 gap-y-1 p-3">
                    <span class="min-w-0 flex-1">
                        <a href="{{ route('admin.rooms.show', $room) }}" class="block font-medium text-navy underline">{{ $room->name }}</a>
                        <span class="block text-sm text-grey-500">
                            {{ collect([$room->type->label(), $room->floor])->filter()->join(' · ') }}
                        </span>
                    </span>

                    <span class="text-sm text-grey-600 tabular-nums">
                        {{ trans_choice('rooms.asset_count', $room->assets_count, ['count' => $room->assets_count]) }}
                    </span>

                    <span class="text-sm text-grey-600 tabular-nums">
                        {{ trans_choice('rooms.furniture_count', $room->contents_count, ['count' => $room->contents_count]) }}
                    </span>

                    <span class="text-xs text-grey-500 tabular-nums">
                        {{ $room->last_counted_at
                            ? __('rooms.last_counted', ['date' => $room->last_counted_at->format('j M Y')])
                            : __('rooms.never_counted') }}
                    </span>

                    <a href="{{ route('admin.rooms.edit', $room) }}" class="min-h-tap text-sm text-navy underline">
                        {{ __('common.edit') }}
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.admin>
