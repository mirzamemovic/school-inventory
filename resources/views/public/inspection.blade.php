<x-layouts.public :title="__('inspections.log')">
    <h1 class="text-2xl font-bold tracking-tight">{{ $name }}</h1>
    @if ($room)
        <p class="mt-1 text-grey-600">{{ __('public.in_room', ['room' => $room]) }}</p>
    @endif

    <p class="mt-2 text-sm text-grey-500 tabular-nums">
        {{ $lastInspectedAt
            ? __('inspections.last_inspected', ['time' => $lastInspectedAt->diffForHumans()])
            : __('inspections.never_inspected') }}
    </p>

    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-status-broken-soft bg-status-broken-soft px-3 py-2 text-sm text-status-broken">
            <ul class="space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <h2 class="mt-8 text-lg font-semibold">{{ __('inspections.heading') }}</h2>

    <form method="POST" action="{{ route('inspection.store', $tag) }}" class="mt-4">
        @csrf

        <label for="inspector_name" class="mb-1 block text-sm font-medium text-grey-700">
            {{ __('inspections.inspector') }}
        </label>
        <x-input name="inspector_name" :value="old('inspector_name', $knownName)" required class="mb-4" />

        <label for="notes" class="mb-1 block text-sm font-medium text-grey-700">{{ __('inspections.notes') }}</label>
        <textarea name="notes" id="notes" rows="2"
                  class="mb-6 block w-full rounded border border-grey-300 bg-white px-3 py-2 text-base">{{ old('notes') }}</textarea>

        {{-- Two buttons. The whole point of the QR on a fire extinguisher is
             that checking it takes one tap (spec section 4). --}}
        <button type="submit" name="result" value="pass"
                class="mb-3 flex min-h-tap w-full items-center justify-center rounded bg-status-ok px-4 py-3 text-lg font-semibold text-white">
            {{ __('inspections.checked_ok') }}
        </button>

        <button type="submit" name="result" value="fail"
                class="flex min-h-tap w-full items-center justify-center rounded border-2 border-status-broken px-4 py-3 text-base font-semibold text-status-broken">
            {{ __('inspections.checked_problem') }}
        </button>
    </form>
</x-layouts.public>
