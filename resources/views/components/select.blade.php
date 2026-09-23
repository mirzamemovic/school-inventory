@props(['name', 'options' => [], 'selected' => null, 'placeholder' => null])
<select name="{{ $name }}"
        id="{{ $attributes->get('id', $name) }}"
        {{ $attributes->class([
             'block min-h-tap w-full rounded border bg-white px-3 text-base',
             'border-grey-300' => ! $errors->has($name),
             'border-status-broken' => $errors->has($name),
        ])->except('id') }}>
    @if ($placeholder)
        <option value="">{{ $placeholder }}</option>
    @endif
    @foreach ($options as $value => $label)
        <option value="{{ $value }}" @selected((string) old($name, $selected) === (string) $value)>{{ $label }}</option>
    @endforeach
</select>
