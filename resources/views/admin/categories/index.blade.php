<x-layouts.admin :title="__('categories.title')">
    <x-flash />

    <ul class="mb-6 divide-y divide-grey-200 rounded border border-grey-200 bg-white">
        @foreach ($categories as $category)
            <li class="flex min-h-tap flex-wrap items-center gap-x-3 gap-y-1 p-3">
                <span class="min-w-0 flex-1 font-medium">{{ $category->name }}</span>
                <span class="font-mono text-xs text-grey-500">{{ $category->prefix }}</span>

                @if ($category->needs_inspection_default)
                    <span class="rounded bg-status-attention-soft px-2 py-0.5 text-xs font-semibold text-status-attention">
                        {{ trans_choice('assets.labels.months', $category->default_inspection_interval_months ?? 0, ['count' => $category->default_inspection_interval_months]) }}
                    </span>
                @endif

                <span class="text-sm text-grey-600 tabular-nums">
                    {{ trans_choice('categories.asset_count', $category->assets_count, ['count' => $category->assets_count]) }}
                </span>

                <a href="{{ route('admin.categories.edit', $category) }}" class="min-h-tap text-sm text-navy underline">
                    {{ __('common.edit') }}
                </a>
            </li>
        @endforeach
    </ul>

    <h2 class="mb-3 text-lg font-bold tracking-tight">{{ __('categories.add') }}</h2>

    <form method="POST" action="{{ route('admin.categories.store') }}" class="max-w-md">
        @csrf

        <x-field :label="__('categories.labels.name')" name="name" required>
            <x-input name="name" :value="old('name')" required />
        </x-field>

        <x-field :label="__('categories.labels.prefix')" name="prefix" :help="__('categories.help.prefix')" required>
            <x-input name="prefix" :value="old('prefix')" maxlength="8" required />
        </x-field>

        <label class="mb-4 flex min-h-tap items-center gap-2 text-sm font-medium text-grey-700">
            <input type="checkbox" name="needs_inspection_default" value="1" class="size-5 rounded border-grey-300"
                   @checked(old('needs_inspection_default'))>
            {{ __('categories.labels.needs_inspection_default') }}
        </label>

        <x-field :label="__('categories.labels.default_inspection_interval_months')" name="default_inspection_interval_months">
            <x-input name="default_inspection_interval_months" type="number" min="1" max="120"
                     :value="old('default_inspection_interval_months')" />
        </x-field>

        <button type="submit" class="inline-flex min-h-tap items-center rounded bg-navy px-5 font-semibold text-white hover:bg-navy-deep">
            {{ __('common.add') }}
        </button>
    </form>
</x-layouts.admin>
