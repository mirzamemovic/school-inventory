<x-layouts.admin :title="__('reports.title')">
    <x-flash />

    {{-- Opens on Open, sorted oldest first. Rows open more than seven days
         turn red (spec section 7B). --}}
    <nav class="mb-4 flex flex-wrap gap-1 border-b border-grey-200" aria-label="{{ __('reports.title') }}">
        @foreach (['open', 'in_progress', 'closed'] as $name)
            <a href="{{ route('admin.reports.index', ['tab' => $name]) }}"
               @if ($tab === $name) aria-current="page" @endif
               class="inline-flex min-h-tap items-center border-b-2 px-3 text-sm font-medium
                      {{ $tab === $name ? 'border-navy text-navy' : 'border-transparent text-grey-600 hover:text-grey-900' }}">
                {{ __('reports.tabs.'.$name) }}
                <span class="ml-2 text-xs text-grey-500 tabular-nums">{{ $counts[$name] }}</span>
            </a>
        @endforeach
    </nav>

    @if ($reports->isEmpty())
        <x-empty-state :message="__('reports.queue_empty')" />
    @else
        <ul class="divide-y divide-grey-200 rounded border border-grey-200 bg-white">
            @foreach ($reports as $report)
                <li>
                    <a href="{{ route('admin.reports.show', $report) }}"
                       class="flex min-h-tap flex-wrap items-center gap-x-3 gap-y-1 p-3 hover:bg-grey-50
                              {{ $report->isStale() ? 'border-l-4 border-status-broken' : '' }}">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium">{{ $report->asset->displayName() }}</span>
                            <span class="block truncate text-sm text-grey-500">
                                {{ collect([$report->asset->room?->name, $report->reporter_name])->filter()->join(' · ') }}
                            </span>
                        </span>

                        @if ($report->reporter_type === \App\Enums\ReporterType::Student)
                            <span class="rounded bg-grey-100 px-1.5 py-0.5 text-xs text-grey-600">{{ __('reports.student_tag') }}</span>
                        @endif

                        <span class="text-xs whitespace-nowrap tabular-nums {{ $report->isStale() ? 'font-semibold text-status-broken' : 'text-grey-500' }}">
                            {{ __('reports.reported_ago', ['time' => $report->created_at->diffForHumans()]) }}
                        </span>

                        <x-status-badge :status="$report->status" />
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="mt-4">{{ $reports->links() }}</div>
    @endif
</x-layouts.admin>
