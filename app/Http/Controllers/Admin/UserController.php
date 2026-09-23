<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;

/**
 * Two accounts, listed. There is deliberately no way to create one or reset a
 * password from the browser: both are artisan commands (spec section 3.1).
 */
class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->orderBy('name')->get(),
        ]);
    }
}
