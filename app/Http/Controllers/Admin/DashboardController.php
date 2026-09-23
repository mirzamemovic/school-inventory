<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AssetStatus;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Report;
use App\Services\NeedsAttention;
use Illuminate\Contracts\View\View;

/**
 * One job: answer "does anything need me today?" in three seconds.
 * No charts, no graphs (spec section 8).
 */
class DashboardController extends Controller
{
    public function __invoke(NeedsAttention $attention): View
    {
        $counts = Asset::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.home', [
            'attention' => $attention->rows(),
            'openReports' => Report::query()->open()->count(),
            'totals' => [
                'all' => Asset::query()->where('status', '!=', AssetStatus::Unassigned->value)->count(),
                'in_use' => (int) ($counts[AssetStatus::InUse->value] ?? 0),
                'under_repair' => (int) ($counts[AssetStatus::UnderRepair->value] ?? 0),
                'retired' => (int) ($counts[AssetStatus::Retired->value] ?? 0),
            ],
        ]);
    }
}
