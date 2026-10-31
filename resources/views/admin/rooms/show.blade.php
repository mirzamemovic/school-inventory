<x-layouts.admin :title="$room->name" :back="route('admin.rooms.index')" :back-label="__('rooms.title')">
    <x-slot:subtitle>
        <p class="flex flex-wrap gap-x-3 text-grey-600 tabular-nums">
            <span>{{ collect([$room->type->label(), $room->floor])->filter()->join(' · ') }}</span>
            <span>
                {{ $room->last_counted_at
                    ? __('rooms.last_counted', ['date' => $room->last_counted_at->format('j M Y')])
                    : __('rooms.never_counted') }}
            </span>
        </p>
    </x-slot:subtitle>

    <x-flash />

    <div class="mb-8 flex flex-wrap items-center gap-x-5 gap-y-2">
        <a href="{{ route('admin.rooms.sheet', $room) }}" class="btn-primary">
            {{ __('rooms.inventory_sheet') }}
        </a>

        <form method="POST" action="{{ route('admin.rooms.counted', $room) }}">
            @csrf
            <button type="submit" class="link-action">{{ __('rooms.mark_counted') }}</button>
        </form>

        <a href="{{ route('admin.rooms.edit', $room) }}" class="link-action text-grey-700">{{ __('common.edit') }}</a>
    </div>

    <h2 class="mb-3 text-lg font-bold tracking-tight">{{ __('rooms.labelled_assets') }}</h2>

    @if ($assets->isEmpty())
        <p class="mb-8 text-sm text-grey-600">{{ __('rooms.nothing_here') }}</p>
    @else
        <ul class="mb-8 divide-y divide-grey-200 rounded border border-grey-200 bg-white">
            @foreach ($assets as $asset)
                <li>
                    <a href="{{ route('admin.assets.show', $asset) }}" class="flex min-h-tap items-center gap-3 px-3 py-2.5 hover:bg-grey-50 sm:px-4">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold">{{ $asset->displayName() }}</span>
                            <span class="block font-mono text-xs text-grey-700">{{ $asset->asset_tag }}</span>
                        </span>
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
                <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-3 py-2.5 sm:px-4">
                    <span class="flex min-w-0 flex-1 basis-48 flex-wrap items-center gap-2">
                        <span class="font-semibold">{{ $content->item_name }}</span>
                        <x-status-badge :status="$content->condition" />
                    </span>

                    <form method="POST" action="{{ route('admin.rooms.contents.update', [$room, $content]) }}"
                          class="flex items-center gap-2">
                        @csrf
                        @method('PUT')
                        <label for="quantity-{{ $content->id }}" class="sr-only">{{ __('rooms.quantity') }} — {{ $content->item_name }}</label>
                        <input type="number" name="quantity" id="quantity-{{ $content->id }}" min="0" max="1000" inputmode="numeric"
                               value="{{ $content->quantity }}"
                               class="min-h-tap w-20 rounded border border-grey-300 bg-white px-2 text-center text-base tabular-nums">
                        <button type="submit" class="btn-secondary text-sm">{{ __('common.save') }}</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('admin.rooms.contents.store', $room) }}" class="mb-8 max-w-md rounded border border-grey-200 bg-white p-4">
        @csrf
        <h3 class="mb-3 font-bold">{{ __('rooms.add_furniture') }}</h3>

        <x-field :label="__('rooms.item_name')" name="item_name" required>
            <x-input name="item_name" :value="old('item_name')" required />
        </x-field>

        <div class="grid grid-cols-2 gap-x-4">
            <x-field :label="__('rooms.quantity')" name="quantity" required>
                <x-input name="quantity" type="number" min="1" max="1000" inputmode="numeric" :value="old('quantity', 1)" required />
            </x-field>

            <x-field :label="__('assets.labels.condition')" name="condition" required>
                <x-select name="condition" :options="\App\Enums\AssetCondition::options()"
                          :selected="\App\Enums\AssetCondition::Good->value" />
            </x-field>
        </div>

        <button type="submit" class="btn-secondary" data-busy-text="{{ __('common.saving') }}">
            {{ __('rooms.add_furniture') }}
        </button>
    </form>

    @if ($supplies->isNotEmpty())
        <h2 class="mb-3 text-lg font-bold tracking-tight">{{ __('rooms.supplies_here') }}</h2>

        <ul class="mb-4 divide-y divide-grey-200 rounded border border-grey-200 bg-white">
            @foreach ($supplies as $supply)
                <li class="flex min-h-tap items-center gap-3 px-3 py-2 sm:px-4">
                    <span class="min-w-0 flex-1 font-semibold">{{ $supply->name }}</span>
                    @if ($supply->isLow())
                        <span class="rounded bg-status-attention-soft px-2 py-0.5 text-xs font-semibold text-status-attention">{{ __('supplies.low') }}</span>
                    @endif
                    <span class="tabular-nums">{{ $supply->describeQuantity() }}</span>
                </li>
            @endforeach
        </ul>

        <p class="text-sm text-grey-700">
            {{ __('rooms.cupboard_link') }}:
            <a href="{{ $room->cupboardUrl() }}" class="link font-mono break-all">{{ $room->cupboardUrl() }}</a>
        </p>
    @endif
</x-layouts.admin>
