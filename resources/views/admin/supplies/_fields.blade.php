<x-field :label="__('supplies.labels.name')" name="name" required>
    <x-input name="name" :value="old('name', $supply->name)" required />
</x-field>

<x-field :label="__('supplies.labels.quantity')" name="quantity" required>
    <x-input name="quantity" type="number" min="0" :value="old('quantity', $supply->quantity ?? 0)" required />
</x-field>

<x-field :label="__('supplies.labels.minimum_level')" name="minimum_level" required>
    <x-input name="minimum_level" type="number" min="0" :value="old('minimum_level', $supply->minimum_level ?? 0)" required />
</x-field>

<x-field :label="__('supplies.labels.unit')" name="unit" required>
    <x-select name="unit" :options="\App\Enums\SupplyUnit::options()" :selected="$supply->unit?->value" />
</x-field>

<x-field :label="__('supplies.labels.room')" name="room_id">
    <x-select name="room_id" :options="$rooms->pluck('name', 'id')" :selected="$supply->room_id" :placeholder="__('common.none')" />
</x-field>

<x-field :label="__('supplies.labels.storage_location')" name="storage_location">
    <x-input name="storage_location" :value="old('storage_location', $supply->storage_location)" />
</x-field>
