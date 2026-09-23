<x-layouts.admin :title="__('supplies.edit')" :back="route('admin.supplies.index')" :back-label="__('supplies.title')">
    <x-flash />

    <form method="POST" action="{{ route('admin.supplies.update', $supply) }}" class="max-w-md">
        @csrf
        @method('PUT')
        @include('admin.supplies._fields')

        <div class="mt-6 flex flex-wrap items-center gap-x-5 gap-y-2">
            <button type="submit" class="btn-primary" data-busy-text="{{ __('common.saving') }}">
                {{ __('common.save_changes') }}
            </button>
            <a href="{{ route('admin.supplies.index') }}" class="link-action text-grey-700">{{ __('common.cancel') }}</a>
        </div>
    </form>

    @if ($supply->room_id)
        <p class="mt-8 text-sm text-grey-700">
            {{ __('supplies.cupboard') }}:
            <a href="{{ route('cupboard', $supply->room) }}" class="link font-mono break-all">{{ route('cupboard', $supply->room) }}</a>
        </p>
    @endif
</x-layouts.admin>
