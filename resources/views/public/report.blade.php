<x-layouts.public :title="__('reports.report_problem')">
    <h1 class="text-2xl font-bold tracking-tight">{{ $name }}</h1>
    @if ($room)
        <p class="mt-1 text-grey-600">{{ __('public.in_room', ['room' => $room]) }}</p>
    @endif

    @if ($isAddition)
        <p class="mt-4 rounded border border-status-attention-soft bg-status-attention-soft p-3 text-sm font-medium text-status-attention">
            {{ __('reports.already_reported', ['time' => $openReportAge]) }}
        </p>
    @endif

    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-status-broken-soft bg-status-broken-soft px-3 py-2 text-sm text-status-broken">
            <ul class="space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('report.store', $tag) }}" enctype="multipart/form-data" class="mt-6">
        @csrf

        @if ($knownName)
            {{-- Asked once, then remembered on the device for a year. --}}
            <input type="hidden" name="reporter_name" value="{{ $knownName }}">
            <input type="hidden" name="reporter_type" value="{{ $knownType?->value ?? 'other' }}">
            <p class="mb-6 text-sm text-grey-600">{{ __('reports.reported_by', ['name' => $knownName]) }}</p>
        @else
            <fieldset class="mb-6">
                <legend class="text-lg font-semibold">{{ __('reports.who_are_you') }}</legend>
                <p class="mt-1 mb-3 text-sm text-grey-600">{{ __('reports.who_help') }}</p>

                <label for="reporter_name" class="mb-1 block text-sm font-medium text-grey-700">
                    {{ __('reports.fields.reporter_name') }}
                </label>
                <x-input name="reporter_name" :value="old('reporter_name')" autocomplete="name" required class="mb-3" />

                @foreach (\App\Enums\ReporterType::cases() as $type)
                    @continue($type === \App\Enums\ReporterType::Student && ! $studentsAllowed)
                    <label class="flex min-h-tap items-center gap-3 text-base">
                        <input type="radio" name="reporter_type" value="{{ $type->value }}" class="size-5"
                               @checked(old('reporter_type', 'staff') === $type->value) required>
                        {{ $type->label() }}
                    </label>
                @endforeach
            </fieldset>
        @endif

        <fieldset class="mb-6">
            <legend class="mb-2 text-lg font-semibold">{{ __('reports.what_is_wrong') }}</legend>

            {{-- Three taps. Typing a paragraph on a phone is exactly where a
                 55-year-old teacher abandons the process (spec section 7A). --}}
            <div class="space-y-2">
                @foreach (['not_turning_on', 'damaged', 'missing'] as $problem)
                    <label class="flex min-h-tap cursor-pointer items-center gap-3 rounded border border-grey-300 bg-white px-3 text-base has-checked:border-navy has-checked:bg-grey-50 has-checked:font-semibold">
                        <input type="radio" name="problem" value="{{ $problem }}" class="size-5"
                               @checked(old('problem') === $problem)>
                        {{ __('reports.problem.'.$problem) }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="mb-6">
            <label for="description" class="mb-1 block text-sm font-medium text-grey-700">
                {{ __('reports.anything_else') }}
            </label>
            <textarea name="description" id="description" rows="3"
                      class="block w-full rounded border border-grey-300 bg-white px-3 py-2 text-base">{{ old('description') }}</textarea>
        </div>

        <div class="mb-8">
            <label for="photo" class="mb-1 block text-sm font-medium text-grey-700">{{ __('reports.add_photo') }}</label>
            <input type="file" name="photo" id="photo" accept="image/jpeg,image/png,image/webp" capture="environment"
                   class="block min-h-tap w-full text-sm">
        </div>

        <p class="mb-4 text-sm">
            <a href="{{ route('privacy') }}" class="text-grey-600 underline">{{ __('privacy.link') }}</a>
        </p>

        <button type="submit"
                class="flex min-h-tap w-full items-center justify-center rounded bg-status-broken px-4 text-lg font-semibold text-white">
            {{ $isAddition ? __('reports.add_information') : __('reports.send') }}
        </button>
    </form>
</x-layouts.public>
