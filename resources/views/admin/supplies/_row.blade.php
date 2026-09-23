{{--
    One row of the supplies list. Returned on its own by the +/- buttons so
    HTMX can swap just this row rather than reloading the page.
--}}
<li id="supply-{{ $supply->id }}"
    class="px-3 py-3 sm:px-4 {{ $supply->isLow() ? 'bg-status-attention-soft' : '' }}">
    <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
        <span class="min-w-0 flex-1 basis-52">
            <span class="flex flex-wrap items-center gap-2">
                <span class="font-semibold">{{ $supply->name }}</span>
                @if ($supply->isLow())
                    {{-- Solid, so it still reads on a row that is already amber. --}}
                    <span class="rounded bg-status-attention px-2 py-0.5 text-xs font-semibold text-white">
                        {{ __('supplies.low') }}
                    </span>
                @endif
            </span>
            <span class="block text-sm text-grey-700">
                {{ collect([$supply->room?->name, $supply->storage_location, __('supplies.minimum', ['count' => $supply->minimum_level])])->filter()->join(' · ') }}
            </span>
        </span>

        <span class="flex items-center gap-2">
            <form method="POST" action="{{ route('admin.supplies.adjust', $supply) }}"
                  hx-post="{{ route('admin.supplies.adjust', $supply) }}"
                  hx-target="#supply-{{ $supply->id }}"
                  hx-swap="outerHTML">
                @csrf
                <input type="hidden" name="by" value="-1">
                <button type="submit" class="btn-secondary min-w-tap px-0 text-xl"
                        aria-label="{{ __('supplies.take_one') }} — {{ $supply->name }}">
                    <span aria-hidden="true">−</span>
                    <span class="htmx-indicator sr-only">{{ __('common.working') }}</span>
                </button>
            </form>

            <span class="min-w-16 text-center leading-tight">
                <span class="block text-xl font-bold tabular-nums">{{ $supply->quantity }}</span>
                <span class="block text-xs text-grey-700">{{ $supply->unit->label() }}</span>
            </span>

            <form method="POST" action="{{ route('admin.supplies.adjust', $supply) }}"
                  hx-post="{{ route('admin.supplies.adjust', $supply) }}"
                  hx-target="#supply-{{ $supply->id }}"
                  hx-swap="outerHTML">
                @csrf
                <input type="hidden" name="by" value="1">
                <button type="submit" class="btn-secondary min-w-tap px-0 text-xl"
                        aria-label="{{ __('supplies.add_one') }} — {{ $supply->name }}">
                    <span aria-hidden="true">+</span>
                    <span class="htmx-indicator sr-only">{{ __('common.working') }}</span>
                </button>
            </form>
        </span>

        <a href="{{ route('admin.supplies.edit', $supply) }}" class="link-action text-sm">
            {{ __('common.edit') }}
        </a>
    </div>
</li>
