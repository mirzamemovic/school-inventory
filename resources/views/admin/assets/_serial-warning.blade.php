{{--
    A warning, not an error. It is amber rather than red, it names the item it
    found so the person can judge for themselves, and there is nothing here to
    dismiss or confirm — the form saves either way.
--}}
@if ($duplicate)
    <p role="status"
       class="mt-2 rounded border border-status-attention-soft bg-status-attention-soft px-3 py-2 text-sm text-status-attention">
        {{ __('assets.serial_in_use') }}
        <a href="{{ route('admin.assets.show', $duplicate) }}" target="_blank"
           class="font-semibold underline underline-offset-2">{{ $duplicate->displayName() }}</a>
        <span class="font-mono">{{ $duplicate->asset_tag }}</span>@if ($duplicate->room)<span> · {{ $duplicate->room->name }}</span>@endif.
        @if ($others > 0)
            {{ trans_choice('assets.serial_in_use_others', $others, ['count' => $others]) }}
        @endif
        <span class="mt-1 block">{{ __('assets.serial_in_use_help') }}</span>
    </p>
@endif
