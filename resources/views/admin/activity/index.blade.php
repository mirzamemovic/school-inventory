<x-layouts.admin :title="__('activity.title')">
    <x-flash />

    <form method="GET" action="{{ route('admin.activity.index') }}" class="mb-4 space-y-3">
        <label for="q" class="sr-only">{{ __('activity.search_placeholder') }}</label>
        <x-input name="q" id="q" type="search" :value="request('q')" :placeholder="__('activity.search_placeholder')" autofocus />

        <div class="flex flex-wrap items-end gap-2">
            <span>
                <label for="action" class="mb-1 block text-xs text-grey-600">{{ __('activity.filter_action') }}</label>
                <x-select name="action" id="action" :selected="request('action')" :placeholder="__('common.none')"
                          :options="collect($actions)->mapWithKeys(fn ($a) => [$a => __('activity.sentence.'.$a, ['actor' => '', 'from' => '…', 'to' => '…'])])"
                          class="w-auto" />
            </span>

            <span>
                <label for="who" class="mb-1 block text-xs text-grey-600">{{ __('activity.filter_who') }}</label>
                <x-input name="who" id="who" :value="request('who')" class="w-auto" />
            </span>

            <span>
                <label for="from" class="mb-1 block text-xs text-grey-600">{{ __('activity.filter_from') }}</label>
                <x-input name="from" id="from" type="date" :value="request('from')" class="w-auto" />
            </span>

            <span>
                <label for="to" class="mb-1 block text-xs text-grey-600">{{ __('activity.filter_to') }}</label>
                <x-input name="to" id="to" type="date" :value="request('to')" class="w-auto" />
            </span>

            <button type="submit" class="min-h-tap rounded border border-grey-300 bg-white px-4 text-sm font-medium">
                {{ __('activity.filter') }}
            </button>

            <a href="{{ route('admin.activity.index') }}" class="min-h-tap py-2 text-sm text-grey-600 underline">
                {{ __('activity.clear') }}
            </a>

            <a href="{{ route('admin.activity.export', request()->query()) }}"
               class="min-h-tap py-2 text-sm font-medium text-navy underline">
                {{ __('activity.export') }}
            </a>
        </div>
    </form>

    @if ($events->isEmpty())
        <x-empty-state :message="request()->hasAny(['q', 'action', 'who', 'from', 'to']) ? __('activity.no_matches') : __('activity.empty')" />
    @else
        <ul class="divide-y divide-grey-200 rounded border border-grey-200 bg-white">
            @include('admin.activity._rows')
        </ul>
    @endif

    @php $blocked = \App\Models\BlockedDevice::orderByDesc('created_at')->get(); @endphp

    <h2 class="mt-10 mb-3 text-lg font-bold tracking-tight">{{ __('activity.blocked_devices') }}</h2>

    @if ($blocked->isEmpty())
        <p class="text-sm text-grey-500">{{ __('activity.no_blocked_devices') }}</p>
    @else
        <ul class="divide-y divide-grey-200 rounded border border-grey-200 bg-white">
            @foreach ($blocked as $device)
                <li class="flex min-h-tap items-center gap-3 p-3 text-sm">
                    <span class="min-w-0 flex-1 truncate">{{ $device->note ?: $device->device_token }}</span>
                    <span class="text-xs text-grey-500 tabular-nums">{{ __('activity.blocked_on', ['date' => $device->created_at->format('j M Y')]) }}</span>
                    <form method="POST" action="{{ route('admin.activity.unblock', $device) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="min-h-tap text-sm text-navy underline">{{ __('activity.unblock') }}</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.admin>
