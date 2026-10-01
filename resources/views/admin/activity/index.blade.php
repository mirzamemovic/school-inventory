<x-layouts.admin :title="__('activity.title')" :back="route('admin.manage')" :back-label="__('manage.title')">
    <x-flash />

    @php $filtering = request()->hasAny(['q', 'action', 'who', 'from', 'to']); @endphp

    <form method="GET" action="{{ route('admin.activity.index') }}" class="mb-5 space-y-3" role="search">
        <label for="q" class="sr-only">{{ __('activity.search_placeholder') }}</label>
        <x-input name="q" id="q" type="search" :value="request('q')" :placeholder="__('activity.search_placeholder')" autofocus />

        <div class="grid grid-cols-2 gap-x-3 gap-y-2 sm:flex sm:flex-wrap sm:items-end">
            <div class="col-span-2 sm:col-span-1">
                <label for="action" class="mb-1 block text-sm font-semibold text-grey-800">{{ __('activity.filter_action') }}</label>
                <x-select name="action" id="action" :selected="request('action')" :placeholder="__('common.none')"
                          :options="collect($actions)->mapWithKeys(fn ($a) => [$a => __('activity.sentence.'.$a, ['actor' => '', 'from' => '…', 'to' => '…', 'account' => '…'])])"
                          class="sm:w-auto" />
            </div>

            <div class="col-span-2 sm:col-span-1">
                <label for="who" class="mb-1 block text-sm font-semibold text-grey-800">{{ __('activity.filter_who') }}</label>
                <x-input name="who" id="who" :value="request('who')" class="sm:w-48" />
            </div>

            <div>
                <label for="from" class="mb-1 block text-sm font-semibold text-grey-800">{{ __('activity.filter_from') }}</label>
                <x-input name="from" id="from" type="date" :value="request('from')" class="sm:w-auto" />
            </div>

            <div>
                <label for="to" class="mb-1 block text-sm font-semibold text-grey-800">{{ __('activity.filter_to') }}</label>
                <x-input name="to" id="to" type="date" :value="request('to')" class="sm:w-auto" />
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
            <button type="submit" class="btn-secondary">
                {{ __('activity.filter') }}
            </button>

            @if ($filtering)
                <a href="{{ route('admin.activity.index') }}" class="link-action text-sm text-grey-700">
                    {{ __('activity.clear') }}
                </a>
            @endif

            <a href="{{ route('admin.activity.export', request()->query()) }}" class="link-action text-sm sm:ml-auto">
                {{ __('activity.export') }}
            </a>
        </div>
    </form>

    @if ($events->isEmpty())
        <x-empty-state :message="$filtering ? __('activity.no_matches') : __('activity.empty')" />
    @else
        <ul class="divide-y divide-grey-200 rounded border border-grey-200 bg-white">
            @include('admin.activity._rows')
        </ul>

        {{-- Shown while the next page is on its way in. --}}
        <p id="activity-loading" class="htmx-indicator mt-3 text-center text-sm text-grey-600">{{ __('common.working') }}</p>
    @endif

    @php $blocked = \App\Models\BlockedDevice::orderByDesc('created_at')->get(); @endphp

    <h2 class="mt-10 mb-3 text-lg font-bold tracking-tight">{{ __('activity.blocked_devices') }}</h2>

    @if ($blocked->isEmpty())
        <p class="text-sm text-grey-600">{{ __('activity.no_blocked_devices') }}</p>
    @else
        <ul class="divide-y divide-grey-200 rounded border border-grey-200 bg-white">
            @foreach ($blocked as $device)
                <li class="flex min-h-tap flex-wrap items-center gap-x-3 px-3 py-1 text-sm sm:px-4">
                    <span class="min-w-0 flex-1 truncate">{{ $device->note ?: $device->device_token }}</span>
                    <span class="text-grey-600 tabular-nums">{{ __('activity.blocked_on', ['date' => $device->created_at->format('j M Y')]) }}</span>
                    <form method="POST" action="{{ route('admin.activity.unblock', $device) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="link-action">{{ __('activity.unblock') }}</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.admin>
