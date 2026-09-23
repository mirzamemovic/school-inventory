<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SerialNumberCheck;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Answers the live check under the serial number field. It returns a fragment
 * of markup and never a decision: nothing here can stop a save.
 */
class SerialCheckController extends Controller
{
    public function __invoke(Request $request, SerialNumberCheck $check): View
    {
        $duplicates = $check->duplicatesOf(
            // The field posts under its own name, so that is what is read here.
            $request->string('serial_number')->toString(),
            $request->filled('except') ? $request->integer('except') : null,
        );

        return view('admin.assets._serial-warning', [
            'duplicate' => $duplicates->first(),
            'others' => max(0, $duplicates->count() - 1),
        ]);
    }
}
