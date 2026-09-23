<x-layouts.admin :title="__('people.edit')" :back="route('admin.people.index')" :back-label="__('people.title')">
    <x-flash />
    <form method="POST" action="{{ route('admin.people.update', $person) }}" class="max-w-md">
        @csrf
        @method('PUT')
        @include('admin.people._form')
        <div class="mt-6 flex flex-wrap items-center gap-x-5 gap-y-2">
            <button type="submit" class="btn-primary" data-busy-text="{{ __('common.saving') }}">
                {{ __('common.save_changes') }}
            </button>
            <a href="{{ route('admin.people.index') }}" class="link-action text-grey-700">{{ __('common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
