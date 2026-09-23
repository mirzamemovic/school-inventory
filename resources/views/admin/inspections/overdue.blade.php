<x-layouts.admin :title="__('inspections.overdue_title')">
    <x-flash />

    @if ($assets->isEmpty())
        <x-empty-state :message="__('inspections.nothing_overdue')" />
    @else
        <ul class="divide-y divide-grey-200 rounded border border-grey-200 bg-white">
            @foreach ($assets as $asset)
                <li>
                    <a href="{{ route('admin.assets.show', $asset) }}"
                       class="flex min-h-tap flex-wrap items-center gap-x-3 gap-y-1 p-3 hover:bg-grey-50">
                        <span class="min-w-0 flex-1">
                            <span class="block font-medium">{{ $asset->displayName() }}</span>
                            <span class="block text-sm text-grey-500">{{ $asset->room?->name }}</span>
                        </span>

                        <span class="text-xs text-grey-500 tabular-nums">
                            {{ $asset->last_inspected_at
                                ? __('inspections.last_inspected', ['time' => $asset->last_inspected_at->diffForHumans()])
                                : __('inspections.never_inspected') }}
                        </span>

                        <span class="rounded bg-status-attention-soft px-2 py-0.5 text-xs font-semibold text-status-attention">
                            {{ __('inspections.overdue') }}
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.admin>
