<x-layouts.admin :title="__('manage.title')">
    <x-flash />

    <ul class="grid gap-3 sm:grid-cols-2">
        @foreach ($sections as $section)
            <li>
                <a href="{{ $section['url'] }}"
                   class="flex min-h-tap flex-col justify-center rounded border border-grey-200 bg-white p-4 hover:bg-grey-50">
                    <span class="font-semibold">{{ __('manage.'.$section['key']) }}</span>
                    <span class="mt-1 text-sm text-grey-600">{{ __('manage.'.$section['key'].'_help') }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</x-layouts.admin>
