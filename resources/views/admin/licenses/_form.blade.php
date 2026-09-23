{{--
    One screen, not steps (spec section 8). A licence has fewer fields than an
    asset and nobody fills this in standing up, so there is nothing to collapse
    — the fields are only grouped under plain headings so the form reads in
    the order people think about a licence.
--}}
@php $legend = 'mb-3 text-lg font-bold'; @endphp

<fieldset class="mb-6">
    <legend class="{{ $legend }}">{{ __('licenses.groups.what') }}</legend>

    <x-field :label="__('licenses.labels.name')" name="name" required>
        <x-input name="name" :value="old('name', $license->name)" autofocus required />
    </x-field>

    <x-field :label="__('licenses.labels.vendor')" name="vendor">
        <x-input name="vendor" :value="old('vendor', $license->vendor)" />
    </x-field>

    <div class="grid gap-x-4 sm:grid-cols-2">
        <x-field :label="__('licenses.labels.type')" name="type" required>
            <x-select name="type" :options="\App\Enums\LicenseType::options()"
                      :selected="$license->type?->value ?? \App\Enums\LicenseType::Software->value" />
        </x-field>

        <x-field :label="__('licenses.labels.licence_model')" name="licence_model" required>
            <x-select name="licence_model" :options="\App\Enums\LicenseModel::options()"
                      :selected="$license->licence_model?->value ?? \App\Enums\LicenseModel::Subscription->value" />
        </x-field>
    </div>
</fieldset>

<fieldset class="mb-6">
    <legend class="{{ $legend }}">{{ __('licenses.groups.when') }}</legend>

    <x-field :label="__('licenses.labels.expires_at')" name="expires_at" :help="__('licenses.help.expires_at')">
        <x-input name="expires_at" type="date" :value="old('expires_at', $license->expires_at?->toDateString())" />
    </x-field>

    <label class="mb-5 flex min-h-tap cursor-pointer items-center gap-3 text-base font-semibold text-grey-800">
        <input type="checkbox" name="auto_renew" value="1" class="size-5 rounded border-grey-300"
               @checked(old('auto_renew', $license->auto_renew))>
        {{ __('licenses.labels.auto_renew') }}
    </label>

    <div class="grid gap-x-4 sm:grid-cols-2">
        <x-field :label="__('licenses.labels.purchase_date')" name="purchase_date">
            <x-input name="purchase_date" type="date" :value="old('purchase_date', $license->purchase_date?->toDateString())" />
        </x-field>

        <x-field :label="__('licenses.labels.renewal_cost')" name="renewal_cost">
            <x-input name="renewal_cost" type="number" step="0.01" min="0" inputmode="decimal"
                     :value="old('renewal_cost', $license->renewal_cost)" />
        </x-field>
    </div>
</fieldset>

<fieldset class="mb-6">
    <legend class="{{ $legend }}">{{ __('licenses.groups.who') }}</legend>

    <x-field :label="__('licenses.labels.account_reference')" name="account_reference" :help="__('licenses.help.account_reference')">
        <x-input name="account_reference" :value="old('account_reference', $license->account_reference)" />
    </x-field>

    <p class="mb-1 text-sm text-grey-600">{{ __('licenses.help.seats') }}</p>
    <div class="grid grid-cols-2 gap-x-4">
        <x-field :label="__('licenses.labels.seats_total')" name="seats_total">
            <x-input name="seats_total" type="number" min="0" inputmode="numeric" :value="old('seats_total', $license->seats_total)" />
        </x-field>

        <x-field :label="__('licenses.labels.seats_used')" name="seats_used">
            <x-input name="seats_used" type="number" min="0" inputmode="numeric" :value="old('seats_used', $license->seats_used)" />
        </x-field>
    </div>

    {{-- A single optional machine, for an OEM licence. Not a many-to-many. --}}
    <x-field :label="__('licenses.labels.asset_id')" name="asset_id" :help="__('licenses.tied_to_help')">
        <x-select name="asset_id" :options="$assets->mapWithKeys(fn ($a) => [$a->id => $a->displayName().' — '.$a->asset_tag])"
                  :selected="$license->asset_id" :placeholder="__('common.none')" />
    </x-field>

    <x-field :label="__('licenses.labels.notes')" name="notes">
        <x-textarea name="notes" :value="old('notes', $license->notes)" />
    </x-field>
</fieldset>
