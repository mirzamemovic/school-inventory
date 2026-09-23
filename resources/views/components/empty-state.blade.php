@props(['message'])
{{-- An empty list says what would be here and, where there is one, the next
     step. It is an invitation to act, not a shrug (spec section 9). --}}
<div {{ $attributes->class('rounded border border-dashed border-grey-300 bg-white px-4 py-8 text-center sm:py-10') }}>
    <p class="mx-auto max-w-prose text-grey-700">{{ $message }}</p>
    @if (trim($slot) !== '')
        <div class="mt-4 flex flex-wrap items-center justify-center gap-x-4 gap-y-2">{{ $slot }}</div>
    @endif
</div>
