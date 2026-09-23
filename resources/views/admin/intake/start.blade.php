<x-layouts.admin :title="__('intake.title')">
    <x-flash />

    <div class="max-w-md">
        <h2 class="text-xl font-bold tracking-tight">{{ __('intake.scan_next') }}</h2>
        <p class="mt-2 text-grey-600">{{ __('intake.instructions') }}</p>

        <form method="POST" action="{{ route('admin.intake.find') }}" class="mt-6">
            @csrf

            <x-field :label="__('intake.tag_label')" name="asset_tag" required>
                <x-input name="asset_tag" :value="old('asset_tag')" autocapitalize="characters"
                         autocomplete="off" spellcheck="false" class="font-mono" autofocus required />
            </x-field>

            <button type="submit" class="inline-flex min-h-tap items-center rounded bg-navy px-5 font-semibold text-white hover:bg-navy-deep">
                {{ __('intake.open') }}
            </button>
        </form>

        <p class="mt-8 text-sm text-grey-600 tabular-nums">
            {{ trans_choice('intake.remaining', $remaining, ['count' => $remaining]) }}
        </p>

        <p class="mt-2">
            <a href="{{ route('admin.labels.index') }}" class="text-sm text-navy underline">{{ __('labels.title') }}</a>
        </p>
    </div>
</x-layouts.admin>
