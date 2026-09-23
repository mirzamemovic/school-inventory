<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\InspectionRecorder;
use Illuminate\Contracts\View\View;

/**
 * The only new screen phase 4 needs: what is overdue an inspection.
 */
class InspectionOverdueController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.inspections.overdue', [
            'assets' => InspectionRecorder::overdue(),
        ]);
    }
}
