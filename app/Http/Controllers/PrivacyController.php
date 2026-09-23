<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Reached from the report form. Public, like the form itself — somebody about
 * to type their name should be able to read what happens to it without a login.
 */
class PrivacyController extends Controller
{
    public function __invoke(): View
    {
        return view('public.privacy');
    }
}
