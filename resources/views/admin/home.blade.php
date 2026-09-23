<x-layouts.admin :title="__('home.title')">
    <x-flash />

    {{-- One job: does anything need me today? --}}
    <a href="{{ route('admin.reports.index') }}"
       class="mb-6 flex min-h-tap items-center gap-3 rounded border p-4
              {{ $openReports > 0 ? 'border-status-broken-soft bg-status-broken-soft' : 'border-grey-200 bg-white' }}">
        <span class="text-3xl font-bold tabular-nums {{ $openReports > 0 ? 'text-status-broken' : 'text-grey-400' }}">
            {{ $openReports }}
        </span>
        <span class="{{ $openReports > 0 ? 'font-semibold text-status-broken' : 'text-grey-600' }}">
            {{ trans_choice('home.open_reports', $openReports, ['count' => $openReports]) }}
        </span>
    </a>

    <h2 class="mb-3 text-lg font-bold tracking-tight">{{ __('home.needs_attention') }}</h2>

    @if ($attention->isEmpty())
        {{-- The goal state, not a placeholder. --}}
        <x-empty-state :message="__('home.nothing_needs_attention')" />
    @else
        <ul class="mb-8 divide-y divide-grey-200 rounded border border-grey-200 bg-white">
            @foreach ($attention as $row)
                <li>
                    <a href="{{ $row['url'] }}" class="flex min-h-tap items-center gap-3 p-3 hover:bg-grey-50">
                        <span aria-hidden="true"
                              class="size-2 shrink-0 rounded-full {{ $row['severity'] === 'broken' ? 'bg-status-broken' : 'bg-status-attention' }}"></span>
                        <span class="text-sm">{{ $row['label'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    {{-- Four plain numbers. No charts, no graphs. --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ($totals as $key => $value)
            <div class="rounded border border-grey-200 bg-white p-4">
                <p class="text-3xl font-bold tabular-nums">{{ $value }}</p>
                <p class="mt-1 text-sm text-grey-600">{{ __('home.totals.'.$key) }}</p>
            </div>
        @endforeach
    </div>
</x-layouts.admin>
