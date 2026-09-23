@foreach ($events as $event)
    <li class="flex flex-wrap items-baseline gap-x-2 gap-y-1 px-3 py-2.5 text-sm sm:px-4"
        @if ($loop->last && $events->hasMorePages())
            {{-- Infinite scroll: ask for the next page when this row appears. --}}
            hx-get="{{ $events->nextPageUrl() }}"
            hx-trigger="revealed"
            hx-swap="afterend"
            hx-target="this"
            hx-indicator="#activity-loading"
        @endif>
        {{-- Self-declared actions are lighter than signed-in ones: the log must
             never look more certain than it is (spec section 5). --}}
        <span class="min-w-0 flex-1 basis-64 {{ $event->isSelfReported() ? 'text-grey-700' : 'font-medium text-grey-900' }}">
            {{ \App\Support\EventSentence::for($event) }}
            @if ($event->isSelfReported())
                <span class="ml-1 rounded bg-grey-100 px-1.5 py-0.5 text-xs font-normal whitespace-nowrap text-grey-700">{{ __('activity.self_reported') }}</span>
            @endif
        </span>

        <span class="flex items-center gap-x-3">
            @if ($event->device_token && $event->isSelfReported())
                <form method="POST" action="{{ route('admin.activity.block') }}">
                    @csrf
                    <input type="hidden" name="device_token" value="{{ $event->device_token }}">
                    <input type="hidden" name="note" value="{{ $event->actor_name }}">
                    <button type="submit" class="inline-flex min-h-tap items-center text-xs text-grey-700 underline decoration-grey-400 underline-offset-2 hover:text-status-broken sm:min-h-0">
                        {{ __('activity.block_device') }}
                    </button>
                </form>
            @endif

            <time datetime="{{ $event->created_at->toIso8601String() }}"
                  class="text-xs whitespace-nowrap text-grey-600 tabular-nums">
                {{ $event->created_at->format('j M Y, H:i') }}
            </time>
        </span>
    </li>
@endforeach
