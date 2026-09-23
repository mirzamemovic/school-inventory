<x-layouts.admin :title="__('assets.edit')" :back="route('admin.assets.show', $asset)" :back-label="$asset->displayName()">
    {{-- The tag is deliberately absent from this form: it is write-once and the
         sticker on the wall cannot be updated (spec section 6.2). --}}
    <x-slot:subtitle>
        <p class="font-mono text-base font-semibold text-grey-900">{{ $asset->asset_tag }}</p>
    </x-slot:subtitle>

    <x-flash />

    <form method="POST" action="{{ route('admin.assets.update', $asset) }}" enctype="multipart/form-data" class="max-w-2xl">
        @csrf
        @method('PUT')
        @include('admin.assets._form')

        <div class="mt-6 flex flex-wrap items-center gap-x-5 gap-y-2">
            <button type="submit" class="btn-primary" data-busy-text="{{ __('common.saving') }}">
                {{ __('common.save_changes') }}
            </button>
            <a href="{{ route('admin.assets.show', $asset) }}" class="link-action text-grey-700">{{ __('common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
