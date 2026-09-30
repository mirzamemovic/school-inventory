<x-layouts.admin :title="$phone->label" :back="route('admin.inspectors.index')" :back-label="__('inspections.phones.title')">
    <x-flash />

    <div class="max-w-md space-y-4">
        @if ($setupUrl)
            {{-- Scanned there and then by the inspector's own phone. The code
                 works once and runs out quickly, so a photo of this screen is
                 worth nothing by the afternoon. --}}
            <section class="rounded border border-grey-200 bg-white p-4 text-center sm:p-6">
                <div class="mx-auto w-56" role="img" aria-label="{{ __('inspections.phones.add') }}">
                    {!! $qrSvg !!}
                </div>
                <p class="mt-4 text-grey-800">
                    {{ __('inspections.phones.scan_now', ['time' => $phone->invite_expires_at->format('H:i')]) }}
                </p>
                <p class="mt-4 text-sm text-grey-600">{{ __('inspections.phones.or_open') }}</p>
                <input type="text" readonly value="{{ $setupUrl }}" data-select-on-focus aria-label="{{ __('inspections.phones.or_open') }}"
                       class="mt-1 block min-h-tap w-full rounded border border-grey-300 bg-grey-50 px-3 font-mono text-xs text-grey-800">
            </section>
        @elseif ($phone->isActive())
            <p class="rounded border border-status-ok-soft bg-status-ok-soft px-4 py-3 font-semibold text-status-ok">
                {{ __('inspections.phones.set_up_on', ['date' => $phone->claimed_at->format('j M Y, H:i')]) }}
            </p>
        @elseif ($phone->withdrawn_at === null)
            <p class="rounded border border-grey-200 bg-white px-4 py-3 text-grey-700">{{ __('inspections.phones.expired_help') }}</p>
        @endif

        @if ($phone->withdrawn_at)
            <p class="rounded border border-grey-200 bg-white px-4 py-3 text-grey-700">
                {{ __('inspections.phones.withdrawn_on', ['date' => $phone->withdrawn_at->format('j M Y')]) }}
            </p>
        @else
            {{-- Destructive, so last, apart, and never navy. --}}
            <form method="POST" action="{{ route('admin.inspectors.withdraw', $phone) }}"
                  data-confirm="{{ __('inspections.phones.withdraw_confirm') }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-danger w-full sm:w-auto">{{ __('inspections.phones.withdraw') }}</button>
            </form>
        @endif
    </div>
</x-layouts.admin>
