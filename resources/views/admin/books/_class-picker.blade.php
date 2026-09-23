<form method="GET" action="{{ $action }}" class="mb-6 max-w-xs">
    <label for="class" class="mb-1 block text-sm font-medium text-grey-700">{{ __('books.pick_class') }}</label>
    <div class="flex gap-2">
        <x-select name="class" id="class" :options="$classes->mapWithKeys(fn ($c) => [$c => $c])"
                  :selected="$class" :placeholder="__('common.not_set')" />
        <button type="submit" class="min-h-tap rounded border border-grey-300 bg-white px-4 text-sm font-medium">
            {{ __('common.search') }}
        </button>
    </div>
</form>
