<x-layouts.admin :title="__('supplies.edit')">
    <x-flash />

    <form method="POST" action="{{ route('admin.supplies.update', $supply) }}" class="max-w-md">
        @csrf
        @method('PUT')
        @include('admin.supplies._fields')

        <div class="flex items-center gap-4">
            <button type="submit" class="inline-flex min-h-tap items-center rounded bg-navy px-5 font-semibold text-white hover:bg-navy-deep">
                {{ __('common.save_changes') }}
            </button>
            <a href="{{ route('admin.supplies.index') }}" class="text-sm text-grey-600 underline">{{ __('common.cancel') }}</a>
        </div>
    </form>

    @if ($supply->room_id)
        <p class="mt-8 text-sm text-grey-600">
            {{ __('supplies.cupboard') }}:
            <a href="{{ route('cupboard', $supply->room) }}" class="font-mono text-navy underline">{{ route('cupboard', $supply->room) }}</a>
        </p>
    @endif
</x-layouts.admin>
