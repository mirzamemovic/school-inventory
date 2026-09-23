{{--
    Photo, item name, room, status badge, report button. Nothing else.

    This page is enumerable by anyone who scans one sticker, so it is handed a
    PublicAssetView and never the Asset model: price, supplier, serial number,
    warranty and repair history cannot leak from here even by accident
    (spec section 13).
--}}
<x-layouts.public :title="$asset->name">
    @if (session('status'))
        <p role="status" class="mb-5 rounded border border-status-ok-soft bg-status-ok-soft px-3 py-3 font-medium text-status-ok">
            {{ session('status') }}
        </p>
    @endif

    @if ($asset->photoUrl)
        <img src="{{ $asset->photoUrl }}"
             alt="{{ __('assets.photo_alt', ['name' => $asset->name]) }}"
             class="mb-5 aspect-video w-full rounded object-cover">
    @endif

    <h1 class="text-3xl leading-tight font-bold tracking-tight">{{ $asset->name }}</h1>

    <p class="mt-1 text-lg text-grey-600">
        {{ $asset->room ? __('public.in_room', ['room' => $asset->room]) : __('public.no_room') }}
    </p>

    <p class="mt-3">
        <span class="inline-flex items-center rounded px-2 py-1 text-sm font-semibold {{ $asset->statusClasses }}">
            {{ $asset->statusLabel }}
        </span>
    </p>

    @if ($asset->isBlank)
        <div class="mt-6 rounded border border-status-attention-soft bg-status-attention-soft p-4">
            <p class="font-semibold text-status-attention">{{ __('public.blank_heading') }}</p>
            <p class="mt-1 text-sm text-status-attention">{{ __('public.blank_help') }}</p>
        </div>
    @else
        {{-- The banner sits above the button on purpose. This single element
             eliminates most duplicate reports (spec section 7A). --}}
        @if ($asset->hasOpenReport)
            <p class="mt-6 rounded border border-status-attention-soft bg-status-attention-soft p-3 font-medium text-status-attention">
                {{ __('reports.already_reported', ['time' => $asset->openReportAge]) }}
            </p>
        @endif

        <a href="{{ route('report.create', $tag) }}"
           class="mt-4 flex min-h-tap w-full items-center justify-center rounded bg-status-broken px-4 py-3 text-lg font-semibold text-white">
            {{ $asset->hasOpenReport ? __('reports.add_information') : __('reports.report_problem') }}
        </a>

        {{-- Compliance items are an inspection schedule, not inventory: the
             QR is an "I checked this and it's fine" button (spec section 4). --}}
        @if ($asset->needsInspection)
            <a href="{{ route('inspection.create', $tag) }}"
               class="mt-3 flex min-h-tap w-full items-center justify-center rounded border-2 border-navy px-4 py-3 text-base font-semibold text-navy">
                {{ __('inspections.log') }}
            </a>
        @endif
    @endif

    <p class="mt-8 font-mono text-sm text-grey-500">{{ $asset->tag }}</p>
</x-layouts.public>
