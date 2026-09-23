<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Session;

function renderPublicLayout(string $slot = 'Slot content', string $attributes = ''): string
{
    return Blade::render("<x-layouts.public {$attributes}>{$slot}</x-layouts.public>");
}

it('renders whatever the page puts in the slot', function () {
    expect(renderPublicLayout('Projector INV-PRJ-0031'))->toContain('Projector INV-PRJ-0031');
});

it('keeps public pages out of search engines', function () {
    expect(renderPublicLayout())
        ->toContain('<meta name="robots" content="noindex, nofollow">');
});

it('shows no navigation and no admin header', function () {
    $html = renderPublicLayout();

    expect($html)
        ->not->toContain('<nav')
        ->not->toContain('<header')
        ->not->toContain(__('nav.assets'))
        ->not->toContain(__('nav.manage'));
});

it('carries the csrf token as an htmx header on the body', function () {
    // The most common HTMX-in-Laravel mistake, and it fails silently.
    Session::start();

    expect(renderPublicLayout())
        ->toContain('hx-headers=')
        ->toContain(csrf_token());
});

it('uses the school name as the title, and prefixes a page title when given', function () {
    expect(renderPublicLayout())
        ->toContain('<title>'.config('app.name').'</title>');

    expect(renderPublicLayout('x', 'title="Report a problem"'))
        ->toContain('<title>Report a problem · '.config('app.name').'</title>');
});

it('declares the viewport so phones do not zoom out', function () {
    expect(renderPublicLayout())->toContain('width=device-width, initial-scale=1');
});

it('loads the compiled stylesheet and script', function () {
    expect(renderPublicLayout())
        ->toContain('build/assets/app-')
        ->toContain('.css')
        ->toContain('.js');
});
