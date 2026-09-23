<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\Report;

/**
 * Everything the anonymous scan page is allowed to know about an asset.
 *
 * Tags are sequential, so anyone who scans one sticker can walk the rest. If
 * this object carried purchase_price or serial_number, enumeration would
 * produce a categorised shopping list of every valuable item in the building,
 * with room numbers (spec section 13).
 *
 * Warranty and repair history are left out on purpose: "under warranty until
 * 2027" signals new and worth stealing, and "repaired three times" signals
 * troublesome. Neither helps a teacher reporting a fault.
 *
 * The public Blade view is handed one of these and never the Asset model, so
 * keeping a field private is a property of the type rather than something
 * somebody has to remember.
 */
final readonly class PublicAssetView
{
    public function __construct(
        public string $tag,
        public string $name,
        public ?string $room,
        public string $statusLabel,
        public string $statusClasses,
        public ?string $photoUrl,
        public bool $isBlank,
        public bool $hasOpenReport,
        public ?string $openReportAge,
        public bool $needsInspection,
    ) {}

    public static function from(Asset $asset, ?Report $openReport = null): self
    {
        return new self(
            tag: $asset->asset_tag,
            name: $asset->displayName(),
            room: $asset->room?->name,
            statusLabel: $asset->status->label(),
            statusClasses: $asset->status->badgeClasses(),
            photoUrl: $asset->photo_path ? route('photo', ['path' => $asset->photo_path]) : null,
            isBlank: $asset->isBlank(),
            hasOpenReport: $openReport !== null,
            // Only how long ago. Not who reported it, not what they said, and
            // not how many times this has happened before.
            openReportAge: $openReport?->created_at->diffForHumans(),
            needsInspection: (bool) $asset->needs_inspection,
        );
    }
}
