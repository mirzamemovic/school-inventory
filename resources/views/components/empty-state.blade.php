@props(['message'])
<div class="rounded border border-dashed border-grey-300 bg-white px-4 py-10 text-center">
    <p class="text-grey-600">{{ $message }}</p>
    @if (trim($slot) !== '')
        <div class="mt-4 flex justify-center">{{ $slot }}</div>
    @endif
</div>
