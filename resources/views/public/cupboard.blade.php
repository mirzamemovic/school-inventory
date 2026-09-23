<x-layouts.public :title="__('supplies.cupboard')">
    @if (session('status'))
        <p role="status" class="mb-5 rounded border border-status-ok-soft bg-status-ok-soft px-4 py-3 font-semibold text-status-ok">
            {{ session('status') }}
        </p>
    @endif

    @if ($errors->any())
        <div role="alert" class="mb-5 rounded border border-status-broken-soft bg-status-broken-soft px-4 py-3 text-status-broken">
            <ul class="space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <h1 class="text-2xl leading-tight font-bold tracking-tight">{{ __('supplies.cupboard') }}</h1>
    <p class="mt-1 text-lg text-grey-700">{{ $room->name }}</p>

    @if ($supplies->isEmpty())
        <p class="mt-6 rounded border border-dashed border-grey-300 px-4 py-6 text-center text-grey-700">{{ __('supplies.cupboard_empty') }}</p>
    @else
        <ul class="mt-6 space-y-4">
            @foreach ($supplies as $supply)
                <li class="rounded border border-grey-300 p-4">
                    <p class="flex items-start justify-between gap-3">
                        <span class="text-lg font-semibold">{{ $supply->name }}</span>
                        <span class="text-right leading-tight">
                            <span class="block text-3xl font-bold tabular-nums">{{ $supply->quantity }}</span>
                            <span class="block text-sm text-grey-600">{{ $supply->unit->label() }}</span>
                        </span>
                    </p>

                    <form method="POST" action="{{ route('cupboard.take', [$room, $supply]) }}" class="mt-3 flex items-end gap-2">
                        @csrf
                        <span class="w-24 shrink-0">
                            <label for="take-{{ $supply->id }}" class="mb-1 block text-sm font-semibold text-grey-800">{{ __('supplies.how_many') }}</label>
                            <input type="number" name="quantity" id="take-{{ $supply->id }}" value="1" min="1" max="20" inputmode="numeric"
                                   class="block min-h-tap w-full rounded border border-grey-300 bg-white px-3 text-center text-lg tabular-nums">
                        </span>
                        <button type="submit" data-busy-text="{{ __('common.saving') }}"
                                class="flex min-h-tap flex-1 items-center justify-center rounded bg-navy px-4 text-lg font-semibold text-white hover:bg-navy-deep">
                            {{ __('supplies.took_some') }}
                        </button>
                    </form>

                    {{-- Reconciling what is actually on the shelf beats catching
                         every withdrawal (spec section 8). --}}
                    <details class="mt-3 border-t border-grey-200">
                        <summary class="disclosure flex min-h-tap cursor-pointer items-center font-medium text-grey-800">{{ __('supplies.recount') }}</summary>
                        <p class="text-sm text-grey-600">{{ __('supplies.recount_help') }}</p>
                        <form method="POST" action="{{ route('cupboard.recount', [$room, $supply]) }}" class="mt-2 flex items-end gap-2">
                            @csrf
                            <span class="w-24 shrink-0">
                                <label for="count-{{ $supply->id }}" class="mb-1 block text-sm font-semibold text-grey-800">{{ __('supplies.recount_label') }}</label>
                                <input type="number" name="quantity" id="count-{{ $supply->id }}" min="0" max="10000" inputmode="numeric"
                                       class="block min-h-tap w-full rounded border border-grey-300 bg-white px-3 text-center text-lg tabular-nums">
                            </span>
                            <button type="submit" data-busy-text="{{ __('common.saving') }}"
                                    class="flex min-h-tap flex-1 items-center justify-center rounded border border-grey-300 bg-white px-4 font-semibold hover:bg-grey-50">
                                {{ __('supplies.recount') }}
                            </button>
                        </form>
                    </details>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.public>
