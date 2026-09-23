@props(['events'])
{{--
    Public actions are self-declared, never verified, so they are styled more
    lightly than an authenticated admin action. The log must never look more
    certain than it is (spec section 5).
--}}
@if ($events->isEmpty())
    <p class="text-sm text-grey-500">{{ __('assets.timeline_empty') }}</p>
@else
    <ol class="divide-y divide-grey-200 rounded border border-grey-200 bg-white">
        @foreach ($events as $event)
            <li class="flex flex-wrap items-baseline gap-x-2 gap-y-1 p-3 text-sm">
                <span class="{{ $event->isSelfReported() ? 'text-grey-600' : 'font-medium text-grey-900' }}">
                    {{ \App\Support\EventSentence::for($event) }}
                </span>

                @if ($event->isSelfReported())
                    <span class="rounded bg-grey-100 px-1.5 py-0.5 text-xs text-grey-500">
                        {{ __('activity.self_reported') }}
                    </span>
                @endif

                <time datetime="{{ $event->created_at->toIso8601String() }}"
                      class="ml-auto text-xs whitespace-nowrap text-grey-500 tabular-nums">
                    {{ __('activity.ago', ['time' => $event->created_at->diffForHumans(short: true, syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE)]) }}
                </time>
            </li>
        @endforeach
    </ol>
@endif
