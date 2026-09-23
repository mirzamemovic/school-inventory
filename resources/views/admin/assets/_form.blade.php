{{--
    Four collapsible groups. Purchase is closed by default: during bulk entry
    nobody has the invoice in hand, and eight visible financial fields make
    people abandon the form (spec section 8).
--}}
@php
    $groupClasses = 'mb-3 rounded border border-grey-200 bg-white';
    $summaryClasses = 'flex min-h-tap cursor-pointer items-center px-4 font-semibold';
@endphp

<details class="{{ $groupClasses }}" open>
    <summary class="{{ $summaryClasses }}">{{ __('assets.groups.identity') }}</summary>
    <div class="border-t border-grey-200 p-4">
        <x-field :label="__('assets.labels.name')" name="name" required>
            <x-input name="name" :value="old('name', $asset->name)" required />
        </x-field>

        <x-field :label="__('assets.labels.category')" name="category_id" required>
            <x-select name="category_id" :options="$categories->pluck('name', 'id')"
                      :selected="$asset->category_id" :placeholder="__('common.not_set')" required />
        </x-field>

        <x-field :label="__('assets.labels.make')" name="make">
            <x-input name="make" :value="old('make', $asset->make)" />
        </x-field>

        <x-field :label="__('assets.labels.model')" name="model">
            <x-input name="model" :value="old('model', $asset->model)" />
        </x-field>

        <x-field :label="__('assets.labels.serial_number')" name="serial_number">
            {{-- Warns, never blocks. The field itself is unchanged: this adds a
                 live look-up and somewhere to put the answer. --}}
            <x-input name="serial_number" :value="old('serial_number', $asset->serial_number)"
                     hx-get="{{ route('admin.assets.serial-check', ['except' => $asset->getKey()]) }}"
                     hx-trigger="input changed delay:400ms"
                     hx-target="#serial-number-warning"
                     hx-swap="innerHTML"
                     hx-include="this" />

            <div id="serial-number-warning" aria-live="polite">
                @include('admin.assets._serial-warning', [
                    'duplicate' => app(\App\Services\SerialNumberCheck::class)
                        ->duplicateOf(old('serial_number', $asset->serial_number), $asset->getKey()),
                    'others' => 0,
                ])
            </div>
        </x-field>

        <x-field :label="__('assets.labels.official_number')" name="official_number" :help="__('assets.help.official_number')">
            <x-input name="official_number" :value="old('official_number', $asset->official_number)" />
        </x-field>

        <x-field :label="__('assets.labels.photo')" name="photo">
            <input type="file" name="photo" id="photo" accept="image/jpeg,image/png,image/webp"
                   class="block min-h-tap w-full text-sm">
        </x-field>
    </div>
</details>

<details class="{{ $groupClasses }}">
    <summary class="{{ $summaryClasses }}">{{ __('assets.groups.purchase') }}</summary>
    <div class="border-t border-grey-200 p-4">
        <p class="mb-4 text-sm text-grey-600">{{ __('assets.help.purchase') }}</p>

        <x-field :label="__('assets.labels.purchase_date')" name="purchase_date">
            <x-input name="purchase_date" type="date" :value="old('purchase_date', $asset->purchase_date?->toDateString())" />
        </x-field>

        <x-field :label="__('assets.labels.purchase_price')" name="purchase_price">
            <x-input name="purchase_price" type="number" step="0.01" min="0" :value="old('purchase_price', $asset->purchase_price)" />
        </x-field>

        <x-field :label="__('assets.labels.supplier')" name="supplier">
            <x-input name="supplier" :value="old('supplier', $asset->supplier)" />
        </x-field>

        <x-field :label="__('assets.labels.invoice_ref')" name="invoice_ref">
            <x-input name="invoice_ref" :value="old('invoice_ref', $asset->invoice_ref)" />
        </x-field>

        <x-field :label="__('assets.labels.funding_source')" name="funding_source" required>
            <x-select name="funding_source" :options="\App\Enums\FundingSource::options()"
                      :selected="$asset->funding_source?->value ?? 'unknown'" />
        </x-field>

        <x-field :label="__('assets.labels.warranty_end')" name="warranty_end">
            <x-input name="warranty_end" type="date" :value="old('warranty_end', $asset->warranty_end?->toDateString())" />
        </x-field>
    </div>
</details>

<details class="{{ $groupClasses }}" open>
    <summary class="{{ $summaryClasses }}">{{ __('assets.groups.location') }}</summary>
    <div class="border-t border-grey-200 p-4">
        <x-field :label="__('assets.labels.room')" name="room_id" required>
            <x-select name="room_id" :options="$rooms->pluck('name', 'id')"
                      :selected="$asset->room_id" :placeholder="__('common.not_set')" required />
        </x-field>
    </div>
</details>

<details class="{{ $groupClasses }}" open>
    <summary class="{{ $summaryClasses }}">{{ __('assets.groups.condition') }}</summary>
    <div class="border-t border-grey-200 p-4">
        <p class="mb-4 text-sm text-grey-600">{{ __('assets.help.status_vs_condition') }}</p>

        <x-field :label="__('assets.labels.status')" name="status" required>
            <x-select name="status" :options="\App\Enums\AssetStatus::options()"
                      :selected="$asset->status?->value ?? \App\Enums\AssetStatus::InUse->value" />
        </x-field>

        <x-field :label="__('assets.labels.condition')" name="condition" required>
            <x-select name="condition" :options="\App\Enums\AssetCondition::options()"
                      :selected="$asset->condition?->value ?? \App\Enums\AssetCondition::Good->value" />
        </x-field>

        <x-field :label="__('assets.labels.expected_life_years')" name="expected_life_years">
            <x-input name="expected_life_years" type="number" min="1" max="50" :value="old('expected_life_years', $asset->expected_life_years)" />
        </x-field>

        <label class="mb-4 flex min-h-tap items-center gap-2 text-sm font-medium text-grey-700">
            <input type="checkbox" name="needs_inspection" value="1" class="size-5 rounded border-grey-300"
                   @checked(old('needs_inspection', $asset->needs_inspection))>
            {{ __('assets.labels.needs_inspection') }}
        </label>

        <x-field :label="__('assets.labels.inspection_interval_months')" name="inspection_interval_months">
            <x-input name="inspection_interval_months" type="number" min="1" max="120"
                     :value="old('inspection_interval_months', $asset->inspection_interval_months)" />
        </x-field>

        <x-field :label="__('assets.labels.notes')" name="notes">
            <textarea name="notes" id="notes" rows="3"
                      class="block w-full rounded border border-grey-300 bg-white px-3 py-2 text-base">{{ old('notes', $asset->notes) }}</textarea>
        </x-field>
    </div>
</details>
