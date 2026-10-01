@php $busy ??= false; @endphp
<x-layouts.public :title="__($busy ? 'users.setup.busy_heading' : 'users.setup.unavailable_heading')">
    <h1 class="text-2xl leading-tight font-bold tracking-tight">{{ __($busy ? 'users.setup.busy_heading' : 'users.setup.unavailable_heading') }}</h1>
    <p class="mt-3 text-lg text-grey-700">{{ __($busy ? 'users.setup.busy_help' : 'users.setup.unavailable_help') }}</p>
</x-layouts.public>
