<x-layouts.admin :title="$room->name">
    <x-flash />

    <div class="mb-6 flex flex-wrap items-center gap-4">
        <a href="{{ route('admin.rooms.sheet', $room) }}"
           class="inline-flex min-h-tap items-center rounded bg-navy px-4 font-semibold text-white hover:bg-navy-deep">
            {{ __('rooms.inventory_sheet') }}
        </a>

        <form method="POST" action="{{ route('admin.rooms.counted', $room) }}">
            @csrf
            <button type="submit" class="min-h-tap text-sm font-medium text-navy underline">{{ __('rooms.mark_counted') }}</button>
        </form>

        <span class="text-sm text-grey-500 tabular-nums">
            {{ $room->last_counted_at
                ? __('rooms.last_counted', ['date' => $room->last_counted_at->format('j M Y')])
                : __('rooms.never_counted') }}
        </span>

        <a href="{{ route('admin.rooms.edit', $room) }}" class="text-sm text-grey-600 underline">{{ __('common.edit') }}</a>
    </div>

    <h2 class="mb-3 text-lg font-bold tracking-tight">{{ __('rooms.labelled_assets') }}</h2>

    @if ($assets->isEmpty())
        <p class="mb-8 text-sm text-grey-500">{{ __('rooms.nothing_here') }}</p>
    @else
        <ul class="mb-8 divide-y divide-grey-200 rounded border border-grey-200 bg-white">
            @foreach ($assets as $asset)
                <li>
                    <a href="{{ route('admin.assets.show', $asset) }}" class="flex min-h-tap items-center gap-3 p-3 hover:bg-grey-50">
                        <span class="min-w-0 flex-1 truncate font-medium">{{ $asset->displayName() }}</span>
                        <span class="font-mono text-xs text-grey-500">{{ $asset->asset_tag }}</span>
                        <x-status-badge :status="$asset->status" />
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    <h2 class="mb-3 text-lg font-bold tracking-tight">{{ __('rooms.furniture') }}</h2>

    @if ($contents->isNotEmpty())
        <ul class="mb-4 divide-y divide-grey-200 rounded border border-grey-200 bg-white">
            @foreach ($contents as $content)
                <li class="flex min-h-tap flex-wrap items-center gap-x-3 gap-y-1 p-3">
                    <span class="min-w-0 flex-1 font-medium">{{ $content->item_name }}</span>
                    <x-status-badge :status="$content->condition" />

                    <form method="POST" action="{{ route('admin.rooms.contents.update', [$room, $content]) }}"
                          class="flex items-center gap-2">
                        @csrf
                        @method('PUT')
                        <label for="quantity-{{ $content->id }}" class="sr-only">{{ __('rooms.quantity') }} — {{ $content->item_name }}</label>
                        <input type="number" name="quantity" id="quantity-{{ $content->id }}" min="0" max="1000"
                               value="{{ $content->quantity }}"
                               class="min-h-tap w-20 rounded border border-grey-300 px-2 text-center tabular-nums">
                        <button type="submit" class="min-h-tap text-sm text-navy underline">{{ __('common.save') }}</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('admin.rooms.contents.store', $room) }}" class="mb-8 max-w-md">
        @csrf

        <x-field :label="__('rooms.item_name')" name="item_name" required>
            <x-input name="item_name" :value="old('item_name')" required />
        </x-field>

        <x-field :label="__('rooms.quantity')" name="quantity" required>
            <x-input name="quantity" type="number" min="1" max="1000" :value="old('quantity', 1)" required />
        </x-field>

        <x-field :label="__('assets.labels.condition')" name="condition" required>
            <x-select name="condition" :options="\App\Enums\AssetCondition::options()"
                      :selected="\App\Enums\AssetCondition::Good->value" />
        </x-field>

        <button type="submit" class="inline-flex min-h-tap items-center rounded border border-grey-300 px-5 font-semibold hover:bg-grey-50">
            {{ __('rooms.add_furniture') }}
        </button>
    </form>

    @if ($supplies->isNotEmpty())
        <h2 class="mb-3 text-lg font-bold tracking-tight">{{ __('rooms.supplies_here') }}</h2>

        <ul class="mb-4 divide-y divide-grey-200 rounded border border-grey-200 bg-white">
            @foreach ($supplies as $supply)
                <li class="flex min-h-tap items-center gap-3 p-3 {{ $supply->isLow() ? 'bg-status-attention-soft' : '' }}">
                    <span class="min-w-0 flex-1 font-medium">{{ $supply->name }}</span>
                    <span class="tabular-nums">{{ $supply->describeQuantity() }}</span>
                </li>
            @endforeach
        </ul>

        <p class="text-sm">
            <a href="{{ route('cupboard', $room) }}" class="font-mono text-navy underline">{{ route('cupboard', $room) }}</a>
        </p>
    @endif
</x-layouts.admin>
