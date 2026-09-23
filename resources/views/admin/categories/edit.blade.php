@php $prefixFrozen = $category->assets()->exists(); @endphp
<x-layouts.admin :title="__('categories.edit')">
    <x-flash />

    <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="max-w-md">
        @csrf
        @method('PUT')

        <x-field :label="__('categories.labels.name')" name="name" required>
            <x-input name="name" :value="old('name', $category->name)" autofocus required />
        </x-field>

        <x-field :label="__('categories.labels.prefix')" name="prefix"
                 :help="$prefixFrozen ? __('categories.help.prefix_frozen') : __('categories.help.prefix')" required>
            <x-input name="prefix" :value="old('prefix', $category->prefix)" maxlength="8"
                     :readonly="$prefixFrozen" required />
        </x-field>

        <label class="mb-4 flex min-h-tap items-center gap-2 text-sm font-medium text-grey-700">
            <input type="checkbox" name="needs_inspection_default" value="1" class="size-5 rounded border-grey-300"
                   @checked(old('needs_inspection_default', $category->needs_inspection_default))>
            {{ __('categories.labels.needs_inspection_default') }}
        </label>

        <x-field :label="__('categories.labels.default_inspection_interval_months')" name="default_inspection_interval_months">
            <x-input name="default_inspection_interval_months" type="number" min="1" max="120"
                     :value="old('default_inspection_interval_months', $category->default_inspection_interval_months)" />
        </x-field>

        <div class="flex items-center gap-4">
            <button type="submit" class="inline-flex min-h-tap items-center rounded bg-navy px-5 font-semibold text-white hover:bg-navy-deep">
                {{ __('common.save_changes') }}
            </button>
            <a href="{{ route('admin.categories.index') }}" class="text-sm text-grey-600 underline">{{ __('common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
