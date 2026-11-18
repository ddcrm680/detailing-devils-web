<?php

namespace App\Services;

use App\Models\ContentUrl;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/** One namespace for current and historical paths; destinations are resolved by their content module. */
final class ContentUrls
{
    public function reserve(string $type, int $id, string $path, ?int $actorId = null): void
    {
        if ($id < 1 || ! preg_match('/^[a-z][a-z0-9_-]{0,31}$/D', $type)
            || ! preg_match('~^/[a-z0-9]+(?:[/.-][a-z0-9]+)*$~D', $path) || strlen($path) > 255) {
            throw ValidationException::withMessages(['slug' => 'Enter a valid URL.']);
        }
        $existing = ContentUrl::query()->where('path', $path)->first();
        if ($existing) {
            if ($existing->content_type !== $type || $existing->content_id !== $id) {
                throw ValidationException::withMessages(['slug' => 'This URL is reserved. Please choose another.']);
            }

            return;
        }
        ContentUrl::create([
            'content_type' => $type, 'content_id' => $id, 'path' => $path,
            'created_by' => $actorId ?? Auth::id(),
        ]);
    }
}
