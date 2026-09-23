<x-layouts.admin :title="__('rooms.edit')" :back="route('admin.rooms.show', $room)" :back-label="$room->name">
    <x-flash />
    <form method="POST" action="{{ route('admin.rooms.update', $room) }}" class="max-w-md">
        @csrf
        @method('PUT')
        @include('admin.rooms._form')
        <div class="mt-6 flex flex-wrap items-center gap-x-5 gap-y-2">
            <button type="submit" class="btn-primary" data-busy-text="{{ __('common.saving') }}">
                {{ __('common.save_changes') }}
            </button>
            <a href="{{ route('admin.rooms.index') }}" class="link-action text-grey-700">{{ __('common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
