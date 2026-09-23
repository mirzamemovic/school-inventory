@props(['title' => null, 'openReports' => null])
@php
    // The red count is visible on every screen (spec section 8), so the
    // layout asks for it rather than every controller remembering to pass it.
    $openReports ??= \App\Models\Report::query()->open()->count();

    // Five items. If a sixth ever seems necessary, stop and ask (spec section 8).
    $items = [
        ['label' => __('nav.home'), 'path' => '/admin', 'pattern' => 'admin'],
        ['label' => __('nav.assets'), 'path' => '/admin/assets', 'pattern' => 'admin/assets*'],
        ['label' => __('nav.reports'), 'path' => '/admin/reports', 'pattern' => 'admin/reports*', 'badge' => true],
        ['label' => __('nav.books'), 'path' => '/admin/books', 'pattern' => 'admin/books*'],
        ['label' => __('nav.manage'), 'path' => '/admin/manage', 'pattern' => 'admin/manage*'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · '.\App\Models\Setting::schoolName() : \App\Models\Setting::schoolName() }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-grey-50 text-grey-900" hx-headers='@json(['X-CSRF-TOKEN' => csrf_token()])'>
    <a href="#main"
       class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:inline-flex focus:min-h-tap focus:items-center focus:rounded focus:bg-white focus:px-4 focus:font-medium focus:text-navy">
        {{ __('layout.skip_to_content') }}
    </a>

    <header class="bg-navy text-white">
        <div class="mx-auto flex min-h-tap max-w-5xl items-center gap-4 px-4">
            <a href="{{ url('/admin') }}" class="flex-1 text-sm font-semibold tracking-wide uppercase">
                {{ \App\Models\Setting::schoolName() }}
            </a>

            @auth
                <x-admin-search />

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="min-h-tap text-sm text-white/80 hover:text-white">
                        {{ __('common.sign_out') }}
                    </button>
                </form>
            @endauth
        </div>
    </header>

    <nav aria-label="{{ __('nav.main') }}" class="border-b border-grey-200 bg-white">
        <ul class="mx-auto flex max-w-5xl flex-wrap gap-1 px-2">
            @foreach ($items as $item)
                @php $active = request()->is($item['pattern']); @endphp
                <li>
                    <a href="{{ url($item['path']) }}"
                       @if ($active) aria-current="page" @endif
                       class="inline-flex min-h-tap items-center border-b-2 px-3 text-sm font-medium whitespace-nowrap
                              {{ $active ? 'border-navy text-navy' : 'border-transparent text-grey-600 hover:text-grey-900' }}">
                        {{ $item['label'] }}
                        @if (($item['badge'] ?? false) && $openReports > 0)
<span class="ml-2 inline-flex min-w-5 items-center justify-center rounded-full bg-status-broken px-1.5 py-0.5 text-xs leading-none font-semibold text-white" aria-label="{{ trans_choice('nav.open_reports', $openReports, ['count' => $openReports]) }}">{{ $openReports }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <main id="main" class="mx-auto w-full max-w-5xl px-4 py-6 sm:py-8">
        @if ($title)
            <h1 class="mb-6 text-2xl font-bold tracking-tight sm:text-3xl">{{ $title }}</h1>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
