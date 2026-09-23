<x-layouts.admin :title="__('assets.edit')">
    <x-flash />

    {{-- The tag is deliberately absent from this form: it is write-once and the
         sticker on the wall cannot be updated (spec section 6.2). --}}
    <p class="mb-4 font-mono text-sm text-grey-500">{{ $asset->asset_tag }}</p>

    <form method="POST" action="{{ route('admin.assets.update', $asset) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.assets._form')

        <div class="flex items-center gap-4">
            <button type="submit" class="inline-flex min-h-tap items-center rounded bg-navy px-5 font-semibold text-white hover:bg-navy-deep">
                {{ __('common.save_changes') }}
            </button>
            <a href="{{ route('admin.assets.show', $asset) }}" class="text-sm text-grey-600 underline">{{ __('common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
