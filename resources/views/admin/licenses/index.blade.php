<x-layouts.admin :title="__('licenses.title')" :back="route('admin.manage')" :back-label="__('manage.title')">
    <x-flash />

    <div class="mb-5">
        <a href="{{ route('admin.licenses.create') }}" class="btn-primary">
            {{ __('licenses.add') }}
        </a>
    </div>

    <form method="GET" action="{{ route('admin.licenses.index') }}" class="mb-4 max-w-md" role="search">
        <label for="q" class="sr-only">{{ __('licenses.search_placeholder') }}</label>
        <x-input name="q" id="q" type="search" :value="request('q')"
                 :placeholder="__('licenses.search_placeholder')" autofocus />
    </form>

    @if ($licenses->isEmpty())
        <x-empty-state :message="request('q') ? __('licenses.no_matches') : __('licenses.none_yet')">
            @unless (request('q'))
                <a href="{{ route('admin.licenses.create') }}" class="link-action">{{ __('licenses.add') }}</a>
            @endunless
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
                       class="flex flex-wrap items-center gap-x-5 gap-y-1.5 px-3 py-3 hover:bg-grey-900/[0.03] sm:px-4">
                        <span class="min-w-0 flex-1 basis-56">
                            <span class="block font-semibold">{{ $license->name }}</span>
                            <span class="block text-sm text-grey-700">
                                {{ collect([
                                    $license->vendor,
                                    $license->type->label(),
                                    $license->seats_total !== null ? __('licenses.seats', ['used' => $license->seats_used ?? 0, 'total' => $license->seats_total]) : null,
                                    $license->auto_renew ? __('licenses.auto_renew') : __('licenses.manual_renew'),
                                ])->filter()->join(' · ') }}
                            </span>
                        </span>

                        <span class="text-sm whitespace-nowrap tabular-nums">
                            @if ($license->neverExpires())
                                <span class="text-grey-700">{{ __('licenses.never_expires') }}</span>
                            @elseif ($expired)
                                <span class="inline-flex items-center rounded bg-status-broken px-2 py-0.5 text-xs font-semibold text-white">
                                    {{ __('licenses.expired_on', ['date' => $license->expires_at->format('j M Y')]) }}
                                </span>
                            @elseif ($soon)
                                <span class="inline-flex items-center rounded bg-status-attention px-2 py-0.5 text-xs font-semibold text-white">
                                    {{ __('licenses.expires_on', ['date' => $license->expires_at->format('j M Y')]) }}
                                </span>
                            @else
                                <span class="text-grey-700">{{ __('licenses.expires_on', ['date' => $license->expires_at->format('j M Y')]) }}</span>
                            @endif
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.admin>
