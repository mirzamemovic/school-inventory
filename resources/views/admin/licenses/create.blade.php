<x-layouts.admin :title="__('licenses.add')">
    <x-flash />

    <form method="POST" action="{{ route('admin.licenses.store') }}" class="max-w-md">
        @csrf
        @include('admin.licenses._form')

        <div class="flex items-center gap-4">
            <button type="submit" class="inline-flex min-h-tap items-center rounded bg-navy px-5 font-semibold text-white hover:bg-navy-deep">
                {{ __('common.save') }}
            </button>
            <a href="{{ route('admin.licenses.index') }}" class="text-sm text-grey-600 underline">{{ __('common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
