<?php

namespace App\Services\Seo\Admin;

use App\Contracts\Seo\MetadataRepository;
use App\Models\User;
use App\Services\Seo\SeoResolver;
use App\Services\Seo\SeoSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SeoPublisher
{
    public function __construct(
        private SeoStorage $storage,
        private SeoPublicationCodec $codec,
        private SeoAdminPages $pages,
        private SeoMedia $media
    ) {}

    public function snapshot(object $state): SeoSnapshot
    {
        $snapshot = $this->codec->decode($state->payload);

        return new SeoSnapshot(
            $snapshot->overrides,
            app(MetadataRepository::class)->snapshot()->content,
            $snapshot->images
        );
    }

    public function draft(
        string $id,
        array $fields,
        int $expected,
        ?string $reason,
        User $actor
    ): int {
        $this->authorize($actor);

        return DB::transaction(function () use (
            $id,
            $fields,
            $expected,
            $reason,
            $actor
        ) {
            $state = $this->storage->state(lock: true);
            abort_unless(
                (int) $state->version === $expected,
                409,
                'The publication changed. Reload before saving.'
            );
            $snapshot = $this->pages->candidate($this->snapshot($state), $id, $fields);
            $this->validate($snapshot, $id, $fields);
            if (! DB::table('seo_revisions')
                ->where('page_id', $id)
                ->exists()) {
                DB::table('seo_revisions')->insert([
                    'page_id' => $id,
                    'locale' => 'en',
                    'kind' => 'baseline',
                    'fields' => json_encode(
                        $this->pages->fields($this->pages->page(
                            $id,
                            $this->snapshot($state)
                        )),
                        JSON_THROW_ON_ERROR
                    ),
                    'base_version' => $expected,
                    'administrator_id' => $actor->id,
                    'created_at' => now(),
                ]);
            }
            $revision = DB::table('seo_revisions')->insertGetId([
                'page_id' => $id,
                'locale' => 'en',
                'kind' => 'draft',
                'fields' => json_encode($fields, JSON_THROW_ON_ERROR),
                'base_version' => $expected,
                'administrator_id' => $actor->id,
                'policy_reason' => $reason,
                'created_at' => now(),
            ]);

            return $revision;
        });
    }

    public function publish(
        int $revisionId,
        int $expected,
        bool $acknowledged,
        bool $policyApproved,
        User $actor,
        bool $rollback = false,
        ?string $policyReason = null
    ): int {
        $this->authorize($actor);
        $version = DB::transaction(function () use (
            $revisionId,
            $expected,
            $acknowledged,
            $policyApproved,
            $actor,
            $rollback,
            $policyReason
        ) {
            $state = $this->storage->state(lock: true);
            abort_unless(
                (int) $state->version === $expected,
                409,
                'The publication changed. Reload before publishing.'
            );
            $revision = DB::table('seo_revisions')
                ->where('id', $revisionId)
                ->first();
            abort_unless(
                $revision && (
                    $rollback
                        ? in_array($revision->kind, ['baseline', 'published', 'rollback'], true)
                        : $revision->kind === 'draft'
                ),
                404
            );
            if (! $rollback) {
                abort_unless(
                    (int) $revision->base_version === $expected,
                    409,
                    'This draft is stale. Save a fresh draft first.'
                );
                abort_if(
                    DB::table('seo_revisions')
                        ->where('source_revision_id', $revisionId)
                        ->where('kind', 'published')
                        ->exists(),
                    409
                );
            }
            $id = $revision->page_id;
            $fields = json_decode($revision->fields, true, flags: JSON_THROW_ON_ERROR);
            $current = $this->snapshot($state);
            $candidate = $this->pages->candidate($current, $id, $fields);
            $this->validate($candidate, $id, $fields);
            $reason = $policyReason ?? $revision->policy_reason;
            $policyChange = $this->pages->policyChanges($fields, $this->pages->page($id, $current));
            if ($policyChange) {
                abort_unless(Gate::forUser($actor)->allows('publish-seo-policy'), 403);
            }
            if ($policyChange && (! $policyApproved || mb_strlen(trim($reason ?? '')) < 15)) {
                throw ValidationException::withMessages([
                    'publication' => 'Policy publication must be explicitly confirmed with a meaningful reason.',
                ]);
            }
            if (! $acknowledged) {
                throw ValidationException::withMessages([
                    'publication' => 'Review and acknowledge the metadata warnings before publishing.',
                ]);
            }
            $this->media->assertAvailable($fields['image'], $candidate);
            $warnings = $this->pages->warnings($candidate);
            if (array_filter($warnings, fn ($issue) => $issue['level'] === 'error')) {
                throw ValidationException::withMessages(['publication' => 'Resolve SEO diagnostic errors before publishing.']);
            }
            if (
                ! $this->pages->page(
                    $id,
                    new SeoSnapshot(
                        content: $candidate->content,
                        images: $candidate->images
                    )
                )->published
            ) {
                throw ValidationException::withMessages([
                    'publication' => 'Publish the underlying content before publishing its SEO override.',
                ]);
            }
            $xml = $this->pages->sitemap($candidate);
            $newVersion = $expected + 1;
            DB::table('seo_revisions')->insert([
                'page_id' => $id,
                'locale' => 'en',
                'kind' => $rollback ? 'rollback' : 'published',
                'fields' => $revision->fields,
                'base_version' => $expected,
                'administrator_id' => $actor->id,
                'source_revision_id' => $revisionId,
                'policy_reason' => $reason,
                'created_at' => now(),
            ]);
            $payload = $this->codec->encode($candidate);
            DB::table('seo_metadata')
                ->where('id', 1)
                ->update([
                    'version' => $newVersion,
                    'payload' => $payload,
                    'payload_hash' => hash('sha256', $payload),
                    'sitemap_xml' => $xml,
                    'sitemap_hash' => hash('sha256', $xml),
                    'updated_at' => now(),
                ]);

            return $newVersion;
        });
        // Readers load one versioned snapshot per request; no persistent metadata cache can go stale.
        app()->forgetInstance(SeoResolver::class);

        return $version;
    }

    private function authorize(User $actor): void
    {
        abort_unless(Gate::forUser($actor)->allows('manage-seo'), 403);
        $this->storage->assertConfigured();
    }

    private function validate(SeoSnapshot $snapshot, string $id, array $fields): void
    {
        $page = $this->pages->page($id, $snapshot);
        if (in_array('Invalid published override rejected; using base metadata.', $page->warnings, true)) {
            throw ValidationException::withMessages(['metadata' => 'The metadata failed the shared SEO contract.']);
        }
        $base = $this->pages->page($id, new SeoSnapshot(content: $snapshot->content, images: $snapshot->images));
        if (
            $fields['sitemap']
            && (! $base->sitemap || ! $page->indexable() || $page->canonical !== $base->canonical)
        ) {
            throw ValidationException::withMessages([
                'sitemap' => 'Only approved, self-canonical, indexable pages can enter the sitemap.',
            ]);
        }
        $canonicals = array_map(fn ($item) => $item->canonical, $this->pages->canonicals($id));
        if (! in_array($fields['canonical'], $canonicals, true)) {
            throw ValidationException::withMessages(['canonical' => 'Choose an existing approved production canonical URL.']);
        }
    }
}
