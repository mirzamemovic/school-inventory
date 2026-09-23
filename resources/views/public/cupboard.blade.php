<x-layouts.public :title="__('supplies.cupboard')">
    @if (session('status'))
        <p role="status" class="mb-5 rounded border border-status-ok-soft bg-status-ok-soft px-3 py-3 font-medium text-status-ok">
            {{ session('status') }}
        </p>
    @endif

    @if ($errors->any())
        <div role="alert" class="mb-5 rounded border border-status-broken-soft bg-status-broken-soft px-3 py-2 text-sm text-status-broken">
            <ul class="space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <h1 class="text-2xl font-bold tracking-tight">{{ __('supplies.cupboard') }}</h1>
    <p class="mt-1 text-lg text-grey-600">{{ $room->name }}</p>

    @if ($supplies->isEmpty())
        <p class="mt-6 text-grey-600">{{ __('supplies.cupboard_empty') }}</p>
    @else
        <ul class="mt-6 space-y-4">
            @foreach ($supplies as $supply)
                <li class="rounded border border-grey-200 p-4">
                    <p class="flex items-baseline justify-between gap-3">
                        <span class="font-semibold">{{ $supply->name }}</span>
                        <span class="text-2xl font-bold tabular-nums">{{ $supply->quantity }}</span>
                    </p>
                    <p class="text-sm text-grey-500">{{ $supply->unit->label() }}</p>

                    <form method="POST" action="{{ route('cupboard.take', [$room, $supply]) }}" class="mt-3 flex items-end gap-2">
                        @csrf
                        <span class="flex-1">
                            <label for="take-{{ $supply->id }}" class="mb-1 block text-sm text-grey-700">{{ __('supplies.how_many') }}</label>
                            <input type="number" name="quantity" id="take-{{ $supply->id }}" value="1" min="1" max="20"
                                   class="block min-h-tap w-full rounded border border-grey-300 px-3 text-base tabular-nums">
                        </span>
                        <button type="submit" class="min-h-tap rounded bg-navy px-4 font-semibold text-white">
                            {{ __('supplies.took_some') }}
                        </button>
                    </form>

                    {{-- Reconciling what is actually on the shelf beats catching
                         every withdrawal (spec section 8). --}}
                    <details class="mt-3">
                        <summary class="cursor-pointer text-sm text-navy underline">{{ __('supplies.recount') }}</summary>
                        <p class="mt-2 text-xs text-grey-600">{{ __('supplies.recount_help') }}</p>
                        <form method="POST" action="{{ route('cupboard.recount', [$room, $supply]) }}" class="mt-2 flex items-end gap-2">
                            @csrf
                            <span class="flex-1">
                                <label for="count-{{ $supply->id }}" class="mb-1 block text-sm text-grey-700">{{ __('supplies.recount_label') }}</label>
                                <input type="number" name="quantity" id="count-{{ $supply->id }}" min="0" max="10000"
                                       class="block min-h-tap w-full rounded border border-grey-300 px-3 text-base tabular-nums">
                            </span>
                            <button type="submit" class="min-h-tap rounded border border-grey-300 px-4 font-semibold">
                                {{ __('supplies.recount') }}
                            </button>
                        </form>
                    </details>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.public>
