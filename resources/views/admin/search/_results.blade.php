{{--
    Shared by the header dropdown and the full results page.

    Every result is a real <a>, so moving onto one with the arrow keys puts
    genuine focus on a link and Enter is the browser's own behaviour rather
    than something reimplemented in JavaScript.
--}}
@if ($tooShort)
    <p class="p-3 text-sm text-grey-500">{{ __('search.too_short') }}</p>
@elseif ($term === '')
    <p class="p-3 text-sm text-grey-500">{{ __('search.start_typing') }}</p>
@elseif ($groups->isEmpty())
    <div class="p-4">
        <p class="text-sm font-medium">{{ __('search.nothing', ['term' => $term]) }}</p>
        <p class="mt-1 text-sm text-grey-500">{{ __('search.nothing_help') }}</p>
    </div>
@else
    <div class="divide-y divide-grey-200">
        @foreach ($groups as $group)
            <section>
                <h2 class="bg-grey-50 px-3 py-1.5 text-xs font-semibold tracking-wide text-grey-500 uppercase">
                    {{ $group['heading'] }}
                </h2>

                <ul>
                    @foreach ($group['items'] as $item)
                        <li>
                            <a href="{{ $item['url'] }}"
                               data-search-result
                               class="flex min-h-tap flex-wrap items-center gap-x-3 gap-y-0.5 px-3 py-2 hover:bg-grey-50 focus:bg-grey-100 focus:outline-none">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium">{{ $item['title'] }}</span>
                                    @if ($item['subtitle'])
                                        <span class="block truncate text-xs text-grey-500">{{ $item['subtitle'] }}</span>
                                    @endif
                                </span>

                                @if ($item['meta'])
                                    <span class="font-mono text-xs whitespace-nowrap text-grey-500 tabular-nums">{{ $item['meta'] }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
@endif
