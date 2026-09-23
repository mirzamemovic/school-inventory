@foreach ($events as $event)
    <li class="flex flex-wrap items-baseline gap-x-2 gap-y-1 p-3 text-sm"
        @if ($loop->last && $events->hasMorePages())
            {{-- Infinite scroll: ask for the next page when this row appears. --}}
            hx-get="{{ $events->nextPageUrl() }}"
            hx-trigger="revealed"
            hx-swap="afterend"
            hx-target="this"
        @endif>
        <span class="{{ $event->isSelfReported() ? 'text-grey-600' : 'font-medium text-grey-900' }}">
            {{ \App\Support\EventSentence::for($event) }}
        </span>

        @if ($event->isSelfReported())
            <span class="rounded bg-grey-100 px-1.5 py-0.5 text-xs text-grey-500">{{ __('activity.self_reported') }}</span>
        @endif

        @if ($event->device_token && $event->isSelfReported())
            <form method="POST" action="{{ route('admin.activity.block') }}">
                @csrf
                <input type="hidden" name="device_token" value="{{ $event->device_token }}">
                <input type="hidden" name="note" value="{{ $event->actor_name }}">
                <button type="submit" class="text-xs text-grey-500 underline hover:text-status-broken">
                    {{ __('activity.block_device') }}
                </button>
            </form>
        @endif

        <time datetime="{{ $event->created_at->toIso8601String() }}"
              class="ml-auto text-xs whitespace-nowrap text-grey-500 tabular-nums">
            {{ $event->created_at->format('j M Y, H:i') }}
        </time>
    </li>
@endforeach
