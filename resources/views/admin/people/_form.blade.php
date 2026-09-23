<x-field :label="__('people.labels.name')" name="name" required>
    <x-input name="name" :value="old('name', $person->name)" autofocus required />
</x-field>

<div class="grid gap-x-4 sm:grid-cols-2">
    <x-field :label="__('people.labels.type')" name="type" required>
        <x-select name="type" :options="\App\Enums\PersonType::options()" :selected="$person->type?->value" />
    </x-field>

    <x-field :label="__('people.labels.class')" name="class">
        <x-input name="class" :value="old('class', $person->class)" />
    </x-field>
</div>

<x-field :label="__('people.labels.email')" name="email">
    <x-input name="email" type="email" :value="old('email', $person->email)" />
</x-field>
