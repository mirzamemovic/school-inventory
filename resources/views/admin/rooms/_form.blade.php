<x-field :label="__('rooms.labels.name')" name="name" required>
    <x-input name="name" :value="old('name', $room->name)" autofocus required />
</x-field>

<x-field :label="__('rooms.labels.floor')" name="floor">
    <x-input name="floor" :value="old('floor', $room->floor)" />
</x-field>

<x-field :label="__('rooms.labels.type')" name="type" required>
    <x-select name="type" :options="\App\Enums\RoomType::options()" :selected="$room->type?->value" />
</x-field>
