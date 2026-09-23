<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Session;

function renderAdminLayout(string $slot = 'Slot content', string $attributes = ''): string
{
    return Blade::render("<x-layouts.admin {$attributes}>{$slot}</x-layouts.admin>");
}

it('renders whatever the page puts in the slot', function () {
    expect(renderAdminLayout('Nothing needs your attention.'))
        ->toContain('Nothing needs your attention.');
});

it('shows exactly the five navigation items', function () {
    $html = renderAdminLayout();

    expect($html)
        ->toContain(__('nav.home'))
        ->toContain(__('nav.assets'))
        ->toContain(__('nav.reports'))
        ->toContain(__('nav.books'))
        ->toContain(__('nav.manage'));

    expect(substr_count($html, '<li>'))->toBe(5);
});

it('points each navigation item at its admin path', function () {
    $html = renderAdminLayout();

    foreach (['/admin', '/admin/assets', '/admin/reports', '/admin/books', '/admin/manage'] as $path) {
        expect($html)->toContain('href="'.url($path).'"');
    }
});

it('hides the report badge when nothing is open', function () {
    expect(renderAdminLayout())->not->toContain('bg-status-broken');
});

it('shows a red count badge on Reports when reports are open', function () {
    $html = renderAdminLayout('x', ':open-reports="3"');

    expect($html)
        ->toContain('bg-status-broken')
        ->toContain('>3<')
        ->toContain('3 open reports');
});

it('labels a single open report in the singular', function () {
    expect(renderAdminLayout('x', ':open-reports="1"'))->toContain('1 open report');
});

it('renders the page title as a heading and in the browser title', function () {
    $html = renderAdminLayout('x', 'title="Assets"');

    expect($html)
        ->toContain('<title>Assets · '.config('app.name').'</title>')
        ->toContain('>Assets</h1>');
});

it('carries the csrf token as an htmx header on the body', function () {
    // The most common HTMX-in-Laravel mistake, and it fails silently.
    Session::start();

    expect(renderAdminLayout())
        ->toContain('hx-headers=')
        ->toContain(csrf_token());
});

it('offers a skip link to the main content', function () {
    expect(renderAdminLayout())
        ->toContain('href="#main"')
        ->toContain(__('layout.skip_to_content'))
        ->toContain('id="main"');
});

it('is not marked noindex, unlike the public pages', function () {
    expect(renderAdminLayout())->not->toContain('noindex');
});
