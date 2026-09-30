<x-layouts.admin :title="__('inspections.overdue_title')" :back="route('admin.home')" :back-label="__('home.title')">
    <x-flash />

    @if ($assets->isEmpty())
        <div class="rounded border border-status-ok-soft bg-status-ok-soft px-4 py-5">
            <p class="font-semibold text-status-ok">{{ __('inspections.nothing_overdue') }}</p>
        </div>
    @else
        <ul class="divide-y divide-grey-200 rounded border border-grey-200 bg-white">
            @foreach ($assets as $asset)
                <li>
                    <a href="{{ route('admin.assets.show', $asset) }}"
                       class="flex min-h-tap items-center gap-3 px-3 py-2.5 hover:bg-grey-50 sm:px-4">
                        <span class="min-w-0 flex-1">
                            <span class="block font-semibold">{{ $asset->displayName() }}</span>
                            <span class="block text-sm text-grey-600 tabular-nums">
                                {{ collect([
                                    $asset->room?->name,
                                    $asset->last_inspected_at
                                        ? __('inspections.last_inspected', ['time' => $asset->last_inspected_at->diffForHumans()])
                                        : __('inspections.never_inspected'),
                                ])->filter()->join(' · ') }}
                            </span>
                            {{-- Someone did look, just not on a phone that was
                                 set up: worth knowing, not worth counting. --}}
                            @if ($asset->latestInspection && ! $asset->latestInspection->counted)
                                <span class="mt-0.5 block text-sm text-grey-700">
                                    {{ __('inspections.unconfirmed_check', [
                                        'name' => $asset->latestInspection->inspector_name,
                                        'time' => $asset->latestInspection->inspected_at->diffForHumans(),
                                    ]) }}
                                </span>
                            @endif
                        </span>

                        <span class="shrink-0 rounded bg-status-attention-soft px-2 py-0.5 text-xs font-semibold text-status-attention">
                            {{ __('inspections.overdue') }}
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.admin>
