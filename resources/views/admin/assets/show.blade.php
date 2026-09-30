@php
    $qr = app(\App\Services\QrImage::class);
    $dueOn = $asset->needs_inspection ? \App\Services\InspectionRecorder::dueOn($asset) : null;

    $identity = array_filter([
        'category' => $asset->category?->name,
        'make' => $asset->make,
        'model' => $asset->model,
        'serial_number' => $asset->serial_number,
        'official_number' => $asset->official_number,
        'expected_life_years' => $asset->expected_life_years ? trans_choice('assets.labels.years', $asset->expected_life_years, ['count' => $asset->expected_life_years]) : null,
        'inspection_interval_months' => $asset->needs_inspection && $asset->inspection_interval_months
            ? trans_choice('assets.labels.months', $asset->inspection_interval_months, ['count' => $asset->inspection_interval_months])
            : null,
    ], 'filled');

    $purchase = array_filter([
        'purchase_date' => $asset->purchase_date?->format('j F Y'),
        'purchase_price' => $asset->purchase_price !== null ? number_format((float) $asset->purchase_price, 2) : null,
        'supplier' => $asset->supplier,
        'invoice_ref' => $asset->invoice_ref,
        'warranty_end' => $asset->warranty_end?->format('j F Y'),
    ], 'filled');

    // "Not known" is the default, not a fact worth a line on its own.
    if ($asset->funding_source && $asset->funding_source !== \App\Enums\FundingSource::Unknown) {
        $purchase['funding_source'] = $asset->funding_source->label();
    }

    $dt = 'text-sm text-grey-600';
    $card = 'rounded border border-grey-200 bg-white';
