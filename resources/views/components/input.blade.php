@props(['name', 'type' => 'text'])
<input type="{{ $type }}"
       name="{{ $name }}"
       id="{{ $attributes->get('id', $name) }}"
       {{ $attributes->class([
            'block min-h-tap w-full rounded border bg-white px-3 text-base',
            'border-grey-300' => ! $errors->has($name),
            'border-status-broken' => $errors->has($name),
       ])->except('id') }}>
