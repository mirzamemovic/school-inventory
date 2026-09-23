@props(['label', 'name', 'help' => null, 'required' => false])
@php $id = $attributes->get('id', $name); @endphp
{{--
    Label, then help, then the control, then the error. Help sits above the
    control so a phone keyboard never covers it, and both help and error are
    tied to the control with aria-describedby by the input components.

    The required marker is grey on purpose: red means broken in this
    application, never "fill this in" (spec section 9).
--}}
<div class="mb-5">
    <label for="{{ $id }}" class="mb-1 block text-sm font-semibold text-grey-800">
        {{ $label }}@if ($required)<span aria-hidden="true" class="font-normal text-grey-500"> *</span>@endif
    </label>

    @if ($help)
        <p id="{{ $id }}-help" class="mb-1.5 text-sm text-grey-600">{{ $help }}</p>
    @endif

    {{ $slot }}

    @error($name)
        <p id="{{ $id }}-error" class="mt-1.5 text-sm font-medium text-status-broken">{{ $message }}</p>
    @enderror
</div>
