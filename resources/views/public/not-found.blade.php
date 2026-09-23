<x-layouts.public :title="__('public.not_found')">
    <h1 class="text-2xl font-bold tracking-tight">{{ __('public.not_found') }}</h1>
    <p class="mt-3 text-grey-600">{{ __('public.not_found_help') }}</p>
    @isset($tag)
        <p class="mt-6 font-mono text-sm text-grey-500">{{ $tag }}</p>
    @endisset
</x-layouts.public>
