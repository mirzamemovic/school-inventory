@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Anyone who scans one sticker can walk the rest of the tags. Keep every
         public page out of search results (spec section 13). --}}
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' · '.\App\Models\Setting::schoolName() : \App\Models\Setting::schoolName() }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- The CSRF token travels as an htmx header from the body element. Without
     this, every htmx write fails silently and looks like a routing bug. --}}
<body class="min-h-screen bg-white text-grey-900" hx-headers='@json(['X-CSRF-TOKEN' => csrf_token()])'>
    {{-- No navigation and no header on purpose: someone arrives here from a
         phone camera, holding something in the other hand. --}}
    <main id="main" class="mx-auto w-full max-w-md px-4 py-6 sm:py-10">
        {{ $slot }}
    </main>

    <footer class="mx-auto w-full max-w-md px-4 pb-8">
        <p class="text-xs text-grey-500">{{ \App\Models\Setting::schoolName() }}</p>
    </footer>
</body>
</html>
