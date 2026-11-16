<?php

namespace App\Services;

use App\Models\ContentUrl;
use App\Models\Studio;
use App\Models\User;
use App\Services\Seo\Admin\SeoPublicationCodec;
use App\Services\Seo\Admin\SeoStorage;
use App\Services\Seo\SeoResolver;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class StudioUrls
{
    public static function path(string $slug): string
    {
        return '/studios/'.$slug;
    }

    /** Same first lock as the SEO publisher: SEO state, then Studio, then path reservation. */
    public function lockPublication(): ?object
    {
        return Schema::hasTable('seo_metadata') ? app(SeoStorage::class)->state(lock: true) : null;
    }

    public function save(Studio $studio, Closure $persist): bool
    {
        try {
            return DB::transaction(function () use ($studio, $persist) {
                $this->lockPublication();
                $current = $studio->exists ? Studio::query()->lockForUpdate()->findOrFail($studio->id) : null;
                if ($current && $studio->getRawOriginal('slug') !== $current->slug) {
                    $this->fail('The studio URL changed. Reload this page and try again.');
                }
                if ($current && $studio->slug !== $current->slug) {
                    if ($current->url_locked_at || $current->status === 'active') {
                        $this->fail('Use Change URL to update this studio URL.');
                    }
                    $this->available($studio->slug);
                }
                $studio->url_locked_at = $current?->url_locked_at
                    ?? (($studio->status === 'active' || $current?->status === 'active') ? now() : null);
                $saved = $persist();
                if ($saved) {
                    $this->validateSlug($studio->slug);
                    app(ContentUrls::class)->reserve(ContentUrl::STUDIO, $studio->id, self::path($studio->slug));
                }

                return $saved;
            });
        } catch (UniqueConstraintViolationException) {
            $this->fail('This URL was just taken. Please choose another.');
        }
    }

    private function validateSlug(string $slug): void
    {
        if (strlen($slug) > 191 || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) {
            $this->fail('Use lowercase letters, numbers and single hyphens.');
        }
    }

    private function available(string $slug): void
    {
        if (ContentUrl::query()->where('path', self::path($slug))->exists()
            || Studio::query()->where('slug', $slug)->exists()) {
            $this->fail('This URL is reserved. Please choose another.');
        }
    }

    public function change(Studio $studio, string $slug, string $expected, User $actor): void
    {
        abort_unless($actor->can('manage-studios'), 403);
        $this->validateSlug($slug);
        try {
            DB::transaction(function () use ($studio, $slug, $expected, $actor) {
                $publication = $this->lockPublication();
                $current = Studio::query()->lockForUpdate()->findOrFail($studio->id);
                if ($current->slug !== $expected) {
                    $this->fail('The studio URL changed. Reload this page and try again.');
                }
                if ($slug === $current->slug) {
                    $this->fail('Choose a different URL.');
                }
                $this->available($slug);
                if ($publication) {
                    $this->checkSeo($current, $publication);
                }
                app(ContentUrls::class)->reserve(ContentUrl::STUDIO, $current->id, self::path($current->slug), $actor->id);
                app(ContentUrls::class)->reserve(ContentUrl::STUDIO, $current->id, self::path($slug), $actor->id);
                Studio::query()->whereKey($current->id)->update(['slug' => $slug, 'updated_at' => now()]);
                app()->forgetInstance(SeoResolver::class);
            });
        } catch (UniqueConstraintViolationException) {
            $this->fail('This URL was just taken. Please choose another.');
        }
    }

    private function checkSeo(Studio $studio, object $state): void
    {
        $id = 'studio.'.$studio->id;
        $canonical = rtrim(config('seo.origin'), '/').self::path($studio->slug);
        $snapshot = app(SeoPublicationCodec::class)->decode($state->payload);
        foreach ($snapshot->overrides as $override) {
            if ($override->published && isset($override->fields['canonical'])
                && ($override->pageId === $id || $override->fields['canonical'] === $canonical)) {
                $this->fail('Review the existing canonical settings in SEO Management before changing this URL.');
            }
        }
        $latest = DB::table('seo_revisions')->selectRaw('MAX(id)')->groupBy('page_id');
        foreach (DB::table('seo_revisions')->whereIn('id', $latest)->where('kind', 'draft')->get() as $draft) {
            $fields = json_decode($draft->fields, true, flags: JSON_THROW_ON_ERROR);
            if ($draft->page_id === $id || ($fields['canonical'] ?? null) === $canonical) {
                $this->fail('Review the pending SEO draft before changing this URL.');
            }
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['slug' => $message]);
    }
}
