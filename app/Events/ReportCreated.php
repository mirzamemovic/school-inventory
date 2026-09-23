<?php

namespace App\Events;

use App\Models\Report;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Notifications are in-app only for now, but this event fires anyway so an
 * email listener can be added later without refactoring (spec section 3).
 */
class ReportCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Report $report) {}
}
