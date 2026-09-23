<x-layouts.admin :title="__('people.import')">
    <x-flash />

    <div class="max-w-md">
        <p class="mb-4 text-grey-600">{{ __('people.import_help') }}</p>
        <p class="mb-6 rounded border border-grey-200 bg-grey-50 p-3 font-mono text-sm">{{ __('people.import_example') }}</p>

        <form method="POST" action="{{ route('admin.people.import') }}" enctype="multipart/form-data">
            @csrf

            <x-field :label="__('people.choose_file')" name="csv" required>
                <input type="file" name="csv" id="csv" accept=".csv,text/csv" class="block min-h-tap w-full text-sm" required>
            </x-field>

            <button type="submit" class="inline-flex min-h-tap items-center rounded bg-navy px-5 font-semibold text-white hover:bg-navy-deep">
                {{ __('people.import') }}
            </button>
        </form>
    </div>
</x-layouts.admin>
