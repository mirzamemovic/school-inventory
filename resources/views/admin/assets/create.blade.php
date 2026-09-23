<x-layouts.admin :title="__('assets.add')" :back="route('admin.assets.index')" :back-label="__('assets.title')">
    <x-flash />

    <form method="POST" action="{{ route('admin.assets.store') }}" enctype="multipart/form-data" class="max-w-2xl">
        @csrf
        @include('admin.assets._form')

        <div class="mt-6 flex flex-wrap items-center gap-x-5 gap-y-2">
            <button type="submit" class="btn-primary" data-busy-text="{{ __('common.saving') }}">
                {{ __('common.save') }}
            </button>
            <a href="{{ route('admin.assets.index') }}" class="link-action text-grey-700">{{ __('common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
