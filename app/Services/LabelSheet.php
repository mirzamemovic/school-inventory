<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Collection;

/**
 * A4, 24 up, 3 columns by 8 rows on a standard 70x37mm sticker sheet.
 *
 * The start position matters more than anything else here: printing one label
 * onto a fresh sheet wastes 23 stickers, so a partly used sheet has to be
 * feedable back through the printer (spec section 6.3).
 */
class LabelSheet
{
    public function __construct(private readonly QrImage $qr) {}

    public function perSheet(): int
    {
        return (int) config('school.labels.columns') * (int) config('school.labels.rows');
    }

    /**
     * @param  Collection<int, Asset>  $assets
     */
    public function render(Collection $assets, int $startPosition = 1): PdfDocument
    {
        return Pdf::loadView('labels.sheet', [
            'pages' => $this->paginate($assets, $startPosition),
            'schoolName' => Setting::schoolName(),
            'layout' => config('school.labels'),
        ])->setPaper('a4');
    }

    /**
     * Lay the labels out into pages of 24 cells, leaving the cells before the
     * chosen start position empty so already-peeled stickers are skipped.
     *
     * @param  Collection<int, Asset>  $assets
     * @return array<int, array<int, array{tag: string, qr: string}|null>>
     */
    public function paginate(Collection $assets, int $startPosition = 1): array
    {
        $perSheet = $this->perSheet();
        $startPosition = max(1, min($startPosition, $perSheet));

        $cells = array_fill(0, $startPosition - 1, null);

        foreach ($assets as $asset) {
            $cells[] = [
                'tag' => $asset->asset_tag,
                'qr' => $this->qr->pngDataUri($asset->scanUrl()),
            ];
        }

        $cells = array_pad($cells, (int) ceil(max(count($cells), 1) / $perSheet) * $perSheet, null);

        return array_chunk($cells, $perSheet);
    }
}
