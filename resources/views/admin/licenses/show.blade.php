@php
    $expired = $license->isExpired();
    $soon = $license->isExpiringSoon($windowDays);
@endphp
<x-layouts.admin :title="$license->name">
    <x-flash />

    {{-- Same countdown, different meaning depending on auto_renew: a manual
         renewal running out stops the service, an automatic one takes money
         out of the account (spec section 5). --}}
    @if ($expired)
        <p role="status" class="mb-4 rounded border border-status-broken-soft bg-status-broken-soft px-3 py-3 font-medium text-status-broken">
            {{ $license->auto_renew ? __('licenses.warning.expired_auto') : __('licenses.warning.expired_manual') }}
        </p>
    @elseif ($soon)
        <p role="status" class="mb-4 rounded border border-status-attention-soft bg-status-attention-soft px-3 py-3 font-medium text-status-attention">
            {{ __($license->warningKey()) }}
        </p>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="rounded border border-grey-200 bg-white p-4">
                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-grey-500">{{ __('licenses.expiry') }}</dt>
                        <dd class="mt-0.5 tabular-nums">
                            @if ($license->neverExpires())
                                {{-- No badge. Never expiring is not a warning. --}}
                                <span class="text-grey-600">{{ __('licenses.never_expires') }}</span>
                            @else
                                <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold
                                    {{ $expired ? 'bg-status-broken-soft text-status-broken' : ($soon ? 'bg-status-attention-soft text-status-attention' : 'bg-status-ok-soft text-status-ok') }}">
                                    {{ $expired
                                        ? __('licenses.expired_on', ['date' => $license->expires_at->format('j F Y')])
                                        : __('licenses.expires_on', ['date' => $license->expires_at->format('j F Y')]) }}
                                </span>
                            @endif
                        </dd>
                    </div>

                    @foreach ([
                        'vendor' => $license->vendor,
                        'type' => $license->type?->label(),
                        'licence_model' => $license->licence_model?->label(),
                        'purchase_date' => $license->purchase_date?->format('j F Y'),
                        'renewal_cost' => $license->renewal_cost,
                        'account_reference' => $license->account_reference,
                    ] as $key => $value)
                        @if (filled($value))
                            <div>
                                <dt class="text-xs text-grey-500">{{ __('licenses.labels.'.$key) }}</dt>
                                <dd class="tabular-nums">{{ $value }}</dd>
                            </div>
                        @endif
                    @endforeach

                    <div>
                        <dt class="text-xs text-grey-500">{{ __('licenses.labels.auto_renew') }}</dt>
                        <dd>{{ $license->auto_renew ? __('licenses.auto_renew') : __('licenses.manual_renew') }}</dd>
                    </div>

                    @if ($license->seats_total !== null)
                        <div>
                            <dt class="text-xs text-grey-500">{{ __('licenses.labels.seats_used') }}</dt>
                            <dd class="tabular-nums">
                                {{ __('licenses.seats', ['used' => $license->seats_used ?? 0, 'total' => $license->seats_total]) }}
                            </dd>
                        </div>
                    @endif

                    @if ($license->asset)
                        <div>
                            <dt class="text-xs text-grey-500">{{ __('licenses.tied_to') }}</dt>
                            <dd>
                                <a href="{{ route('admin.assets.show', $license->asset) }}" class="text-navy underline">
                                    {{ $license->asset->displayName() }}
                                </a>
                                <span class="font-mono text-xs text-grey-500">{{ $license->asset->asset_tag }}</span>
                            </dd>
                        </div>
                    @endif
                </dl>

                @if ($license->notes)
                    <p class="mt-4 border-t border-grey-200 pt-4 text-sm whitespace-pre-line text-grey-700">{{ $license->notes }}</p>
                @endif
            </div>

            {{-- Renewal history comes out of the single audit log, not a
                 renewals table (spec section 5). --}}
            <h2 class="mt-8 mb-3 text-lg font-bold tracking-tight">{{ __('licenses.history') }}</h2>

            @if ($license->events->isEmpty())
                <p class="text-sm text-grey-500">{{ __('licenses.history_empty') }}</p>
            @else
                <x-timeline :events="$license->events" />
            @endif
        </div>

        <div class="space-y-4">
            <div class="rounded border border-grey-200 bg-white p-4">
                <a href="{{ route('admin.licenses.edit', $license) }}"
                   class="inline-flex min-h-tap w-full items-center justify-center rounded bg-navy px-4 font-semibold text-white hover:bg-navy-deep">
                    {{ __('common.edit') }}
                </a>
            </div>

            <details class="rounded border border-grey-200 bg-white" @if ($expired || $soon) open @endif>
                <summary class="flex min-h-tap cursor-pointer items-center px-4 text-sm font-semibold">
                    {{ __('licenses.renew_heading') }}
                </summary>
                <form method="POST" action="{{ route('admin.licenses.renew', $license) }}" class="border-t border-grey-200 p-4">
                    @csrf
                    <p class="mb-3 text-xs text-grey-600">{{ __('licenses.renew_help') }}</p>

                    <label for="expires_at" class="mb-1 block text-sm font-medium text-grey-700">{{ __('licenses.new_expiry') }}</label>
                    <x-input name="expires_at" type="date" class="mb-3" required />

                    <label for="renewal_cost" class="mb-1 block text-sm font-medium text-grey-700">{{ __('licenses.updated_cost') }}</label>
                    <x-input name="renewal_cost" type="number" step="0.01" min="0" class="mb-3" />

                    <button type="submit" class="min-h-tap w-full rounded border border-grey-300 text-sm font-medium hover:bg-grey-50">
                        {{ __('licenses.renew_action') }}
                    </button>
                </form>
            </details>

            <form method="POST" action="{{ route('admin.licenses.destroy', $license) }}"
                  onsubmit="return confirm('{{ __('licenses.delete_confirm') }}')">
                @csrf
                @method('DELETE')
                <button type="submit" class="min-h-tap w-full text-sm text-grey-600 underline hover:text-status-broken">
                    {{ __('licenses.delete') }}
                </button>
            </form>
        </div>
    </div>
</x-layouts.admin>
