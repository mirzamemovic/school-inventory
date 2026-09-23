<x-layouts.public :title="__('inspections.log')">
    <h1 class="text-2xl leading-tight font-bold tracking-tight text-balance">{{ $name }}</h1>
    @if ($room)
        <p class="mt-1 text-grey-700">{{ __('public.in_room', ['room' => $room]) }}</p>
    @endif

    <p class="mt-2 text-grey-600 tabular-nums">
        {{ $lastInspectedAt
            ? __('inspections.last_inspected', ['time' => $lastInspectedAt->diffForHumans()])
            : __('inspections.never_inspected') }}
    </p>

    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-status-broken-soft bg-status-broken-soft px-4 py-3 text-status-broken">
            <ul class="space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <h2 class="mt-8 text-lg font-bold">{{ __('inspections.heading') }}</h2>

    <form method="POST" action="{{ route('inspection.store', $tag) }}" class="mt-4">
        @csrf

        <x-field :label="__('inspections.inspector')" name="inspector_name">
            <x-input name="inspector_name" :value="old('inspector_name', $knownName)" autocomplete="name" required />
        </x-field>

        <x-field :label="__('inspections.notes')" name="notes">
            <x-textarea name="notes" rows="2" :value="old('notes')" />
        </x-field>

        {{-- Two buttons. The whole point of the QR on a fire extinguisher is
             that checking it takes one tap (spec section 4). --}}
        <button type="submit" name="result" value="pass" data-busy-text="{{ __('common.saving') }}"
                class="mt-2 mb-3 flex min-h-14 w-full items-center justify-center rounded bg-status-ok px-4 py-3 text-lg font-semibold text-white hover:bg-status-ok/90">
            {{ __('inspections.checked_ok') }}
        </button>

        <button type="submit" name="result" value="fail" data-busy-text="{{ __('common.saving') }}"
                class="flex min-h-14 w-full items-center justify-center rounded border-2 border-status-broken bg-white px-4 py-3 text-lg font-semibold text-status-broken hover:bg-status-broken-soft">
            {{ __('inspections.checked_problem') }}
        </button>
    </form>
</x-layouts.public>
