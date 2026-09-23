<x-layouts.admin :title="__('categories.title')" :back="route('admin.manage')" :back-label="__('manage.title')">
    <x-flash />

    <ul class="mb-8 divide-y divide-grey-200 rounded border border-grey-200 bg-white">
        @foreach ($categories as $category)
            <li class="flex flex-wrap items-center gap-x-5 gap-y-1 px-3 py-2.5 sm:px-4">
                <span class="min-w-0 flex-1 basis-48">
                    <span class="block font-semibold">{{ $category->name }}</span>
                    <span class="block font-mono text-xs text-grey-700">{{ $category->prefix }}</span>
                </span>

                <span class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-grey-700 tabular-nums">
                    @if ($category->needs_inspection_default)
                        <span>
                            {{ __('assets.labels.inspection_interval_months') }}
                            {{ trans_choice('assets.labels.months', $category->default_inspection_interval_months ?? 0, ['count' => $category->default_inspection_interval_months]) }}
                        </span>
                    @endif
                    <span>{{ trans_choice('categories.asset_count', $category->assets_count, ['count' => $category->assets_count]) }}</span>
                </span>

                <a href="{{ route('admin.categories.edit', $category) }}" class="link-action text-sm">
                    {{ __('common.edit') }}
                </a>
            </li>
        @endforeach
    </ul>

    {{-- Schools always add a category nobody predicted, but not often. --}}
    <details class="max-w-md rounded border border-grey-200 bg-white" @if ($errors->any()) open @endif>
        <summary class="disclosure flex min-h-tap cursor-pointer items-center px-4 py-2 text-lg font-bold">
            {{ __('categories.add') }}
        </summary>

        <form method="POST" action="{{ route('admin.categories.store') }}" class="border-t border-grey-200 p-4">
            @csrf

            <x-field :label="__('categories.labels.name')" name="name" required>
                <x-input name="name" :value="old('name')" required />
            </x-field>

            <x-field :label="__('categories.labels.prefix')" name="prefix" :help="__('categories.help.prefix')" required>
                <x-input name="prefix" :value="old('prefix')" maxlength="8" autocapitalize="characters" spellcheck="false" class="max-w-40 font-mono" required />
            </x-field>

            <label class="mb-2 flex min-h-tap cursor-pointer items-center gap-3 text-base font-semibold text-grey-800">
                <input type="checkbox" name="needs_inspection_default" value="1" class="size-5 rounded border-grey-300"
                       @checked(old('needs_inspection_default'))>
                {{ __('categories.labels.needs_inspection_default') }}
            </label>

            <x-field :label="__('categories.labels.default_inspection_interval_months')" name="default_inspection_interval_months">
                <x-input name="default_inspection_interval_months" type="number" min="1" max="120" inputmode="numeric" class="max-w-32"
                         :value="old('default_inspection_interval_months')" />
            </x-field>

            <button type="submit" class="btn-secondary" data-busy-text="{{ __('common.saving') }}">
                {{ __('categories.add') }}
            </button>
        </form>
    </details>
</x-layouts.admin>
