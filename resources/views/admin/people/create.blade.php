<x-layouts.admin :title="__('people.add')" :back="route('admin.people.index')" :back-label="__('people.title')">
    <x-flash />
    <form method="POST" action="{{ route('admin.people.store') }}" class="max-w-md">
        @csrf
        @include('admin.people._form')
        <div class="mt-6 flex flex-wrap items-center gap-x-5 gap-y-2">
            <button type="submit" class="btn-primary" data-busy-text="{{ __('common.saving') }}">
                {{ __('common.save') }}
            </button>
            <a href="{{ route('admin.people.index') }}" class="link-action text-grey-700">{{ __('common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
