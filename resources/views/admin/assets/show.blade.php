@php
    $qr = app(\App\Services\QrImage::class);
@endphp
<x-layouts.admin :title="$asset->displayName()">
    <x-flash />

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="sm:col-span-2">
            <div class="rounded border border-grey-200 bg-white p-4">
                @if ($asset->photo_path)
                    <img src="{{ route('photo', ['path' => $asset->photo_path]) }}"
                         alt="{{ __('assets.photo_alt', ['name' => $asset->displayName()]) }}"
                         class="mb-4 aspect-video w-full rounded object-cover">
                @endif

                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                    @foreach ([
                        'tag' => $asset->asset_tag,
                        'category' => $asset->category?->name,
                        'room' => $asset->room?->name,
                        'assigned_person' => $asset->assignedPerson?->name,
                        'make' => $asset->make,
                        'model' => $asset->model,
                        'serial_number' => $asset->serial_number,
                        'official_number' => $asset->official_number,
                        'purchase_date' => $asset->purchase_date?->format('j F Y'),
                        'purchase_price' => $asset->purchase_price,
                        'supplier' => $asset->supplier,
                        'invoice_ref' => $asset->invoice_ref,
                        'funding_source' => $asset->funding_source?->label(),
                        'warranty_end' => $asset->warranty_end?->format('j F Y'),
                        'expected_life_years' => $asset->expected_life_years,
                    ] as $key => $value)
                        @if (filled($value))
                            <div>
                                <dt class="text-xs text-grey-500">{{ __('assets.labels.'.$key) }}</dt>
                                <dd class="{{ $key === 'tag' ? 'font-mono' : '' }} tabular-nums">{{ $value }}</dd>
                            </div>
                        @endif
                    @endforeach

                    <div>
                        <dt class="text-xs text-grey-500">{{ __('assets.labels.status') }}</dt>
                        <dd class="mt-0.5"><x-status-badge :status="$asset->status" /></dd>
                    </div>

                    <div>
                        <dt class="text-xs text-grey-500">{{ __('assets.labels.condition') }}</dt>
                        <dd class="mt-0.5"><x-status-badge :status="$asset->condition" /></dd>
                    </div>
                </dl>

                @if ($asset->notes)
                    <p class="mt-4 border-t border-grey-200 pt-4 text-sm whitespace-pre-line text-grey-700">{{ $asset->notes }}</p>
                @endif
            </div>
        </div>

        <div class="space-y-4">
            {{-- Drawn on demand from the tag. There is no stored image that can
                 go missing (spec section 6.1). --}}
            <div class="rounded border border-grey-200 bg-white p-4 text-center">
                <div class="mx-auto w-40" role="img" aria-label="{{ __('assets.qr_alt', ['tag' => $asset->asset_tag]) }}">
                    {!! $qr->svg($asset->scanUrl()) !!}
                </div>
                <p class="mt-3 font-mono text-sm font-semibold">{{ $asset->asset_tag }}</p>
            </div>

            <div class="rounded border border-grey-200 bg-white p-4">
                <a href="{{ route('admin.assets.edit', $asset) }}"
                   class="mb-3 inline-flex min-h-tap w-full items-center justify-center rounded bg-navy px-4 font-semibold text-white hover:bg-navy-deep">
                    {{ __('common.edit') }}
                </a>

                @if ($inQueue)
                    <form method="POST" action="{{ route('admin.labels.queue.remove', $asset) }}">
                        @csrf
                        @method('DELETE')
                        <p class="mb-1 text-sm text-grey-600">{{ __('assets.actions.in_queue') }}</p>
                        <button type="submit" class="min-h-tap text-sm text-navy underline">{{ __('assets.actions.remove_from_queue') }}</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.labels.queue.add', $asset) }}">
                        @csrf
                        <button type="submit" class="min-h-tap text-sm text-navy underline">{{ __('assets.actions.add_to_queue') }}</button>
                    </form>
                @endif

                <form method="POST" action="{{ route('admin.labels.one', $asset) }}" class="mt-2">
                    @csrf
                    <input type="hidden" name="start_position" value="1">
                    <button type="submit" class="min-h-tap text-sm text-navy underline">{{ __('assets.actions.print_label') }}</button>
                </form>
            </div>

            <details class="rounded border border-grey-200 bg-white">
                <summary class="flex min-h-tap cursor-pointer items-center px-4 text-sm font-semibold">
                    {{ __('assets.actions.move') }}
                </summary>
                <form method="POST" action="{{ route('admin.assets.move', $asset) }}" class="border-t border-grey-200 p-4">
                    @csrf
                    <x-select name="room_id" :options="\App\Models\Room::orderBy('name')->pluck('name', 'id')" :selected="$asset->room_id" class="mb-3" />
                    <button type="submit" class="min-h-tap w-full rounded border border-grey-300 text-sm font-medium">{{ __('common.save') }}</button>
                </form>
            </details>

            <details class="rounded border border-grey-200 bg-white">
                <summary class="flex min-h-tap cursor-pointer items-center px-4 text-sm font-semibold">
                    {{ __('assets.actions.assign') }}
                </summary>
                <form method="POST" action="{{ route('admin.assets.assign', $asset) }}" class="border-t border-grey-200 p-4">
                    @csrf
                    <x-select name="assigned_person_id"
                              :options="\App\Models\Person::active()->orderBy('name')->pluck('name', 'id')"
                              :selected="$asset->assigned_person_id" :placeholder="__('common.none')" class="mb-3" />
                    <button type="submit" class="min-h-tap w-full rounded border border-grey-300 text-sm font-medium">{{ __('common.save') }}</button>
                </form>
            </details>

            <details class="rounded border border-grey-200 bg-white">
                <summary class="flex min-h-tap cursor-pointer items-center px-4 text-sm font-semibold">
                    {{ __('assets.actions.change_status') }}
                </summary>
                <form method="POST" action="{{ route('admin.assets.status', $asset) }}" class="border-t border-grey-200 p-4">
                    @csrf
                    <x-select name="status" :options="\App\Enums\AssetStatus::options()" :selected="$asset->status->value" class="mb-3" />
                    <button type="submit" class="min-h-tap w-full rounded border border-grey-300 text-sm font-medium">{{ __('common.save') }}</button>
                </form>
            </details>

            {{-- Only for compliance items: everything else has no schedule
                 (spec section 8). --}}
            @if ($asset->needs_inspection)
                <div class="rounded border border-grey-200 bg-white p-4">
                    <a href="{{ route('inspection.create', $asset->asset_tag) }}"
                       class="inline-flex min-h-tap w-full items-center justify-center rounded border border-navy px-4 text-sm font-semibold text-navy">
                        {{ __('inspections.log') }}
                    </a>

                    <p class="mt-3 text-xs text-grey-500 tabular-nums">
                        {{ $asset->last_inspected_at
                            ? __('inspections.last_inspected', ['time' => $asset->last_inspected_at->diffForHumans()])
                            : __('inspections.never_inspected') }}
                    </p>

                    @php $dueOn = \App\Services\InspectionRecorder::dueOn($asset); @endphp
                    @if ($dueOn)
                        <p class="mt-1 text-xs tabular-nums {{ $dueOn->isPast() ? 'font-semibold text-status-attention' : 'text-grey-500' }}">
                            {{ $dueOn->isPast()
                                ? __('inspections.overdue')
                                : __('inspections.due', ['date' => $dueOn->format('j M Y')]) }}
                        </p>
                    @endif

                    @if ($asset->inspections->isNotEmpty())
                        <ul class="mt-3 space-y-1 border-t border-grey-200 pt-3">
                            @foreach ($asset->inspections->take(5) as $inspection)
                                <li class="flex items-baseline gap-2 text-xs">
                                    <span class="rounded px-1.5 py-0.5 font-semibold {{ $inspection->result->badgeClasses() }}">
                                        {{ $inspection->result->label() }}
                                    </span>
                                    <span class="min-w-0 flex-1 truncate text-grey-600">{{ $inspection->inspector_name }}</span>
                                    <span class="text-grey-500 tabular-nums">{{ $inspection->inspected_at->format('j M Y') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            @if ($asset->status !== \App\Enums\AssetStatus::Retired)
                <details class="rounded border border-grey-200 bg-white">
                    <summary class="flex min-h-tap cursor-pointer items-center px-4 text-sm font-semibold">
                        {{ __('assets.actions.retire') }}
                    </summary>
                    <form method="POST" action="{{ route('admin.assets.retire', $asset) }}" class="border-t border-grey-200 p-4">
                        @csrf
                        <x-input name="disposal_method" class="mb-3" :placeholder="__('assets.labels.notes')" />
                        <button type="submit" class="min-h-tap w-full rounded border border-grey-300 text-sm font-medium">{{ __('assets.actions.retire') }}</button>
                    </form>
                </details>
            @endif
        </div>
    </div>

    <h2 class="mt-8 mb-3 text-lg font-bold tracking-tight">{{ __('assets.timeline') }}</h2>
    <x-timeline :events="$asset->events" />
</x-layouts.admin>