@endphp
<x-layouts.admin :title="$asset->displayName()" :back="route('admin.assets.index')" :back-label="__('assets.title')">
    <x-slot:subtitle>
        <p class="flex flex-wrap items-baseline gap-x-3 gap-y-1 text-grey-600">
            <span class="font-mono text-base font-semibold text-grey-900">{{ $asset->asset_tag }}</span>
            @if ($asset->category)
                <span>{{ $asset->category->name }}</span>
            @endif
        </p>
    </x-slot:subtitle>

    <x-flash />

    {{--
        What is it, where is it, who answers for it, what state is it in, then
        what can be done about it. Supporting details come after. On a phone the
        two columns fold into one in exactly that order.
    --}}
    <div class="flex flex-col gap-4 lg:grid lg:grid-cols-3 lg:items-start">
        <div class="contents lg:col-span-2 lg:flex lg:flex-col lg:gap-4">
            <section class="order-1 overflow-hidden {{ $card }}" aria-label="{{ __('assets.groups.location') }}">
                @if ($asset->photo_path)
                    <img src="{{ route('photo', ['path' => $asset->photo_path]) }}"
                         alt="{{ __('assets.photo_alt', ['name' => $asset->displayName()]) }}"
                         class="aspect-video w-full border-b border-grey-200 object-cover">
                @endif

                <dl class="grid grid-cols-2">
                    <div class="border-r border-b border-grey-200 p-4">
                        <dt class="{{ $dt }}">{{ __('assets.labels.room') }}</dt>
                        <dd class="mt-1 text-lg leading-snug font-semibold">
                            @if ($asset->room)
                                <a href="{{ route('admin.rooms.show', $asset->room) }}" class="link font-semibold text-grey-900">{{ $asset->room->name }}</a>
                            @else
                                <span class="font-normal text-grey-600">{{ __('common.not_set') }}</span>
                            @endif
                        </dd>
                    </div>

                    <div class="border-b border-grey-200 p-4">
                        <dt class="{{ $dt }}">{{ __('assets.labels.assigned_person') }}</dt>
                        <dd class="mt-1 text-lg leading-snug {{ $asset->assignedPerson ? 'font-semibold' : 'text-grey-600' }}">
                            {{ $asset->assignedPerson?->name ?? __('assets.nobody') }}
                        </dd>
                    </div>

                    {{-- Two different questions, side by side and never merged:
                         where it is in its life, and what shape it is in. --}}
                    <div class="border-r border-grey-200 p-4">
                        <dt class="{{ $dt }}">{{ __('assets.labels.status') }}</dt>
                        <dd class="mt-1.5"><x-status-badge :status="$asset->status" /></dd>
                        @if ($asset->status === \App\Enums\AssetStatus::Retired && $asset->disposal_date)
                            <dd class="mt-1.5 text-sm text-grey-600">
                                {{ collect([$asset->disposal_date->format('j M Y'), $asset->disposal_method])->filter()->join(' · ') }}
                            </dd>
                        @endif
                    </div>

                    <div class="p-4">
                        <dt class="{{ $dt }}">{{ __('assets.labels.condition') }}</dt>
                        <dd class="mt-1.5">
                            @if ($asset->condition)
                                <x-status-badge :status="$asset->condition" />
                            @else
                                <span class="text-grey-600">{{ __('common.not_set') }}</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="order-3 {{ $card }} p-4">
                <h2 class="mb-3 font-bold">{{ __('assets.groups.identity') }}</h2>

                @if (empty($identity))
                    <p class="text-sm text-grey-600">{{ __('assets.identity_empty') }}</p>
                @else
                    <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                        @foreach ($identity as $key => $value)
                            <div>
                                <dt class="{{ $dt }}">{{ __('assets.labels.'.$key) }}</dt>
                                <dd class="{{ in_array($key, ['serial_number', 'official_number'], true) ? 'font-mono text-sm' : '' }} break-words">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </section>

            <section class="order-4 {{ $card }} p-4">
                <h2 class="mb-3 font-bold">{{ __('assets.groups.purchase') }}</h2>

                @if (empty($purchase))
                    <p class="text-sm text-grey-600">{{ __('assets.purchase_empty') }}</p>
                @else
                    <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                        @foreach ($purchase as $key => $value)
                            <div>
                                <dt class="{{ $dt }}">{{ __('assets.labels.'.$key) }}</dt>
                                <dd class="break-words tabular-nums">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </section>

            @if ($asset->notes)
                <section class="order-5 {{ $card }} p-4">
                    <h2 class="mb-2 font-bold">{{ __('assets.labels.notes') }}</h2>
                    <p class="text-sm whitespace-pre-line text-grey-800">{{ $asset->notes }}</p>
                </section>
            @endif
        </div>

        <div class="contents lg:flex lg:flex-col lg:gap-4">
            <section class="order-2 {{ $card }}">
                <h2 class="sr-only">{{ __('assets.actions_heading') }}</h2>

                <div class="p-4">
                    <a href="{{ route('admin.assets.edit', $asset) }}" class="btn-primary w-full">
                        {{ __('common.edit') }}
                    </a>
                </div>

                <details class="border-t border-grey-200">
                    <summary class="disclosure flex min-h-tap cursor-pointer items-center px-4 font-semibold">
                        {{ __('assets.actions.move') }}
                    </summary>
                    <form method="POST" action="{{ route('admin.assets.move', $asset) }}" class="px-4 pb-4">
                        @csrf
                        <label for="move-room" class="sr-only">{{ __('assets.labels.room') }}</label>
                        <x-select name="room_id" id="move-room" :options="\App\Models\Room::orderBy('name')->pluck('name', 'id')" :selected="$asset->room_id" class="mb-3" />
                        <button type="submit" class="btn-secondary w-full">{{ __('assets.actions.move') }}</button>
                    </form>
                </details>

                <details class="border-t border-grey-200">
                    <summary class="disclosure flex min-h-tap cursor-pointer items-center px-4 font-semibold">
                        {{ __('assets.actions.assign') }}
                    </summary>
                    <form method="POST" action="{{ route('admin.assets.assign', $asset) }}" class="px-4 pb-4">
                        @csrf
                        <label for="assign-person" class="sr-only">{{ __('assets.labels.assigned_person') }}</label>
                        <x-select name="assigned_person_id" id="assign-person"
                                  :options="\App\Models\Person::active()->orderBy('name')->pluck('name', 'id')"
                                  :selected="$asset->assigned_person_id" :placeholder="__('assets.nobody')" class="mb-3" />
                        <button type="submit" class="btn-secondary w-full">{{ __('common.save') }}</button>
                    </form>
                </details>

                <details class="border-t border-grey-200">
                    <summary class="disclosure flex min-h-tap cursor-pointer items-center px-4 font-semibold">
                        {{ __('assets.actions.change_status') }}
                    </summary>
                    <form method="POST" action="{{ route('admin.assets.status', $asset) }}" class="px-4 pb-4">
                        @csrf
                        <p class="mb-2 text-sm text-grey-600">{{ __('assets.help.status_vs_condition') }}</p>
                        <label for="change-status" class="sr-only">{{ __('assets.labels.status') }}</label>
                        <x-select name="status" id="change-status" :options="\App\Enums\AssetStatus::options()" :selected="$asset->status->value" class="mb-3" />
                        <button type="submit" class="btn-secondary w-full">{{ __('assets.actions.change_status') }}</button>
                    </form>
                </details>

                {{-- Destructive, so it sits last, apart, and in red. --}}
                @if ($asset->status !== \App\Enums\AssetStatus::Retired)
                    <details class="border-t border-grey-200">
                        <summary class="disclosure flex min-h-tap cursor-pointer items-center px-4 font-semibold text-status-broken">
                            {{ __('assets.actions.retire') }}
                        </summary>
                        <form method="POST" action="{{ route('admin.assets.retire', $asset) }}" class="px-4 pb-4">
                            @csrf
                            <p class="mb-3 text-sm text-grey-600">{{ __('assets.retire_help') }}</p>
                            <label for="disposal_method" class="mb-1 block text-sm font-semibold text-grey-800">{{ __('assets.disposal_method') }}</label>
                            <x-input name="disposal_method" class="mb-3" />
                            <button type="submit" class="btn-danger w-full">{{ __('assets.actions.retire') }}</button>
                        </form>
                    </details>
                @endif
            </section>

            {{-- Only for compliance items: everything else has no schedule
                 (spec section 8). --}}
            @if ($asset->needs_inspection)
                <section class="order-2 {{ $card }} p-4">
                    <h2 class="font-bold">{{ __('inspections.title') }}</h2>

                    <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-grey-600 tabular-nums">
                        <span>
                            {{ $asset->last_inspected_at
                                ? __('inspections.last_inspected', ['time' => $asset->last_inspected_at->diffForHumans()])
                                : __('inspections.never_inspected') }}
                        </span>

                        @if ($dueOn)
                            @if ($dueOn->isPast())
                                <span class="rounded bg-status-attention-soft px-2 py-0.5 text-xs font-semibold text-status-attention">{{ __('inspections.overdue') }}</span>
                            @else
                                <span>{{ __('inspections.due', ['date' => $dueOn->format('j M Y')]) }}</span>
                            @endif
                        @endif
                    </p>

                    <a href="{{ route('inspection.create', $asset->asset_tag) }}" class="btn-secondary mt-3 w-full">
                        {{ __('inspections.log') }}
                    </a>

                    @if ($asset->inspections->isNotEmpty())
                        <ul class="mt-3 space-y-1.5 border-t border-grey-200 pt-3">
                            @foreach ($asset->inspections->take(5) as $inspection)
                                <li class="flex items-baseline gap-2 text-sm">
                                    <span class="rounded px-1.5 py-0.5 text-xs font-semibold {{ $inspection->result->badgeClasses() }}">
                                        {{ $inspection->result->label() }}
                                    </span>
                                    <span class="min-w-0 flex-1 truncate text-grey-700">{{ $inspection->inspector_name }}</span>
                                    @unless ($inspection->counted)
                                        <span class="rounded bg-grey-100 px-1.5 py-0.5 text-xs text-grey-700">{{ __('inspections.not_counted') }}</span>
                                    @endunless
                                    <span class="text-grey-600 tabular-nums">{{ $inspection->inspected_at->format('j M Y') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif

            {{-- Drawn on demand from the tag. There is no stored image that can
                 go missing (spec section 6.1). --}}
            <section class="order-6 {{ $card }} p-4">
                <h2 class="sr-only">{{ __('assets.label_heading') }}</h2>

                <div class="flex items-center gap-4 lg:flex-col lg:items-stretch">
                    <div class="w-28 shrink-0 lg:mx-auto lg:w-40" role="img" aria-label="{{ __('assets.qr_alt', ['tag' => $asset->asset_tag]) }}">
                        {!! $qr->svg($asset->scanUrl()) !!}
                    </div>

                    <div class="min-w-0 flex-1 lg:text-center">
                        <p class="font-mono text-lg font-bold">{{ $asset->asset_tag }}</p>

                        @if ($inQueue)
                            <form method="POST" action="{{ route('admin.labels.queue.remove', $asset) }}" class="mt-1">
                                @csrf
                                @method('DELETE')
                                <p class="text-sm text-grey-600">{{ __('assets.actions.in_queue') }}</p>
                                <button type="submit" class="link-action text-sm">{{ __('assets.actions.remove_from_queue') }}</button>
                            </form>
                        @else
                            {{-- The queue is the default: one label on a 24-up
                                 sheet wastes the other 23 (spec section 6.3). --}}
                            <form method="POST" action="{{ route('admin.labels.queue.add', $asset) }}" class="mt-2">
                                @csrf
                                <button type="submit" class="btn-secondary w-full text-sm">{{ __('assets.actions.add_to_queue') }}</button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('admin.labels.one', $asset) }}">
                            @csrf
                            <input type="hidden" name="start_position" value="1">
                            <button type="submit" class="link-action text-sm">{{ __('assets.actions.print_label') }}</button>
                        </form>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <h2 class="mt-8 mb-3 text-lg font-bold tracking-tight">{{ __('assets.timeline') }}</h2>
    <x-timeline :events="$asset->events" />
</x-layouts.admin>
