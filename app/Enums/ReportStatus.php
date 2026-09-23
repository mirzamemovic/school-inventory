<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return __('reports.status.'.$this->value);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Open => 'bg-status-broken-soft text-status-broken',
            self::InProgress => 'bg-status-attention-soft text-status-attention',
            self::Resolved => 'bg-status-ok-soft text-status-ok',
            self::Dismissed => 'bg-status-inactive-soft text-status-inactive',
        };
    }

    public function isClosed(): bool
    {
        return $this === self::Resolved || $this === self::Dismissed;
    }
}
