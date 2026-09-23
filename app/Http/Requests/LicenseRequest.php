<?php

namespace App\Http\Requests;

use App\Enums\LicenseModel;
use App\Enums\LicenseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(LicenseType::class)],
            'licence_model' => ['required', Rule::enum(LicenseModel::class)],

            // Two plain numbers. Nothing here assigns a seat to a person.
            'seats_total' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'seats_used' => ['nullable', 'integer', 'min:0', 'max:100000', 'lte:seats_total'],

            'purchase_date' => ['nullable', 'date'],

            // Left empty for a perpetual licence. Empty means never expires.
            'expires_at' => ['nullable', 'date'],

            'renewal_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'auto_renew' => ['boolean'],
            'account_reference' => ['nullable', 'string', 'max:255'],

            // One optional machine, for an OEM licence. Not a many-to-many.
            'asset_id' => ['nullable', Rule::exists('assets', 'id')],

            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['auto_renew' => $this->boolean('auto_renew')]);
    }

    public function attributes(): array
    {
        return __('licenses.fields');
    }
}
