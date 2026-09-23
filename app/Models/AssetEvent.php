<?php

namespace App\Models;

use App\Enums\ActorType;
use App\Exceptions\AuditLogIsAppendOnly;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['subject_type', 'subject_id', 'action', 'actor_type', 'actor_id', 'actor_name', 'device_token', 'ip_address', 'meta'])]
class AssetEvent extends Model
{
    /** Append only: there is no updated_at column to manage. */
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'actor_type' => ActorType::class,
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw AuditLogIsAppendOnly::onUpdate());
        static::deleting(fn () => throw AuditLogIsAppendOnly::onDelete());
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Public actions are self-declared, never verified. The UI must not present
     * them with the same certainty as an authenticated admin action.
     */
    public function isSelfReported(): bool
    {
        return ! $this->actor_type->isVerified();
    }

    public function actorLabel(): string
    {
        return $this->actor_name ?: __('activity.someone');
    }
}
