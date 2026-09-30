@php
    $asset = $report->asset;
    $card = 'rounded border border-grey-200 bg-white';
@endphp
<x-layouts.admin :title="$asset->displayName()" :back="route('admin.reports.index')" :back-label="__('reports.title')">
    <x-slot:subtitle>
        <p class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-sm text-grey-600">
            <x-status-badge :status="$report->status" />
            @if ($report->reporter_type === \App\Enums\ReporterType::Student)
                <span class="rounded bg-grey-100 px-1.5 py-0.5 text-xs text-grey-700">{{ __('reports.student_tag') }}</span>
            @endif
            <span class="font-mono">{{ $report->reference }}</span>
        </p>

        {{-- Plain words, not a table of timestamps (spec section 5). --}}
        <p class="mt-2 text-grey-700 tabular-nums">
            {{ __('reports.reported_ago', ['time' => $report->created_at->diffForHumans()]) }}
            <span aria-hidden="true" class="text-grey-400">·</span>
            {{ $report->first_viewed_at
                ? __('reports.seen_after', ['time' => $report->created_at->diffForHumans($report->first_viewed_at, \Carbon\CarbonInterface::DIFF_ABSOLUTE)])
                : __('reports.not_seen_yet') }}
            <span aria-hidden="true" class="text-grey-400">·</span>
            {{ $report->resolved_at
                ? __('reports.fixed_after', ['time' => $report->created_at->diffForHumans($report->resolved_at, \Carbon\CarbonInterface::DIFF_ABSOLUTE)])
                : __('reports.still_open') }}
        </p>
    </x-slot:subtitle>

    <x-flash />

    {{--
        The problem, then the decision, then the item, then its history. On a
        phone that is the order down the page, so the three buttons are never
        below a long history.
    --}}
    <div class="flex flex-col gap-4 lg:grid lg:grid-cols-3 lg:items-start">
        <div class="contents lg:col-span-2 lg:flex lg:flex-col lg:gap-4">
            <section class="order-1 {{ $card }} p-4 sm:p-5">
                <p class="flex flex-wrap items-center gap-2 text-sm text-grey-600">
                    {{ __('reports.reported_by', ['name' => $report->reporter_name]) }}
                    <span class="rounded bg-grey-100 px-1.5 py-0.5 text-xs text-grey-700">{{ __('activity.self_reported') }}</span>
                </p>

                {{-- Never rendered unescaped. This text comes from an anonymous
                     stranger and is displayed on the director's screen
                     (spec section 13). --}}
                @if (filled($report->description))
                    <p class="mt-3 text-lg leading-relaxed whitespace-pre-line">{{ $report->description }}</p>
                @else
                    <p class="mt-3 text-grey-600">{{ __('reports.no_description') }}</p>
                @endif

                @if ($report->photo_path)
                    <img src="{{ route('photo', ['path' => $report->photo_path]) }}"
                         alt="{{ __('reports.photo_alt') }}"
                         loading="lazy"
                         class="mt-4 w-full max-w-md rounded border border-grey-200">
                @endif

                @if ($report->resolution_note)
                    <div class="mt-4 border-t border-grey-200 pt-4">
                        <h2 class="text-sm font-semibold text-grey-600">{{ __('reports.resolution_heading') }}</h2>
                        <p class="mt-1 whitespace-pre-line text-grey-900">{{ $report->resolution_note }}</p>
                    </div>
                @endif
            </section>

            {{-- Seeing that this projector was also repaired in March and
                 November turns a one-off into a replacement decision
                 (spec section 7B). --}}
            <section class="order-4">
                <h2 class="mb-3 text-lg font-bold tracking-tight">{{ __('reports.past_reports') }}</h2>

                @if ($history->isEmpty())
                    <p class="text-sm text-grey-600">{{ __('reports.no_past_reports') }}</p>
                @else
                    <ul class="divide-y divide-grey-200 {{ $card }}">
                        @foreach ($history as $past)
                            <li>
                                <a href="{{ route('admin.reports.show', $past) }}" class="flex min-h-tap items-center gap-3 px-3 py-2.5 hover:bg-grey-50 sm:px-4">
                                    <span class="w-24 shrink-0 text-sm text-grey-600 tabular-nums">{{ $past->created_at->format('j M Y') }}</span>
                                    <span class="min-w-0 flex-1 truncate">{{ \Illuminate\Support\Str::limit($past->description, 70) }}</span>
                                    <x-status-badge :status="$past->status" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <div class="contents lg:flex lg:flex-col lg:gap-4">
            {{-- Three buttons and a note box. Not a status dropdown with eight
                 options (spec section 7B). --}}
            @if ($report->isOpen())
                <section class="order-2 {{ $card }}">
                    <div class="border-b border-grey-200 p-4">
                        @if ($report->status === \App\Enums\ReportStatus::InProgress && $report->acknowledged_at)
                            <p class="mb-3 text-sm text-grey-700">{{ __('reports.being_worked_on', ['time' => $report->acknowledged_at->diffForHumans()]) }}</p>
                        @endif

                        <form method="POST" action="{{ route('admin.reports.acknowledge', $report) }}">
                            @csrf
                            <button type="submit" class="btn-secondary w-full">
                                {{ __('reports.acknowledge') }}
                            </button>
                        </form>
                    </div>

                    {{-- One note box for both ways of closing, so whatever was
                         typed is kept whichever button is pressed. --}}
                    <form method="POST" action="{{ route('admin.reports.resolve', $report) }}" class="p-4">
                        @csrf
                        <h2 class="font-bold">{{ __('reports.close_heading') }}</h2>
                        <p class="mt-1 mb-4 text-sm text-grey-600">{{ __('reports.close_help') }}</p>

                        {{-- Shown on the public /r/{reference} page, so the
                             director is told before writing, not after. --}}
                        <x-field :label="__('reports.note')" name="resolution_note" :help="__('reports.note_help')">
                            <x-textarea name="resolution_note" rows="2" :value="old('resolution_note')"
                                        :placeholder="__('reports.note_placeholder')" />
                        </x-field>

                        {{-- Default silent and correct; the override is here for
                             the rare partial repair (spec section 7B). --}}
                        <details class="-mt-2 mb-4">
                            <summary class="disclosure flex min-h-tap cursor-pointer items-center gap-2 text-sm font-medium text-grey-700">
                                {{ __('reports.different_state') }}
                            </summary>
                            <p class="mb-3 text-sm text-grey-600">{{ __('reports.different_state_help') }}</p>

                            <label for="override-status" class="mb-1 block text-sm font-semibold text-grey-800">{{ __('assets.labels.status') }}</label>
                            <x-select name="status" id="override-status" :options="\App\Enums\AssetStatus::options()"
                                      :placeholder="__('common.not_set')" class="mb-3" />

                            <label for="override-condition" class="mb-1 block text-sm font-semibold text-grey-800">{{ __('assets.labels.condition') }}</label>
                            <x-select name="condition" id="override-condition" :options="\App\Enums\AssetCondition::options()"
                                      :placeholder="__('common.not_set')" />
                        </details>

                        <button type="submit" class="btn-primary w-full">
                            {{ __('reports.resolve') }}
                        </button>

                        <button type="submit" formaction="{{ route('admin.reports.dismiss', $report) }}"
                                class="mt-2 inline-flex min-h-tap w-full items-center justify-center text-grey-700 underline decoration-grey-400 underline-offset-2 hover:text-grey-900">
                            {{ __('reports.dismiss') }}
                        </button>
                    </form>
                </section>
            @endif

            <section class="order-3 {{ $card }} p-4">
                <h2 class="text-sm font-semibold text-grey-600">{{ __('reports.the_item') }}</h2>
                <a href="{{ route('admin.assets.show', $asset) }}" class="link mt-1 block text-lg leading-snug font-semibold">
                    {{ $asset->displayName() }}
                </a>
                <p class="mt-1 flex flex-wrap gap-x-3 text-sm text-grey-600">
                    <span class="font-mono">{{ $asset->asset_tag }}</span>
                    @if ($asset->room)
                        <span>{{ $asset->room->name }}</span>
                    @endif
                </p>
                <p class="mt-3 flex flex-wrap gap-2">
                    <x-status-badge :status="$asset->status" />
                    @if ($asset->condition)
                        <x-status-badge :status="$asset->condition" />
                    @endif
                </p>
            </section>
        </div>
    </div>
</x-layouts.admin>
