@php
    $expired = $license->isExpired();
    $soon = $license->isExpiringSoon($windowDays);
    $card = 'rounded border border-grey-200 bg-white';
@endphp
<x-layouts.admin :title="$license->name" :back="route('admin.licenses.index')" :back-label="__('licenses.title')">
    <x-slot:subtitle>
        <p class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-grey-600 tabular-nums">
            @if ($license->neverExpires())
                {{-- No badge. Never expiring is not a warning. --}}
                <span class="text-grey-700">{{ __('licenses.never_expires') }}</span>
            @else
                <span class="inline-flex items-center rounded px-2 py-0.5 text-sm font-semibold
                    {{ $expired ? 'bg-status-broken-soft text-status-broken' : ($soon ? 'bg-status-attention-soft text-status-attention' : 'bg-status-ok-soft text-status-ok') }}">
                    {{ $expired
                        ? __('licenses.expired_on', ['date' => $license->expires_at->format('j F Y')])
                        : __('licenses.expires_on', ['date' => $license->expires_at->format('j F Y')]) }}
                </span>
            @endif
            <span>{{ $license->auto_renew ? __('licenses.auto_renew') : __('licenses.manual_renew') }}</span>
        </p>
    </x-slot:subtitle>

    <x-flash />

    {{-- Same countdown, different meaning depending on auto_renew: a manual
         renewal running out stops the service, an automatic one takes money
         out of the account (spec section 5). --}}
    @if ($expired)
        <p role="status" class="mb-4 rounded border border-status-broken-soft bg-status-broken-soft px-4 py-3 font-semibold text-status-broken">
            {{ $license->auto_renew ? __('licenses.warning.expired_auto') : __('licenses.warning.expired_manual') }}
        </p>
    @elseif ($soon)
        <p role="status" class="mb-4 rounded border border-status-attention-soft bg-status-attention-soft px-4 py-3 font-semibold text-status-attention">
            {{ __($license->warningKey()) }}
        </p>
    @endif

    <div class="flex flex-col gap-4 lg:grid lg:grid-cols-3 lg:items-start">
        <div class="contents lg:col-span-2 lg:flex lg:flex-col lg:gap-4">
            <section class="order-1 {{ $card }} p-4">
                <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                    @foreach ([
                        'vendor' => $license->vendor,
                        'type' => $license->type?->label(),
                        'licence_model' => $license->licence_model?->label(),
                        'purchase_date' => $license->purchase_date?->format('j F Y'),
                        'renewal_cost' => $license->renewal_cost,
                    ] as $key => $value)
                        @if (filled($value))
                            <div>
                                <dt class="text-sm text-grey-600">{{ __('licenses.labels.'.$key) }}</dt>
                                <dd class="tabular-nums">{{ $value }}</dd>
                            </div>
                        @endif
                    @endforeach

                    @if ($license->seats_total !== null)
                        <div>
                            <dt class="text-sm text-grey-600">{{ __('licenses.labels.seats_used') }}</dt>
                            <dd class="tabular-nums">
                                {{ __('licenses.seats', ['used' => $license->seats_used ?? 0, 'total' => $license->seats_total]) }}
                            </dd>
                        </div>
                    @endif

                    {{-- The fact that gets lost when the person who set it up leaves. --}}
                    @if ($license->account_reference)
                        <div class="sm:col-span-2">
                            <dt class="text-sm text-grey-600">{{ __('licenses.labels.account_reference') }}</dt>
                            <dd class="font-semibold break-all">{{ $license->account_reference }}</dd>
                        </div>
                    @endif

                    @if ($license->asset)
                        <div class="sm:col-span-2">
                            <dt class="text-sm text-grey-600">{{ __('licenses.tied_to') }}</dt>
                            <dd>
                                <a href="{{ route('admin.assets.show', $license->asset) }}" class="link">
                                    {{ $license->asset->displayName() }}
                                </a>
                                <span class="ml-1 font-mono text-xs text-grey-700">{{ $license->asset->asset_tag }}</span>
                            </dd>
                        </div>
                    @endif
                </dl>

                @if ($license->notes)
                    <p class="mt-4 border-t border-grey-200 pt-4 text-sm whitespace-pre-line text-grey-800">{{ $license->notes }}</p>
                @endif
            </section>

            {{-- Renewal history comes out of the single audit log, not a
                 renewals table (spec section 5). --}}
            <section class="order-3">
                <h2 class="mb-3 text-lg font-bold tracking-tight">{{ __('licenses.history') }}</h2>

                @if ($license->events->isEmpty())
                    <p class="text-sm text-grey-600">{{ __('licenses.history_empty') }}</p>
                @else
                    <x-timeline :events="$license->events" />
                @endif
            </section>
        </div>

        <div class="contents lg:flex lg:flex-col lg:gap-4">
            <section class="order-2 {{ $card }}">
                <div class="p-4">
                    <a href="{{ route('admin.licenses.edit', $license) }}" class="btn-primary w-full">
                        {{ __('common.edit') }}
                    </a>
                </div>

                <details class="border-t border-grey-200" @if ($expired || $soon || $errors->any()) open @endif>
                    <summary class="disclosure flex min-h-tap cursor-pointer items-center px-4 font-semibold">
                        {{ __('licenses.renew_heading') }}
                    </summary>
                    <form method="POST" action="{{ route('admin.licenses.renew', $license) }}" class="px-4 pb-4">
                        @csrf
                        <p class="mb-3 text-sm text-grey-600">{{ __('licenses.renew_help') }}</p>

                        <x-field :label="__('licenses.new_expiry')" name="expires_at" required>
                            <x-input name="expires_at" type="date" required />
                        </x-field>

                        <x-field :label="__('licenses.updated_cost')" name="renewal_cost">
                            <x-input name="renewal_cost" type="number" step="0.01" min="0" inputmode="decimal" />
                        </x-field>

                        <button type="submit" class="btn-secondary w-full" data-busy-text="{{ __('common.saving') }}">
                            {{ __('licenses.renew_action') }}
                        </button>
                    </form>
                </details>

                {{-- Destructive, so it sits last, apart, and never in navy. --}}
                <form method="POST" action="{{ route('admin.licenses.destroy', $license) }}"
                      class="border-t border-grey-200 px-4 py-2"
                      data-confirm="{{ __('licenses.delete_confirm') }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex min-h-tap w-full items-center justify-center text-sm font-medium text-status-broken underline decoration-status-broken/40 underline-offset-2 hover:decoration-status-broken">
                        {{ __('licenses.delete') }}
                    </button>
                </form>
            </section>
        </div>
    </div>
</x-layouts.admin>
