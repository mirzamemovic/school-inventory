<x-layouts.admin :title="__('licenses.edit')" :back="route('admin.licenses.show', $license)" :back-label="$license->name">
    <x-flash />

    <form method="POST" action="{{ route('admin.licenses.update', $license) }}" class="max-w-xl">
        @csrf
        @method('PUT')
        @include('admin.licenses._form')

        <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
            <button type="submit" class="btn-primary" data-busy-text="{{ __('common.saving') }}">
                {{ __('common.save_changes') }}
            </button>
            <a href="{{ route('admin.licenses.show', $license) }}" class="link-action text-grey-700">{{ __('common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
