@php $prefixFrozen = $category->assets()->exists(); @endphp
<x-layouts.admin :title="__('categories.edit')" :back="route('admin.categories.index')" :back-label="__('categories.title')">
    <x-flash />

    <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="max-w-md">
        @csrf
        @method('PUT')

        <x-field :label="__('categories.labels.name')" name="name" required>
            <x-input name="name" :value="old('name', $category->name)" autofocus required />
        </x-field>

        <x-field :label="__('categories.labels.prefix')" name="prefix"
                 :help="$prefixFrozen ? __('categories.help.prefix_frozen') : __('categories.help.prefix')" required>
            <x-input name="prefix" :value="old('prefix', $category->prefix)" maxlength="8" autocapitalize="characters" spellcheck="false"
                     class="max-w-40 font-mono" :readonly="$prefixFrozen" required />
        </x-field>

        <label class="mb-2 flex min-h-tap cursor-pointer items-center gap-3 text-base font-semibold text-grey-800">
            <input type="checkbox" name="needs_inspection_default" value="1" class="size-5 rounded border-grey-300"
                   @checked(old('needs_inspection_default', $category->needs_inspection_default))>
            {{ __('categories.labels.needs_inspection_default') }}
        </label>

        <x-field :label="__('categories.labels.default_inspection_interval_months')" name="default_inspection_interval_months">
            <x-input name="default_inspection_interval_months" type="number" min="1" max="120" inputmode="numeric" class="max-w-32"
                     :value="old('default_inspection_interval_months', $category->default_inspection_interval_months)" />
        </x-field>

        <div class="mt-6 flex flex-wrap items-center gap-x-5 gap-y-2">
            <button type="submit" class="btn-primary" data-busy-text="{{ __('common.saving') }}">
                {{ __('common.save_changes') }}
            </button>
            <a href="{{ route('admin.categories.index') }}" class="link-action text-grey-700">{{ __('common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
