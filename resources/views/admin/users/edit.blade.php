<x-layouts.admin :title="__('users.edit_title', ['name' => $user->name])" :back="route('admin.users.show', $user)" :back-label="$user->name">
    <x-flash />

    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="max-w-md">
        @csrf
        @method('PUT')
        @include('admin.users._form')
        <div class="mt-6 flex flex-wrap items-center gap-x-5 gap-y-2">
            <button type="submit" class="btn-primary" data-busy-text="{{ __('common.saving') }}">
                {{ __('common.save_changes') }}
            </button>
            <a href="{{ route('admin.users.show', $user) }}" class="link-action text-grey-700">{{ __('common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
