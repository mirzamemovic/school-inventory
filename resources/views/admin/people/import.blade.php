<x-layouts.admin :title="__('people.import')" :back="route('admin.people.index')" :back-label="__('people.title')">
    <x-flash />

    <div class="max-w-md">
        <p class="mb-3 text-grey-700">{{ __('people.import_help') }}</p>
        <p class="mb-6 rounded border border-grey-200 bg-white p-3 font-mono text-sm">{{ __('people.import_example') }}</p>

        <form method="POST" action="{{ route('admin.people.import') }}" enctype="multipart/form-data">
            @csrf

            <x-field :label="__('people.choose_file')" name="csv" required>
                <input type="file" name="csv" id="csv" accept=".csv,text/csv" required
                       class="block w-full text-sm text-grey-700 file:mr-3 file:min-h-tap file:cursor-pointer file:rounded file:border file:border-grey-300 file:bg-white file:px-4 file:font-semibold file:text-grey-900 hover:file:bg-grey-50">
            </x-field>

            <button type="submit" class="btn-primary" data-busy-text="{{ __('common.working') }}">
                {{ __('people.import') }}
            </button>
        </form>
    </div>
</x-layouts.admin>
