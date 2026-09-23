<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\License;
use App\Models\Person;
use App\Models\Report;
use App\Models\Supply;
use App\Support\AcademicYear;
use Illuminate\Support\Collection;

/**
 * One list, several sources, five rows at most. Home answers "does anything
 * need me today?" in three seconds, so anything that does not change what the
 * director does next has no business being here (spec section 8).
 */
class NeedsAttention
{
    public const MAX_ROWS = 5;

    /** @return Collection<int, array{label: string, url: string, severity: string}> */
    public function rows(): Collection
    {
        return collect()
            ->concat($this->staleReports())
            ->concat($this->stuckUnderRepair())
            ->concat($this->expiringWarranties())
            ->concat($this->expiringLicenses())
            ->concat($this->overdueInspections())
            ->concat($this->lowSupplies())
            ->concat($this->bookDebts())
            ->take(self::MAX_ROWS);
    }

    /** @return Collection<int, array{label: string, url: string, severity: string}> */
    private function staleReports(): Collection
    {
        return Report::query()
            ->with('asset')
            ->stillOpen()
            ->where('created_at', '<=', now()->subDays(7))
            ->oldest('created_at')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (Report $report) => [
                'label' => __('home.attention.stale_report', [
                    'item' => $report->asset->displayName(),
                    'time' => $report->created_at->diffForHumans(),
                ]),
                'url' => route('admin.reports.show', $report),
                'severity' => 'broken',
            ]);
    }

    /** @return Collection<int, array{label: string, url: string, severity: string}> */
    private function stuckUnderRepair(): Collection
    {
        return Asset::query()
            ->where('status', AssetStatus::UnderRepair->value)
            ->where('updated_at', '<=', now()->subDays(30))
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (Asset $asset) => [
                'label' => __('home.attention.stuck_repair', ['item' => $asset->displayName()]),
                'url' => route('admin.assets.show', $asset),
                'severity' => 'attention',
            ]);
    }

    /**
     * Licences running out inside the window, and any already gone. A perpetual
     * licence has no expiry date and never appears here — the scope excludes
     * null outright (spec section 5).
     *
     * @return Collection<int, array{label: string, url: string, severity: string}>
     */
    private function expiringLicenses(): Collection
    {
        return License::query()
            ->expiringWithin(60)
            ->byExpiry()
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (License $license) => [
                'label' => __($license->isExpired() ? 'home.attention.license_expired' : 'home.attention.license_expiring', [
                    'item' => $license->name,
                    'date' => $license->expires_at->format('j M Y'),
                ]),
                'url' => route('admin.licenses.show', $license),
                'severity' => $license->isExpired() ? 'broken' : 'attention',
            ]);
    }

    /** @return Collection<int, array{label: string, url: string, severity: string}> */
    private function overdueInspections(): Collection
    {
        return InspectionRecorder::overdue()
            ->take(self::MAX_ROWS)
            ->map(fn (Asset $asset) => [
                'label' => __('home.attention.overdue_inspection', ['item' => $asset->displayName()]),
                'url' => route('admin.inspections.overdue'),
                'severity' => 'attention',
            ])
            ->values();
    }

    /** @return Collection<int, array{label: string, url: string, severity: string}> */
    private function lowSupplies(): Collection
    {
        return Supply::query()
            ->low()
            ->orderBy('name')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (Supply $supply) => [
                'label' => __('home.attention.low_supply', [
                    'item' => $supply->name,
                    'quantity' => $supply->describeQuantity(),
                ]),
                'url' => route('admin.supplies.index'),
                'severity' => 'attention',
            ]);
    }

    /**
     * Only between May and September. Chasing book debts in November is noise
     * (spec section 8).
     *
     * @return Collection<int, array{label: string, url: string, severity: string}>
     */
    private function bookDebts(): Collection
    {
        if (! AcademicYear::isChasingSeason()) {
            return collect();
        }

        $students = Person::query()
            ->students()
            ->active()
            ->whereHas('loans', fn ($q) => $q->whereNull('returned_at'))
            ->count();

        if ($students === 0) {
            return collect();
        }

        return collect([[
            'label' => trans_choice('home.attention.book_debt', $students, ['count' => $students]),
            'url' => route('admin.books.returns.form'),
            'severity' => 'attention',
        ]]);
    }

    /** @return Collection<int, array{label: string, url: string, severity: string}> */
    private function expiringWarranties(): Collection
    {
        return Asset::query()
            ->whereNotNull('warranty_end')
            ->whereBetween('warranty_end', [now()->toDateString(), now()->addDays(60)->toDateString()])
            ->where('status', '!=', AssetStatus::Retired->value)
            ->orderBy('warranty_end')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (Asset $asset) => [
                'label' => __('home.attention.warranty', [
                    'item' => $asset->displayName(),
                    'date' => $asset->warranty_end->format('j M Y'),
                ]),
                'url' => route('admin.assets.show', $asset),
                'severity' => 'attention',
            ]);
    }
}
