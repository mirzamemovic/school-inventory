<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * The fifth navigation item. A plain list of the things a director changes
 * rarely, kept out of the four screens they use every day.
 */
class ManageController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.manage.index', [
            'sections' => [
                ['key' => 'rooms', 'url' => route('admin.rooms.index')],
                ['key' => 'people', 'url' => route('admin.people.index')],
                ['key' => 'supplies', 'url' => route('admin.supplies.index')],
                ['key' => 'licenses', 'url' => route('admin.licenses.index')],
                ['key' => 'inspectors', 'url' => route('admin.inspectors.index')],
                ['key' => 'categories', 'url' => route('admin.categories.index')],
                ['key' => 'activity', 'url' => route('admin.activity.index')],
                ['key' => 'settings', 'url' => route('admin.settings.edit')],
                ['key' => 'users', 'url' => route('admin.users.index')],
            ],
        ]);
    }
}
