<x-layouts.admin :title="__('licenses.add')" :back="route('admin.licenses.index')" :back-label="__('licenses.title')">
    <x-flash />

    <form method="POST" action="{{ route('admin.licenses.store') }}" class="max-w-xl">
        @csrf
        @include('admin.licenses._form')

        <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
            <button type="submit" class="btn-primary" data-busy-text="{{ __('common.saving') }}">
                {{ __('common.save') }}
            </button>
            <a href="{{ route('admin.licenses.index') }}" class="link-action text-grey-700">{{ __('common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
