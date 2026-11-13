<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'name', 'slug', 'address', 'city', 'state', 'country_code', 'phones',
    'map_url', 'latitude', 'longitude', 'status', 'sort_order',
])]
class Studio extends Model
{
    public const STATUSES = ['draft', 'active', 'inactive'];

    protected static function booted(): void
    {
        static::creating(function (Studio $studio) {
            if (! filled($studio->slug)) {
                $studio->slug = static::generateUniqueSlug($studio->name);
            }
        });
    }

    public static function generateUniqueSlug(string $name): string
    {
        $base = rtrim(substr(Str::slug($name) ?: 'studio', 0, 180), '-');
        $slug = $base;
        for ($suffix = 2; static::query()->where('slug', $slug)->exists(); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    protected function casts(): array
    {
        return [
            'phones' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'sort_order' => 'integer',
        ];
    }

    protected function directionsUrl(): Attribute
    {
        return Attribute::make(get: function (): string {
            $url = is_string($this->map_url) ? trim($this->map_url) : '';
            $parts = parse_url($url);
            $host = strtolower($parts['host'] ?? '');
            $path = $parts['path'] ?? '';
            $googleMaps = in_array($host, ['maps.app.goo.gl', 'maps.google.com'], true)
                || (in_array($host, ['google.com', 'www.google.com'], true) && preg_match('~^/maps(?:/|$)~', $path))
                || ($host === 'goo.gl' && str_starts_with($path, '/maps/'));

            if (filter_var($url, FILTER_VALIDATE_URL)
                && strtolower($parts['scheme'] ?? '') === 'https'
                && $googleMaps
                && ! isset($parts['user'])
                && ! isset($parts['pass'])
                && (! isset($parts['port']) || $parts['port'] === 443)
                && ! str_contains($url, '\\')) {
                return $url;
            }

            return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($this->name.' '.$this->address);
        });
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
