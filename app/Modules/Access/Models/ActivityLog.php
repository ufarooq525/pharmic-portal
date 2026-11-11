<?php

namespace App\Modules\Access\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Who did what and when. Rows are written once and never changed. */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public static function record(string $action, string $detail): self
    {
        return static::create(['user_id' => auth()->id(), 'action' => $action, 'detail' => mb_substr($detail, 0, 500)]);
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Activity log entries cannot be edited.'));
        static::deleting(fn () => throw new LogicException('Activity log entries cannot be deleted.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
