<x-layouts.admin :title="__('people.edit')">
    <x-flash />
    <form method="POST" action="{{ route('admin.people.update', $person) }}" class="max-w-md">
        @csrf
        @method('PUT')
        @include('admin.people._form')
        <div class="flex items-center gap-4">
            <button type="submit" class="inline-flex min-h-tap items-center rounded bg-navy px-5 font-semibold text-white hover:bg-navy-deep">
                {{ __('common.save_changes') }}
            </button>
            <a href="{{ route('admin.people.index') }}" class="text-sm text-grey-600 underline">{{ __('common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
