<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['alt_text', 'sort_order'])]
class StudioImage extends Model
{
    protected function casts(): array
    {
        return ['is_cover' => 'boolean', 'sort_order' => 'integer'];
    }

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    protected function publicPath(): Attribute
    {
        return Attribute::make(get: function (): ?string {
            if (! preg_match('~^'.preg_quote((string) $this->studio_id, '~').'/[a-f0-9-]{36}\.jpg$~D', $this->path ?? '')) {
                return null;
            }
            $path = '/uploads/studios/'.$this->path;
            $file = public_path(ltrim($path, '/'));

            return is_file($file) && @getimagesize($file) !== false ? $path : null;
        });
    }
}
