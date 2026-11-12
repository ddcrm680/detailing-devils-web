<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'name', 'slug', 'address', 'city', 'state', 'country_code', 'phones',
    'map_url', 'latitude', 'longitude', 'status', 'sort_order',
])]
class Studio extends Model
{
    public const STATUSES = ['draft', 'active', 'inactive'];

    protected function casts(): array
    {
        return [
            'phones' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'sort_order' => 'integer',
        ];
    }

    public function images(): HasMany
    {
        return $this->hasMany(StudioImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function coverImage(): HasOne
    {
        return $this->hasOne(StudioImage::class)->where('is_cover', true);
    }
}
