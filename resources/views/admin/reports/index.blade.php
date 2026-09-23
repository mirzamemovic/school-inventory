<x-layouts.admin :title="__('reports.title')">
    <x-flash />

    {{-- Opens on Open, sorted oldest first. Rows open more than seven days
         turn red (spec section 7B). --}}
    <nav class="mb-4 flex overflow-x-auto shadow-[inset_0_-1px_0_var(--color-grey-200)] sm:gap-1" aria-label="{{ __('reports.title') }}">
        @foreach (['open', 'in_progress', 'closed'] as $name)
            <a href="{{ route('admin.reports.index', ['tab' => $name]) }}"
               @if ($tab === $name) aria-current="page" @endif
               class="inline-flex min-h-tap shrink-0 items-center gap-1.5 border-b-2 px-2 text-sm whitespace-nowrap sm:px-3 focus-visible:outline-offset-[-2px]
                      {{ $tab === $name ? 'border-navy font-semibold text-navy' : 'border-transparent font-medium text-grey-600 hover:border-grey-300 hover:text-grey-900' }}">
                {{ __('reports.tabs.'.$name) }}
                <span class="rounded-full px-1.5 py-0.5 text-xs leading-none tabular-nums {{ $tab === $name ? 'bg-navy text-white' : 'bg-grey-100 text-grey-700' }}">{{ $counts[$name] }}</span>
            </a>
        @endforeach
    </nav>

    @if ($reports->isEmpty())
        <x-empty-state :message="__('reports.queue_empty.'.$tab)" />
    @else
        <ul class="divide-y divide-grey-200 rounded border border-grey-200 bg-white">
            @foreach ($reports as $report)
                @php
                    $stale = $report->isStale();
                    // The first line is the tap-chip the reporter chose, or the
                    // start of what they typed: enough to triage from the list.
                    $problem = \Illuminate\Support\Str::of($report->description)->trim()->before("\n")->limit(90)->toString();
                @endphp
                <li>
                    <a href="{{ route('admin.reports.show', $report) }}"
                       class="flex gap-3 py-3 pr-3 hover:bg-grey-50 sm:pr-4 {{ $stale ? 'border-l-4 border-status-broken pl-2 sm:pl-3' : 'pl-3 sm:pl-4' }}">
                        <span class="min-w-0 flex-1">
                            <span class="block font-semibold">{{ $report->asset->displayName() }}</span>
                            @if ($problem !== '')
                                <span class="mt-0.5 block truncate text-grey-800">{{ $problem }}</span>
                            @endif
                            <span class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-grey-600">
                                @if ($report->asset->room)
                                    <span>{{ $report->asset->room->name }}</span>
                                @endif
                                <span>{{ $report->reporter_name }}</span>
                                @if ($report->reporter_type === \App\Enums\ReporterType::Student)
                                    <span class="rounded bg-grey-100 px-1.5 py-0.5 text-xs text-grey-700">{{ __('reports.student_tag') }}</span>
                                @endif
                            </span>
                        </span>

                        <span class="flex shrink-0 flex-col items-end gap-1 text-right">
                            <x-status-badge :status="$report->status" />
                            <span class="text-xs tabular-nums {{ $stale ? 'font-semibold text-status-broken' : 'text-grey-600' }}">
                                {{ __('reports.reported_ago', ['time' => $report->created_at->diffForHumans()]) }}
                            </span>
                            @if ($report->isOpen() && $report->first_viewed_at === null)
                                <span class="text-xs font-semibold text-grey-900">{{ __('reports.not_seen_yet') }}</span>
                            @endif
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="mt-4">{{ $reports->links() }}</div>
    @endif
</x-layouts.admin>
