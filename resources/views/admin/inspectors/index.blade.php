@php
    $statusOf = fn (\App\Models\InspectorPhone $phone) => match (true) {
        $phone->withdrawn_at !== null => 'withdrawn',
        $phone->isActive() => 'active',
        $phone->isClaimable() => 'waiting',
        default => 'expired',
    };

    $badge = [
        'active' => 'bg-status-ok-soft text-status-ok',
        'waiting' => 'bg-status-attention-soft text-status-attention',
        'withdrawn' => 'bg-status-inactive-soft text-status-inactive',
        'expired' => 'bg-status-inactive-soft text-status-inactive',
    ];
@endphp
<x-layouts.admin :title="__('inspections.phones.title')" :back="route('admin.manage')" :back-label="__('manage.title')">
    <x-flash />

    <p class="mb-6 max-w-prose text-grey-700">{{ __('inspections.phones.help') }}</p>

    @if ($phones->isEmpty())
        <x-empty-state class="mb-8" :message="__('inspections.phones.none')" />
    @else
        <ul class="mb-8 divide-y divide-grey-200 rounded border border-grey-200 bg-white">
            @foreach ($phones as $phone)
                @php $status = $statusOf($phone); @endphp
                <li class="flex flex-wrap items-center gap-x-5 gap-y-1 px-3 py-3 sm:px-4">
                    <span class="min-w-0 flex-1 basis-56">
                        <a href="{{ route('admin.inspectors.show', $phone) }}" class="link block font-semibold text-grey-900">{{ $phone->label }}</a>
                        <span class="block text-sm text-grey-600 tabular-nums">
                            @if ($status === 'active')
                                {{ __('inspections.phones.set_up_on', ['date' => $phone->claimed_at->format('j M Y')]) }}
                                ·
                                {{ $phone->last_used_at
                                    ? __('inspections.phones.last_used', ['time' => $phone->last_used_at->diffForHumans()])
                                    : __('inspections.phones.never_used') }}
                            @elseif ($status === 'withdrawn')
                                {{ __('inspections.phones.withdrawn_on', ['date' => $phone->withdrawn_at->format('j M Y')]) }}
                            @endif
                        </span>
                    </span>

                    <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $badge[$status] }}">
                        {{ __('inspections.phones.status.'.$status) }}
                    </span>
                </li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('admin.inspectors.store') }}" class="max-w-md rounded border border-grey-200 bg-white p-4">
        @csrf
        <h2 class="mb-3 text-lg font-bold">{{ __('inspections.phones.add') }}</h2>

        <x-field :label="__('inspections.phones.label')" name="label" :help="__('inspections.phones.label_help')" required>
            <x-input name="label" :value="old('label')" maxlength="80" required />
        </x-field>

        <button type="submit" class="btn-primary" data-busy-text="{{ __('common.working') }}">
            {{ __('inspections.phones.make_code') }}
        </button>
    </form>
</x-layouts.admin>
