<x-layouts.admin :title="__('intake.heading')">
    <x-flash />

    {{-- Four fields and one button. Nothing here may grow: this form is filled
         in while standing up, holding a phone, in front of the actual object. --}}
    <div class="max-w-md">
        <p class="mb-4 font-mono text-lg font-semibold">{{ $asset->asset_tag }}</p>

        <form method="POST" action="{{ route('admin.intake.update', $asset) }}">
            @csrf
            @method('PUT')

            <x-field :label="__('assets.labels.name')" name="name" required>
                <x-input name="name" :value="old('name')" autofocus required />
            </x-field>

            <x-field :label="__('assets.labels.category')" name="category_id" required>
                <x-select name="category_id" :options="$categories->pluck('name', 'id')"
                          :placeholder="__('common.not_set')" required />
            </x-field>

            <x-field :label="__('assets.labels.room')" name="room_id" required>
                <x-select name="room_id" :options="$rooms->pluck('name', 'id')"
                          :placeholder="__('common.not_set')" required />
            </x-field>

            <x-field :label="__('assets.labels.condition')" name="condition" required>
                <x-select name="condition" :options="\App\Enums\AssetCondition::options()"
                          :selected="\App\Enums\AssetCondition::Good->value" />
            </x-field>

            <button type="submit" class="inline-flex min-h-tap w-full items-center justify-center rounded bg-navy px-5 text-base font-semibold text-white hover:bg-navy-deep">
                {{ __('intake.save_and_next') }}
            </button>
        </form>
    </div>
</x-layouts.admin>
