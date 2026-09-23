<x-layouts.admin :title="__('home.title')">
    <x-flash />

    {{-- One job: does anything need me today? --}}
    <a href="{{ route('admin.reports.index') }}"
       class="group mb-8 flex min-h-tap items-center gap-4 rounded border p-4 sm:p-5
              {{ $openReports > 0 ? 'border-status-broken-soft bg-status-broken-soft' : 'border-grey-200 bg-white hover:bg-grey-50' }}">
        <span class="text-4xl leading-none font-bold tabular-nums {{ $openReports > 0 ? 'text-status-broken' : 'text-grey-500' }}">
            {{ $openReports }}
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-lg leading-snug {{ $openReports > 0 ? 'font-semibold text-status-broken' : 'text-grey-700' }}">
                {{ trans_choice('home.open_reports', $openReports, ['count' => $openReports]) }}
            </span>
            <span class="block text-sm {{ $openReports > 0 ? 'text-status-broken' : 'text-grey-600' }} underline decoration-current/40 underline-offset-2 group-hover:decoration-current">
                {{ __('home.open_queue') }}
            </span>
        </span>
        <span aria-hidden="true" class="text-xl {{ $openReports > 0 ? 'text-status-broken' : 'text-grey-500' }}">→</span>
    </a>

    <h2 class="mb-3 text-lg font-bold tracking-tight">{{ __('home.needs_attention') }}</h2>

    @if ($attention->isEmpty())
        {{-- The goal state, not a placeholder. --}}
        <div class="mb-8 rounded border border-status-ok-soft bg-status-ok-soft px-4 py-5">
            <p class="font-semibold text-status-ok">{{ __('home.nothing_needs_attention') }}</p>
        </div>
    @else
        {{-- The word says what kind of thing it is; the colour says how urgent. --}}
        <ul class="mb-8 divide-y divide-grey-200 rounded border border-grey-200 bg-white">
            @foreach ($attention as $row)
                <li>
                    <a href="{{ $row['url'] }}" class="flex min-h-tap items-start gap-3 px-3 py-3 hover:bg-grey-50 sm:items-center sm:px-4">
                        <span class="mt-0.5 inline-flex w-24 shrink-0 justify-center rounded px-2 py-0.5 text-xs font-semibold sm:mt-0
                                     {{ $row['severity'] === 'broken' ? 'bg-status-broken-soft text-status-broken' : 'bg-status-attention-soft text-status-attention' }}">
                            {{ __('home.kind.'.$row['kind']) }}
                        </span>
                        <span class="min-w-0 flex-1 text-base leading-snug">{{ $row['label'] }}</span>
                        <span aria-hidden="true" class="hidden text-grey-500 sm:inline">→</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    {{-- Four plain numbers. No charts, no graphs. Each opens the list it counts. --}}
    <ul class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ($totals as $key => $value)
            <li>
                <a href="{{ route('admin.assets.index', $key === 'all' ? [] : ['status' => $key]) }}"
                   class="block rounded border border-grey-200 bg-white p-4 hover:border-grey-300 hover:bg-grey-50">
                    <span class="block text-3xl font-bold tabular-nums">{{ $value }}</span>
                    <span class="mt-1 block text-sm text-grey-600">{{ __('home.totals.'.$key) }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</x-layouts.admin>
