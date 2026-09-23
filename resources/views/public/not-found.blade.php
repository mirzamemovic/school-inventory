<x-layouts.public :title="__('public.not_found')">
    <h1 class="text-2xl font-bold tracking-tight">{{ __('public.not_found') }}</h1>
    <p class="mt-3 text-lg text-grey-700">{{ __('public.not_found_help') }}</p>
    @isset($tag)
        <p class="mt-6 font-mono text-base tracking-wide text-grey-600">{{ $tag }}</p>
    @endisset
</x-layouts.public>
