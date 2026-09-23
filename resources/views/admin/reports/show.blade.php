<x-layouts.admin :title="$report->asset->displayName()">
    <x-flash />

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="rounded border border-grey-200 bg-white p-4">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <x-status-badge :status="$report->status" />
                    @if ($report->reporter_type === \App\Enums\ReporterType::Student)
                        <span class="rounded bg-grey-100 px-1.5 py-0.5 text-xs text-grey-600">{{ __('reports.student_tag') }}</span>
                    @endif
                    <span class="font-mono text-xs text-grey-500">{{ $report->reference }}</span>
                </div>

                <p class="text-sm text-grey-600">
                    {{ __('reports.reported_by', ['name' => $report->reporter_name]) }}
                    <span class="rounded bg-grey-100 px-1.5 py-0.5 text-xs text-grey-500">{{ __('activity.self_reported') }}</span>
                </p>

                {{-- Never rendered unescaped. This text comes from an anonymous
                     stranger and is displayed on the director's screen
                     (spec section 13). --}}
                <p class="mt-4 text-base whitespace-pre-line">{{ $report->description }}</p>

                @if ($report->photo_path)
                    <img src="{{ route('photo', ['path' => $report->photo_path]) }}"
                         alt="{{ __('reports.photo_alt') }}"
                         class="mt-4 w-full max-w-md rounded border border-grey-200">
                @endif

                {{-- Plain words, not a table of timestamps (spec section 5). --}}
                <p class="mt-4 border-t border-grey-200 pt-3 text-sm text-grey-600 tabular-nums">
                    {{ __('reports.reported_ago', ['time' => $report->created_at->diffForHumans()]) }}
                    ·
                    {{ $report->first_viewed_at
                        ? __('reports.seen_after', ['time' => $report->created_at->diffForHumans($report->first_viewed_at, \Carbon\CarbonInterface::DIFF_ABSOLUTE)])
                        : __('reports.not_seen_yet') }}
                    ·
                    {{ $report->resolved_at
                        ? __('reports.fixed_after', ['time' => $report->created_at->diffForHumans($report->resolved_at, \Carbon\CarbonInterface::DIFF_ABSOLUTE)])
                        : __('reports.still_open') }}
                </p>

                @if ($report->resolution_note)
                    <p class="mt-3 rounded bg-grey-50 p-3 text-sm text-grey-700">{{ $report->resolution_note }}</p>
                @endif
            </div>

            {{-- Seeing that this projector was also repaired in March and
                 November turns a one-off into a replacement decision
                 (spec section 7B). --}}
            <h2 class="mt-6 mb-3 text-lg font-bold tracking-tight">{{ __('reports.past_reports') }}</h2>

            @if ($history->isEmpty())
                <p class="text-sm text-grey-500">{{ __('reports.no_past_reports') }}</p>
            @else
                <ul class="divide-y divide-grey-200 rounded border border-grey-200 bg-white">
                    @foreach ($history as $past)
                        <li class="flex min-h-tap flex-wrap items-center gap-x-3 gap-y-1 p-3 text-sm">
                            <a href="{{ route('admin.reports.show', $past) }}" class="min-w-0 flex-1 truncate text-navy underline">
                                {{ \Illuminate\Support\Str::limit($past->description, 70) }}
                            </a>
                            <span class="text-xs text-grey-500 tabular-nums">{{ $past->created_at->format('j M Y') }}</span>
                            <x-status-badge :status="$past->status" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="space-y-4">
            <div class="rounded border border-grey-200 bg-white p-4">
                <a href="{{ route('admin.assets.show', $report->asset) }}" class="block font-medium text-navy underline">
                    {{ $report->asset->displayName() }}
                </a>
                <p class="mt-1 text-sm text-grey-600">{{ $report->asset->room?->name }}</p>
                <p class="mt-1 font-mono text-xs text-grey-500">{{ $report->asset->asset_tag }}</p>
                <p class="mt-3"><x-status-badge :status="$report->asset->status" /></p>
            </div>

            {{-- Three buttons and a note box. Not a status dropdown with eight
                 options (spec section 7B). --}}
            @if ($report->isOpen())
                <div class="rounded border border-grey-200 bg-white p-4">
                    <form method="POST" action="{{ route('admin.reports.acknowledge', $report) }}" class="mb-3">
                        @csrf
                        <button type="submit" class="min-h-tap w-full rounded border border-grey-300 font-semibold hover:bg-grey-50">
                            {{ __('reports.acknowledge') }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.reports.resolve', $report) }}" id="resolve-form">
                        @csrf

                        <label for="resolution_note" class="mb-1 block text-sm font-medium text-grey-700">{{ __('reports.note') }}</label>
                        <textarea name="resolution_note" id="resolution_note" rows="2"
                                  placeholder="{{ __('reports.note_placeholder') }}"
                                  class="mb-3 block w-full rounded border border-grey-300 px-3 py-2 text-sm"></textarea>

                        {{-- Default silent and correct; the override is here for
                             the rare partial repair (spec section 7B). --}}
                        <details class="mb-3">
                            <summary class="cursor-pointer text-xs text-grey-600 underline">{{ __('reports.different_state') }}</summary>
                            <p class="mt-2 text-xs text-grey-500">{{ __('reports.different_state_help') }}</p>
                            <x-select name="status" :options="\App\Enums\AssetStatus::options()"
                                      :placeholder="__('common.not_set')" class="mt-2" />
                            <x-select name="condition" :options="\App\Enums\AssetCondition::options()"
                                      :placeholder="__('common.not_set')" class="mt-2" />
                        </details>

                        <button type="submit" class="mb-3 min-h-tap w-full rounded bg-navy font-semibold text-white hover:bg-navy-deep">
                            {{ __('reports.resolve') }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.reports.dismiss', $report) }}">
                        @csrf
                        <button type="submit" class="min-h-tap w-full text-sm text-grey-600 underline">
                            {{ __('reports.dismiss') }}
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-layouts.admin>
