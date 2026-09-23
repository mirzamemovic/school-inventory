<x-layouts.admin :title="__('intake.title')" :back="route('admin.assets.index')" :back-label="__('assets.title')">
    <x-flash />

    {{-- The top of the loop. Every save lands back here, never on a list. --}}
    <div class="max-w-md">
        <section class="rounded border border-grey-200 bg-white p-4 sm:p-5">
            <h2 class="text-xl font-bold tracking-tight">{{ __('intake.scan_next') }}</h2>
            <p class="mt-1 text-grey-700">{{ __('intake.instructions') }}</p>

            <form method="POST" action="{{ route('admin.intake.find') }}" class="mt-5">
                @csrf

                <x-field :label="__('intake.tag_label')" name="asset_tag" required>
                    <x-input name="asset_tag" :value="old('asset_tag')" autocapitalize="characters"
                             autocomplete="off" spellcheck="false" class="font-mono text-lg uppercase" autofocus required />
                </x-field>

                <button type="submit" class="btn-primary w-full">
                    {{ __('intake.open') }}
                </button>
            </form>
        </section>

        <p class="mt-5 text-grey-700 tabular-nums">
            {{ trans_choice('intake.remaining', $remaining, ['count' => $remaining]) }}
        </p>

        <p>
            <a href="{{ route('admin.labels.index') }}" class="link-action text-sm">{{ __('labels.title') }}</a>
        </p>
    </div>
</x-layouts.admin>
