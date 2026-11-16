<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['content_type', 'content_id', 'path', 'created_by'])]
class ContentUrl extends Model
{
    public const STUDIO = 'studio';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['content_id' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('URL reservations are immutable.'));
        static::deleting(fn () => throw new \LogicException('Historical URLs remain reserved.'));
    }
}
