@props(['perSheet' => 24, 'columns' => 3])
{{--
    Which sticker to start at. This lets a partly used sheet go back through the
    printer instead of into the bin, and it is the single most practically
    useful thing in the printing flow (spec section 6.3).
--}}
<fieldset class="mb-4">
    <legend class="mb-1 text-sm font-medium text-grey-700">{{ __('labels.start_heading') }}</legend>
    <p class="mb-2 text-xs text-grey-500">{{ __('labels.start_help') }}</p>

    <div class="grid max-w-56 gap-1" style="grid-template-columns: repeat({{ $columns }}, minmax(0, 1fr));">
        @for ($position = 1; $position <= $perSheet; $position++)
            <label class="sheet-cell">
                <input type="radio" name="start_position" value="{{ $position }}" class="sr-only"
                       @checked((int) old('start_position', 1) === $position)>
                <span class="sr-only">{{ __('labels.position', ['number' => $position]) }}</span>
                <span aria-hidden="true">{{ $position }}</span>
            </label>
        @endfor
    </div>
</fieldset>
