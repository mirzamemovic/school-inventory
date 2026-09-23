{{-- Three sentences and a way to ask. Anything longer does not get read. --}}
<x-layouts.public :title="__('privacy.title')">
    <h1 class="text-2xl font-bold tracking-tight">{{ __('privacy.title') }}</h1>

    <div class="mt-4 space-y-3 text-grey-700">
        <p>{{ __('privacy.what') }}</p>
        <p>{{ __('privacy.why') }}</p>
        <p>{{ __('privacy.how_long') }}</p>
    </div>

    <p class="mt-6 text-sm text-grey-600">{{ __('privacy.ask') }}</p>
</x-layouts.public>
