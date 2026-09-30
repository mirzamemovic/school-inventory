{{--
    The annual physical count happens on paper, walking round the room with a
    pen. So this is a plain sheet with a tick box per line and room to write in
    anything the system does not know about (spec section 8).
--}}
<x-layouts.admin :back="route('admin.rooms.show', $room)" :back-label="$room->name">
    <div class="print:hidden">
        <button type="button" data-print class="btn-primary mb-6">
            {{ __('common.print') }}
        </button>
    </div>

    <h1 class="text-2xl font-bold tracking-tight">{{ __('rooms.sheet_heading', ['room' => $room->name]) }}</h1>
    <p class="mt-1 text-sm text-grey-700">{{ __('rooms.sheet_help') }}</p>

    <table class="mt-6 w-full border-collapse text-sm">
        <thead>
            <tr class="border-b-2 border-grey-900 text-left">
                <th scope="col" class="py-2 pr-3 font-semibold">{{ __('rooms.item_name') }}</th>
                <th scope="col" class="py-2 pr-3 font-semibold">{{ __('assets.labels.tag') }}</th>
                <th scope="col" class="py-2 pr-3 text-right font-semibold">{{ __('rooms.quantity') }}</th>
                <th scope="col" class="w-16 py-2 pr-3 text-center font-semibold">{{ __('rooms.sheet_found') }}</th>
                <th scope="col" class="w-40 py-2 font-semibold">{{ __('rooms.sheet_notes') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($assets as $asset)
                <tr class="border-b border-grey-300">
                    <td class="py-2 pr-3">{{ $asset->displayName() }}</td>
                    <td class="py-2 pr-3 font-mono text-xs">{{ $asset->asset_tag }}</td>
                    <td class="py-2 pr-3 text-right tabular-nums">1</td>
                    <td class="py-2 pr-3 text-center">☐</td>
                    <td class="py-2"></td>
                </tr>
            @endforeach

            @foreach ($contents as $content)
                <tr class="border-b border-grey-300">
                    <td class="py-2 pr-3">{{ $content->item_name }}</td>
                    <td class="py-2 pr-3 text-xs text-grey-600">—</td>
                    <td class="py-2 pr-3 text-right tabular-nums">{{ $content->quantity }}</td>
                    <td class="py-2 pr-3 text-center">☐</td>
                    <td class="py-2"></td>
                </tr>
            @endforeach

            {{-- Blank lines for whatever is in the room that nobody recorded. --}}
            @for ($i = 0; $i < 6; $i++)
                <tr class="border-b border-grey-300">
                    <td class="py-4 pr-3"></td>
                    <td class="py-4 pr-3"></td>
                    <td class="py-4 pr-3"></td>
                    <td class="py-4 pr-3 text-center">☐</td>
                    <td class="py-4"></td>
                </tr>
            @endfor
        </tbody>
    </table>

    <div class="mt-10 flex gap-12 text-sm">
        <p class="flex-1 border-t border-grey-900 pt-2">{{ __('rooms.signature') }}</p>
        <p class="w-48 border-t border-grey-900 pt-2">{{ __('rooms.date') }}</p>
    </div>
</x-layouts.admin>
