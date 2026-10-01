<x-layouts.admin :title="__('users.create.title')" :back="route('admin.users.index')" :back-label="__('users.title')">
    <x-flash />

    <p class="mb-5 max-w-prose text-grey-700">{{ __('users.create.help') }}</p>

    <form method="POST" action="{{ route('admin.users.store') }}" class="max-w-md">
        @csrf
        @include('admin.users._form', ['user' => null])
        <div class="mt-6 flex flex-wrap items-center gap-x-5 gap-y-2">
            <button type="submit" class="btn-primary" data-busy-text="{{ __('common.saving') }}">
                {{ __('users.create.button') }}
            </button>
            <a href="{{ route('admin.users.index') }}" class="link-action text-grey-700">{{ __('common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
