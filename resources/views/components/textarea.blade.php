@props(['name', 'value' => null, 'rows' => 3])
{{-- Inside an <x-field>, help and error text are announced with the control. --}}
@aware(['label' => null, 'help' => null])
@php
    $id = $attributes->get('id', $name);
    $invalid = $errors->has($name);
    $describedBy = $label ? collect([$help ? $id.'-help' : null, $invalid ? $id.'-error' : null])->filter()->join(' ') : '';
@endphp
<textarea name="{{ $name }}"
          id="{{ $id }}"
          rows="{{ $rows }}"
          @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
          @if ($invalid) aria-invalid="true" @endif
          {{ $attributes->class([
               'block w-full rounded border bg-white px-3 py-2 text-base text-grey-900 placeholder:text-grey-500',
               'border-grey-300' => ! $invalid,
               'border-status-broken' => $invalid,
          ])->except('id') }}>{{ $value }}</textarea>
