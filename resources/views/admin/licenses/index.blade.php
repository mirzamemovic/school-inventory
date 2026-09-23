<x-layouts.admin :title="__('licenses.title')">
    <x-flash />

    <div class="mb-4">
        <a href="{{ route('admin.licenses.create') }}"
           class="inline-flex min-h-tap items-center rounded bg-navy px-4 font-semibold text-white hover:bg-navy-deep">
            {{ __('licenses.add') }}
        </a>
    </div>

    <form method="GET" action="{{ route('admin.licenses.index') }}" class="mb-4 max-w-md">
        <label for="q" class="sr-only">{{ __('licenses.search_placeholder') }}</label>
        <x-input name="q" id="q" type="search" :value="request('q')"
                 :placeholder="__('licenses.search_placeholder')" autofocus />
    </form>

    @if ($licenses->isEmpty())
        <x-empty-state :message="request('q') ? __('licenses.no_matches') : __('licenses.none_yet')">
            <a href="{{ route('admin.licenses.create') }}" class="text-sm text-navy underline">{{ __('licenses.add') }}</a>
        </x-empty-state>
    @else
        {{-- Soonest first, perpetual last. A licence with no expiry date carries
             no badge at all — it is not overdue, it simply never runs out. --}}
        <ul class="divide-y divide-grey-200 rounded border border-grey-200 bg-white">
            @foreach ($licenses as $license)
                @php
                    $expired = $license->isExpired();
                    $soon = $license->isExpiringSoon($windowDays);
                @endphp
                <li class="{{ $expired ? 'bg-status-broken-soft' : ($soon ? 'bg-status-attention-soft' : '') }}">
                    <a href="{{ route('admin.licenses.show', $license) }}"
                       class="flex min-h-tap flex-wrap items-center gap-x-4 gap-y-1 p-3 hover:bg-grey-50/50">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium">{{ $license->name }}</span>
                            <span class="block truncate text-sm text-grey-500">
                                {{ collect([$license->vendor, $license->type->label()])->filter()->join(' · ') }}
                            </span>
                        </span>

                        <span class="text-sm text-grey-600 tabular-nums">
                            @if ($license->seats_total !== null)
                                {{ __('licenses.seats', ['used' => $license->seats_used ?? 0, 'total' => $license->seats_total]) }}
                            @else
                                <span class="text-grey-400">{{ __('licenses.seats_none') }}</span>
                            @endif
                        </span>

                        <span class="text-xs whitespace-nowrap text-grey-500">
                            {{ $license->auto_renew ? __('licenses.auto_renew') : __('licenses.manual_renew') }}
                        </span>

                        <span class="min-w-40 text-right text-sm tabular-nums">
                            @if ($license->neverExpires())
                                <span class="text-grey-500">{{ __('licenses.never_expires') }}</span>
                            @elseif ($expired)
                                <span class="inline-flex items-center rounded bg-status-broken-soft px-2 py-0.5 text-xs font-semibold text-status-broken">
                                    {{ __('licenses.expired_on', ['date' => $license->expires_at->format('j M Y')]) }}
                                </span>
                            @elseif ($soon)
                                <span class="inline-flex items-center rounded bg-status-attention-soft px-2 py-0.5 text-xs font-semibold text-status-attention">
                                    {{ __('licenses.expires_on', ['date' => $license->expires_at->format('j M Y')]) }}
                                </span>
                            @else
                                <span class="text-grey-600">{{ __('licenses.expires_on', ['date' => $license->expires_at->format('j M Y')]) }}</span>
                            @endif
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.admin>
