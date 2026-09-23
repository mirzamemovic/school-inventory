@props(['label', 'name', 'help' => null, 'required' => false])
@php $id = $attributes->get('id', $name); @endphp
<div class="mb-4">
    <label for="{{ $id }}" class="mb-1 block text-sm font-medium text-grey-700">
        {{ $label }}@if ($required)<span aria-hidden="true" class="text-status-broken"> *</span>@endif
    </label>

    {{ $slot }}

    @if ($help)
        <p id="{{ $id }}-help" class="mt-1 text-xs text-grey-500">{{ $help }}</p>
    @endif

    @error($name)
        <p class="mt-1 text-xs font-medium text-status-broken">{{ $message }}</p>
    @enderror
</div>
