<x-layouts.public :title="__('inspections.phones.setup_heading')">
    <h1 class="text-2xl leading-tight font-bold tracking-tight text-balance">{{ __('inspections.phones.setup_heading') }}</h1>
    <p class="mt-3 text-lg text-grey-700">{{ __('inspections.phones.setup_help') }}</p>

    {{-- Opening the link changed nothing; only this button uses the code. --}}
    <form method="POST" action="{{ $action }}" class="mt-8">
        @csrf
        <button type="submit" data-busy-text="{{ __('common.saving') }}"
                class="flex min-h-14 w-full items-center justify-center rounded bg-navy px-4 text-lg font-semibold text-white hover:bg-navy-deep">
            {{ __('inspections.phones.setup_button') }}
        </button>
    </form>
</x-layouts.public>
