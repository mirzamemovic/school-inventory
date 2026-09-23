@php
    // One look for every tap-chip on this page: a whole row to press, and an
    // unmistakable selected state that does not rely on colour alone.
    $chip = 'flex min-h-tap cursor-pointer items-center gap-3 rounded border border-grey-300 bg-white px-3 py-2 text-base
             has-checked:border-navy has-checked:bg-grey-50 has-checked:font-semibold has-checked:ring-1 has-checked:ring-navy';
@endphp
<x-layouts.public :title="__('reports.report_problem')">
    <h1 class="text-2xl leading-tight font-bold tracking-tight text-balance">{{ $name }}</h1>
    @if ($room)
        <p class="mt-1 text-grey-700">{{ __('public.in_room', ['room' => $room]) }}</p>
    @endif

    @if ($isAddition)
        <p class="mt-4 rounded border border-status-attention-soft bg-status-attention-soft px-4 py-3 font-semibold text-status-attention">
            {{ __('reports.already_reported', ['time' => $openReportAge]) }}
        </p>
    @endif

    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-status-broken-soft bg-status-broken-soft px-4 py-3 text-status-broken">
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
            <p class="mb-6 text-grey-700">{{ __('reports.reported_by', ['name' => $knownName]) }}</p>
        @else
            <fieldset class="mb-8">
                <legend class="text-lg font-bold">{{ __('reports.who_are_you') }}</legend>
                <p class="mt-1 mb-4 text-grey-700">{{ __('reports.who_help') }}</p>

                <x-field :label="__('reports.your_name')" name="reporter_name">
                    <x-input name="reporter_name" :value="old('reporter_name')" autocomplete="name" required />
                </x-field>

                <div class="space-y-2">
                    @foreach (\App\Enums\ReporterType::cases() as $type)
                        @continue($type === \App\Enums\ReporterType::Student && ! $studentsAllowed)
                        <label class="{{ $chip }}">
                            <input type="radio" name="reporter_type" value="{{ $type->value }}" class="size-5 shrink-0"
                                   @checked(old('reporter_type', 'staff') === $type->value) required>
                            {{ $type->label() }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endif

        <fieldset class="mb-8">
            <legend class="mb-3 text-lg font-bold">{{ __('reports.what_is_wrong') }}</legend>

            {{-- Three taps. Typing a paragraph on a phone is exactly where a
                 55-year-old teacher abandons the process (spec section 7A). --}}
            <div class="space-y-2">
                @foreach (['not_turning_on', 'damaged', 'missing'] as $problem)
                    <label class="{{ $chip }}">
                        <input type="radio" name="problem" value="{{ $problem }}" class="size-5 shrink-0"
                               @checked(old('problem') === $problem)>
                        {{ __('reports.problem.'.$problem) }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        <x-field :label="__('reports.anything_else')" name="description">
            <x-textarea name="description" :value="old('description')" />
        </x-field>

        <x-field :label="__('reports.add_photo')" name="photo">
            <input type="file" name="photo" id="photo" accept="image/jpeg,image/png,image/webp" capture="environment"
                   class="block w-full text-sm text-grey-700 file:mr-3 file:min-h-tap file:cursor-pointer file:rounded file:border file:border-grey-300 file:bg-white file:px-4 file:text-base file:font-semibold file:text-grey-900 hover:file:bg-grey-50">
        </x-field>

        {{-- Before the button, so it can be read before anything is sent. --}}
        <p class="mb-4 text-sm">
            <a href="{{ route('privacy') }}" class="text-grey-700 underline decoration-grey-400 underline-offset-2">{{ __('privacy.link') }}</a>
        </p>

        <button type="submit" data-busy-text="{{ __('common.sending') }}"
                class="flex min-h-14 w-full items-center justify-center rounded bg-status-broken px-4 text-lg font-semibold text-white hover:bg-status-broken/90">
            {{ $isAddition ? __('reports.add_information') : __('reports.send') }}
        </button>
    </form>
</x-layouts.public>
