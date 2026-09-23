<x-layouts.admin :title="__('manage.title')">
    <x-flash />

    <ul class="grid gap-3 sm:grid-cols-2">
        @foreach ($sections as $section)
            <li>
                <a href="{{ $section['url'] }}"
                   class="group flex min-h-tap items-center gap-3 rounded border border-grey-200 bg-white p-4 hover:border-grey-300 hover:bg-grey-50">
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold group-hover:underline group-hover:decoration-grey-400 group-hover:underline-offset-2">{{ __('manage.'.$section['key']) }}</span>
                        <span class="mt-0.5 block text-sm text-grey-600">{{ __('manage.'.$section['key'].'_help') }}</span>
                    </span>
                    <span aria-hidden="true" class="text-grey-500">→</span>
                </a>
            </li>
        @endforeach
    </ul>
</x-layouts.admin>
