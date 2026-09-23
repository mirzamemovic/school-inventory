{{--
    One row of the supplies list. Returned on its own by the +/- buttons so
    HTMX can swap just this row rather than reloading the page.
--}}
<li id="supply-{{ $supply->id }}"
    class="flex min-h-tap flex-wrap items-center gap-x-3 gap-y-1 p-3 {{ $supply->isLow() ? 'bg-status-attention-soft' : '' }}">
    <span class="min-w-0 flex-1">
        <span class="block font-medium">{{ $supply->name }}</span>
        <span class="block text-sm text-grey-500">
            {{ collect([$supply->room?->name, $supply->storage_location])->filter()->join(' · ') }}
        </span>
    </span>

    @if ($supply->isLow())
        <span class="rounded bg-status-attention-soft px-2 py-0.5 text-xs font-semibold text-status-attention">
            {{ __('supplies.low') }}
        </span>
    @endif

    <span class="text-xs text-grey-500 tabular-nums">{{ __('supplies.minimum', ['count' => $supply->minimum_level]) }}</span>

    <span class="flex items-center gap-1">
        <form method="POST" action="{{ route('admin.supplies.adjust', $supply) }}"
              hx-post="{{ route('admin.supplies.adjust', $supply) }}"
              hx-target="#supply-{{ $supply->id }}"
              hx-swap="outerHTML">
            @csrf
            <input type="hidden" name="by" value="-1">
            <button type="submit" class="min-h-tap min-w-tap rounded border border-grey-300 bg-white font-semibold"
                    aria-label="{{ __('supplies.take_one') }} — {{ $supply->name }}">
                −
                <span class="htmx-indicator sr-only">{{ __('common.working') }}</span>
            </button>
        </form>

        <span class="min-w-16 text-center text-lg font-bold tabular-nums">{{ $supply->quantity }}</span>

        <form method="POST" action="{{ route('admin.supplies.adjust', $supply) }}"
              hx-post="{{ route('admin.supplies.adjust', $supply) }}"
              hx-target="#supply-{{ $supply->id }}"
              hx-swap="outerHTML">
            @csrf
            <input type="hidden" name="by" value="1">
            <button type="submit" class="min-h-tap min-w-tap rounded border border-grey-300 bg-white font-semibold"
                    aria-label="{{ __('supplies.add_one') }} — {{ $supply->name }}">
                +
                <span class="htmx-indicator sr-only">{{ __('common.working') }}</span>
            </button>
        </form>
    </span>

    <span class="text-xs text-grey-500">{{ $supply->unit->label() }}</span>

    <a href="{{ route('admin.supplies.edit', $supply) }}" class="min-h-tap text-sm text-navy underline">
        {{ __('common.edit') }}
    </a>
</li>
