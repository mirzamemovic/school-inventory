<form method="GET" action="{{ $action }}" class="mb-6 max-w-sm print:hidden">
    <label for="class" class="mb-1 block text-sm font-semibold text-grey-800">{{ __('books.pick_class') }}</label>
    <div class="flex gap-2">
        <x-select name="class" id="class" :options="$classes->mapWithKeys(fn ($c) => [$c => $c])"
                  :selected="$class" :placeholder="__('common.not_set')" />
        <button type="submit" class="btn-secondary shrink-0">
            {{ __('books.show_class') }}
        </button>
    </div>
</form>
