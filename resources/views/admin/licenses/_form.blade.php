{{--
    One screen, not steps (spec section 8). A licence has fewer fields than an
    asset and nobody fills this in standing up, so there is nothing to collapse.
--}}
<x-field :label="__('licenses.labels.name')" name="name" required>
    <x-input name="name" :value="old('name', $license->name)" autofocus required />
</x-field>

<x-field :label="__('licenses.labels.vendor')" name="vendor">
    <x-input name="vendor" :value="old('vendor', $license->vendor)" />
</x-field>

<x-field :label="__('licenses.labels.type')" name="type" required>
    <x-select name="type" :options="\App\Enums\LicenseType::options()"
              :selected="$license->type?->value ?? \App\Enums\LicenseType::Software->value" />
</x-field>

<x-field :label="__('licenses.labels.licence_model')" name="licence_model" required>
    <x-select name="licence_model" :options="\App\Enums\LicenseModel::options()"
              :selected="$license->licence_model?->value ?? \App\Enums\LicenseModel::Subscription->value" />
</x-field>

<x-field :label="__('licenses.labels.expires_at')" name="expires_at" :help="__('licenses.help.expires_at')">
    <x-input name="expires_at" type="date" :value="old('expires_at', $license->expires_at?->toDateString())" />
</x-field>

<x-field :label="__('licenses.labels.purchase_date')" name="purchase_date">
    <x-input name="purchase_date" type="date" :value="old('purchase_date', $license->purchase_date?->toDateString())" />
</x-field>

<x-field :label="__('licenses.labels.renewal_cost')" name="renewal_cost">
    <x-input name="renewal_cost" type="number" step="0.01" min="0"
             :value="old('renewal_cost', $license->renewal_cost)" />
</x-field>

<div class="mb-4">
    <label class="flex min-h-tap items-center gap-2 text-sm font-medium text-grey-700">
        <input type="checkbox" name="auto_renew" value="1" class="size-5 rounded border-grey-300"
               @checked(old('auto_renew', $license->auto_renew))>
        {{ __('licenses.labels.auto_renew') }}
    </label>
</div>

<x-field :label="__('licenses.labels.seats_total')" name="seats_total" :help="__('licenses.help.seats')">
    <x-input name="seats_total" type="number" min="0" :value="old('seats_total', $license->seats_total)" />
</x-field>

<x-field :label="__('licenses.labels.seats_used')" name="seats_used">
    <x-input name="seats_used" type="number" min="0" :value="old('seats_used', $license->seats_used)" />
</x-field>

<x-field :label="__('licenses.labels.account_reference')" name="account_reference" :help="__('licenses.help.account_reference')">
    <x-input name="account_reference" :value="old('account_reference', $license->account_reference)" />
</x-field>

{{-- A single optional machine, for an OEM licence. Not a many-to-many. --}}
<x-field :label="__('licenses.labels.asset_id')" name="asset_id" :help="__('licenses.tied_to_help')">
    <x-select name="asset_id" :options="$assets->mapWithKeys(fn ($a) => [$a->id => $a->displayName().' — '.$a->asset_tag])"
              :selected="$license->asset_id" :placeholder="__('common.none')" />
</x-field>

<x-field :label="__('licenses.labels.notes')" name="notes">
    <textarea name="notes" id="notes" rows="3"
              class="block w-full rounded border border-grey-300 bg-white px-3 py-2 text-base">{{ old('notes', $license->notes) }}</textarea>
</x-field>
