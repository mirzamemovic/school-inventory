@props(['title' => null, 'openReports' => null, 'back' => null, 'backLabel' => null])
@php
    // The red count is visible on every screen (spec section 8), so the
    // layout asks for it rather than every controller remembering to pass it.
    $openReports ??= \App\Models\Report::query()->open()->count();

    // Five items. If a sixth ever seems necessary, stop and ask (spec section 8).
    // Each item stays highlighted on every screen that lives underneath it, so
    // the director always knows which of the five they are in.
    $items = [
        ['label' => __('nav.home'), 'path' => '/admin', 'patterns' => ['admin']],
        ['label' => __('nav.assets'), 'path' => '/admin/assets', 'patterns' => ['admin/assets*', 'admin/intake*', 'admin/labels*', 'admin/inspections*']],
        ['label' => __('nav.reports'), 'path' => '/admin/reports', 'patterns' => ['admin/reports*'], 'badge' => true],
        ['label' => __('nav.books'), 'path' => '/admin/books', 'patterns' => ['admin/books*']],
        ['label' => __('nav.manage'), 'path' => '/admin/manage', 'patterns' => ['admin/manage*', 'admin/rooms*', 'admin/people*', 'admin/supplies*', 'admin/licenses*', 'admin/categories*', 'admin/activity*', 'admin/settings*', 'admin/users*', 'admin/inspectors*']],
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

    <header class="on-dark bg-navy text-white">
        {{-- Relative so the search results can span the header on a phone. --}}
        <div class="relative mx-auto flex min-h-tap max-w-5xl items-center gap-2 px-4 sm:gap-4">
            <a href="{{ url('/admin') }}" class="min-w-0 flex-1 truncate py-2.5 text-sm font-semibold tracking-wide uppercase">
                {{ \App\Models\Setting::schoolName() }}
            </a>

            @auth
                <x-admin-search />

                <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="min-h-tap px-1 text-sm text-white/80 hover:text-white">
                        {{ __('common.sign_out') }}
                    </button>
                </form>
            @endauth
        </div>
    </header>

    {{-- One row on every phone: the five items share the width instead of
         wrapping "Manage" onto a line of its own. --}}
    <nav aria-label="{{ __('nav.main') }}" class="border-b border-grey-200 bg-white">
        <ul class="mx-auto flex max-w-5xl overflow-x-auto *:flex-auto sm:gap-1 sm:px-2 sm:*:flex-none">
            @foreach ($items as $item)
                @php $active = request()->is(...$item['patterns']); @endphp
                <li>
                    <a href="{{ url($item['path']) }}"
                       @if ($active) aria-current="page" @endif
                       class="flex min-h-tap items-center justify-center border-b-2 px-1.5 text-sm whitespace-nowrap focus-visible:outline-offset-[-2px] sm:px-3
                              {{ $active ? 'border-navy font-semibold text-navy' : 'border-transparent font-medium text-grey-600 hover:border-grey-300 hover:text-grey-900' }}">
                        {{ $item['label'] }}
                        @if (($item['badge'] ?? false) && $openReports > 0)
<span class="ml-1 inline-flex min-w-5 items-center justify-center rounded-full bg-status-broken px-1.5 py-0.5 text-xs leading-none font-semibold text-white sm:ml-2" aria-label="{{ trans_choice('nav.open_reports', $openReports, ['count' => $openReports]) }}">{{ $openReports }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <main id="main" class="mx-auto w-full max-w-5xl px-4 py-5 sm:py-8">
        {{-- One step up, always in the same place. --}}
        @if ($back)
            <a href="{{ $back }}" class="-mt-2 mb-1 inline-flex min-h-tap items-center gap-1.5 text-sm text-grey-600 hover:text-grey-900 print:hidden">
                <span aria-hidden="true">←</span>{{ $backLabel }}
            </a>
        @endif

        @if ($title)
            <div class="mb-6">
                <h1 class="text-2xl leading-tight font-bold tracking-tight text-balance sm:text-3xl">{{ $title }}</h1>
                @isset($subtitle)
                    <div class="mt-2">{{ $subtitle }}</div>
                @endisset
            </div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
