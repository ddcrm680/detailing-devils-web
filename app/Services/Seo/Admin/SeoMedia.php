<?php

namespace App\Services\Seo\Admin;

use App\Models\User;
use App\Services\Seo\ApprovedSeoImage;
use App\Services\Seo\SeoResolver;
use App\Services\Seo\SeoSnapshot;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class SeoMedia
{
    public function remove(string $id, int $expected, User $actor): void
    {
        abort_unless(Gate::forUser($actor)->allows('manage-seo'), 403);
        $removed = false;
        $bytes = null;
        $relative = 'seo/'.$id.'.jpg';
        try {
            DB::transaction(function () use (
                $id,
                $expected,
                $actor,
                $relative,
                &$removed,
                &$bytes
            ) {
                $state = app(SeoStorage::class)->state(lock: true);
                abort_unless(
                    (int) $state->version === $expected,
                    409,
                    'The library changed. Reload before removing.'
                );
                $codec = app(SeoPublicationCodec::class);
                $snapshot = $codec->decode($state->payload);
                $image = collect($snapshot->images)->first(fn ($image) => basename($image->path) === $id.'.jpg');
                abort_unless($image, 404);
                $url = config('seo.origin').$image->path;
                $inHistory = DB::table('seo_revisions')
                    ->whereIn('kind', ['baseline', 'draft', 'published', 'rollback'])
                    ->whereIn(
                        'fields->image',
                        [$url, $image->path]
                    )
                    ->exists();
                $inPages = collect(
                    app(SeoAdminPages::class)
                        ->resolver(app(SeoPublisher::class)->snapshot($state))
                        ->inventory()
                )
                    ->contains(fn ($page) => $page->image === $url);
                if ($inHistory || $inPages) {
                    throw ValidationException::withMessages([
                        'image' => 'This image is used by a page or revision. Replace or clear it in the page editor; historical files are retained for safe rollback.',
                    ]);
                }
                $disk = Storage::disk('fofo_images');
                if ($disk->exists($relative)) {
                    $bytes = $disk->get($relative);
                    if (! hash_equals($image->sha256, hash('sha256', $bytes)) || ! $disk->delete($relative)) {
                        throw ValidationException::withMessages(['image' => 'Image removal failed. No library changes were saved.']);
                    }
                    $removed = true;
                }
                $images = array_values(array_filter(
                    $snapshot->images,
                    fn ($item) => $item->path !== $image->path
                ));
                $payload = $codec->encode(new SeoSnapshot($snapshot->overrides, $snapshot->content, $images));
                DB::table('seo_metadata')
                    ->where('id', 1)
                    ->update([
                        'payload' => $payload,
                        'payload_hash' => hash('sha256', $payload),
                        'version' => $state->version + 1,
                        'updated_at' => now(),
                    ]);
                DB::table('seo_revisions')->insert([
                    'page_id' => '_media',
                    'locale' => 'en',
                    'kind' => 'image-removed',
                    'fields' => json_encode(
                        ['image' => $image->path, 'label' => $image->label],
                        JSON_THROW_ON_ERROR
                    ),
                    'base_version' => $state->version,
                    'administrator_id' => $actor->id,
                    'created_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            if ($removed && $bytes !== null) {
                try {
                    Storage::disk('fofo_images')->put($relative, $bytes);
                } catch (Throwable $recovery) {
                    Log::critical('SEO image reconciliation required.', ['exception' => $recovery::class]);
                }
            }
            throw $exception;
        }
        app()->forgetInstance(SeoResolver::class);
    }

    public function manifests(): array
    {
        return app(SeoPublicationCodec::class)->decode(app(SeoStorage::class)->state()->payload)->images;
    }

    public function choices(): array
    {
        $choices = [];
        foreach ($this->manifests() as $image) {
            $choices[config('seo.origin').$image->path] = $image->label ?: basename($image->path);
        }
        foreach (app(SeoAdminPages::class)->base()->inventory() as $page) {
            if ($page->image) {
                $choices[$page->image] ??= 'Configured site asset — '.basename(parse_url(
                    $page->image,
                    PHP_URL_PATH
                ));
            }
        }

        return $choices;
    }

    public function assertAvailable(?string $url, SeoSnapshot $snapshot): void
    {
        $path = $url ? parse_url($url, PHP_URL_PATH) : '';
        if (! str_starts_with($path, '/uploads/images/seo/')) {
            return;
        }
        $manifest = collect($snapshot->images)->first(fn ($image) => $image->path === $path);
        $file = 'seo/'.basename($path);
        if (
            ! $manifest || ! Storage::disk('fofo_images')->exists($file)
            || ! hash_equals($manifest->sha256, hash('sha256', Storage::disk('fofo_images')->get($file)))
        ) {
            throw ValidationException::withMessages(['image' => 'The approved sharing image is missing or changed.']);
        }
    }

    public function upload(UploadedFile $file, string $label, User $actor): void
    {
        abort_unless(Gate::forUser($actor)->allows('manage-seo'), 403);
        if (! extension_loaded('gd')) {
            throw ValidationException::withMessages(['image_file' => 'Image processing is not available on this host.']);
        }
        $dimensions = @getimagesize($file->getRealPath());
        if (
            ! $dimensions || ! in_array($dimensions['mime'], ['image/jpeg', 'image/png'], true)
            || min($dimensions[0], $dimensions[1]) < 200
            || max($dimensions[0], $dimensions[1]) > 2400
        ) {
            throw ValidationException::withMessages(['image_file' => 'Use a JPEG/PNG from 200 to 2400 pixels per side.']);
        }
        $decoded = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $decoded) {
            throw ValidationException::withMessages(['image_file' => 'The image could not be decoded safely.']);
        }
        $image = imagecreatetruecolor($dimensions[0], $dimensions[1]);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagecopy($image, $decoded, 0, 0, 0, 0, $dimensions[0], $dimensions[1]);
        ob_start();
        imagejpeg($image, null, 90);
        $bytes = ob_get_clean();
        imagedestroy($decoded);
        imagedestroy($image);
        $filename = Str::uuid().'.jpg';
        $relative = 'seo/'.$filename;
        try {
            // Reuse the existing upload disk, in its own subdirectory; never touch franchise images.
            Storage::disk('fofo_images')->put($relative, $bytes);
            DB::transaction(function () use (
                $filename,
                $label,
                $bytes,
                $dimensions,
                $actor
            ) {
                $storage = app(SeoStorage::class);
                $codec = app(SeoPublicationCodec::class);
                $state = $storage->state(lock: true);
                $snapshot = $codec->decode($state->payload);
                $manifest = new ApprovedSeoImage(
                    '/uploads/images/seo/'.$filename,
                    hash('sha256', $bytes),
                    $dimensions[0],
                    $dimensions[1],
                    $label
                );
                $payload = $codec->encode(new SeoSnapshot(
                    $snapshot->overrides,
                    $snapshot->content,
                    [...$snapshot->images, $manifest]
                ));
                DB::table('seo_metadata')
                    ->where('id', 1)
                    ->update([
                        'payload' => $payload,
                        'payload_hash' => hash('sha256', $payload),
                        'version' => $state->version + 1,
                        'updated_at' => now(),
                    ]);
                DB::table('seo_revisions')->insert([
                    'page_id' => '_media',
                    'locale' => 'en',
                    'kind' => 'image-approved',
                    'fields' => json_encode(['image' => $manifest->path, 'label' => $label], JSON_THROW_ON_ERROR),
                    'base_version' => $state->version,
                    'administrator_id' => $actor->id,
                    'created_at' => now(),
                ]);
            });
            app()->forgetInstance(SeoResolver::class);
        } catch (Throwable $exception) {
            Storage::disk('fofo_images')->delete($relative);
            throw $exception;
        }
    }
}
