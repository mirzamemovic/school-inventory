<?php

namespace App\Http\Requests;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\FundingSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class AssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Identity. asset_tag is absent on purpose: it is write-once and the
            // form must not expose it (spec section 6.2).
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', Rule::exists('categories', 'id')],
            'official_number' => ['nullable', 'string', 'max:255'],
            'make' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:'.config('school.photos.max_kilobytes')],

            // Purchase
            'purchase_date' => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'invoice_ref' => ['nullable', 'string', 'max:255'],
            'funding_source' => ['required', new Enum(FundingSource::class)],
            'warranty_end' => ['nullable', 'date'],

            // Location. Every physical object is somewhere, and during intake
            // you are standing in the room.
            'room_id' => ['required', Rule::exists('rooms', 'id')],

            // State
            'status' => ['required', Rule::in(collect(AssetStatus::assignable())->map->value)],
            'condition' => ['required', new Enum(AssetCondition::class)],

            // Lifecycle
            'expected_life_years' => ['nullable', 'integer', 'min:1', 'max:50'],

            // Compliance
            'needs_inspection' => ['boolean'],
            'inspection_interval_months' => ['nullable', 'integer', 'min:1', 'max:120', 'required_if_accepted:needs_inspection'],

            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'needs_inspection' => $this->boolean('needs_inspection'),
        ]);
    }

    public function attributes(): array
    {
        return __('assets.fields');
    }
}
