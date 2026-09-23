<x-layouts.public :title="__('reports.check_status')">
    @if (session('status'))
        <p role="status" class="mb-5 rounded border border-status-ok-soft bg-status-ok-soft px-4 py-3 text-base font-semibold text-status-ok">
            {{ session('status') }}
        </p>
    @endif

    <h1 class="text-2xl leading-tight font-bold tracking-tight text-balance">{{ $assetName }}</h1>

    <p class="mt-3">
        <span class="inline-flex items-center rounded px-2.5 py-1 text-sm font-semibold {{ $report->status->badgeClasses() }}">
            {{ $report->status->label() }}
        </span>
    </p>

    <p class="mt-4 text-lg text-grey-800">
        @switch(true)
            @case($report->status === \App\Enums\ReportStatus::Resolved)
                {{ __('reports.status_resolved') }}
                @break
            @case($report->status === \App\Enums\ReportStatus::Dismissed)
                {{ __('reports.status_dismissed') }}
                @break
            @case($report->status === \App\Enums\ReportStatus::InProgress)
                {{ __('reports.status_in_progress') }}
                @break
            @case($report->first_viewed_at !== null)
                {{ __('reports.status_seen') }}
                @break
            @default
                {{ __('reports.status_open') }}
        @endswitch
    </p>

    @if ($report->resolution_note)
        <p class="mt-3 rounded border border-grey-200 bg-grey-50 px-4 py-3 text-grey-800">{{ $report->resolution_note }}</p>
    @endif

    <p class="mt-6 text-grey-600 tabular-nums">
        {{ __('reports.reported_ago', ['time' => $report->created_at->diffForHumans()]) }}
    </p>

    <p class="mt-8 text-sm text-grey-600">
        {{ __('reports.reference') }}
        <span class="ml-1 font-mono text-base font-semibold tracking-wide text-grey-900">{{ $report->reference }}</span>
    </p>
</x-layouts.public>
