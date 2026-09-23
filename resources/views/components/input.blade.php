@props(['name', 'type' => 'text'])
{{-- Inside an <x-field>, help and error text are announced with the control. --}}
@aware(['label' => null, 'help' => null])
@php
    $id = $attributes->get('id', $name);
    $invalid = $errors->has($name);
    $describedBy = $label ? collect([$help ? $id.'-help' : null, $invalid ? $id.'-error' : null])->filter()->join(' ') : '';
@endphp
<input type="{{ $type }}"
       name="{{ $name }}"
       id="{{ $id }}"
       @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
       @if ($invalid) aria-invalid="true" @endif
       {{ $attributes->class([
            'block min-h-tap w-full rounded border bg-white px-3 text-base text-grey-900 placeholder:text-grey-500 read-only:bg-grey-50 read-only:text-grey-600',
            'border-grey-300' => ! $invalid,
            'border-status-broken' => $invalid,
       ])->except('id') }}>
