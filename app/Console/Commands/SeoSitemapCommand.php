<?php

namespace App\Console\Commands;

use App\Services\Seo\Admin\SeoStorage;
use App\Services\Seo\Admin\SitemapArtifact;
use App\Services\Seo\SeoResolver;
use App\Services\Seo\SeoSitemap;
use DOMDocument;
use DOMXPath;
use Illuminate\Console\Command;
use Throwable;

final class SeoSitemapCommand extends Command
{
    protected $signature = 'seo:sitemap {--check : Validate XML or compare an explicit export without writing} {--restore-published : Export the stored publication snapshot} {--output= : Optional explicit export path; never public/sitemap.xml}';

    protected $description = 'Validate the dynamic sitemap or export XML to an explicit local file';

    public function handle(SeoSitemap $sitemap, SeoResolver $resolver): int
    {
        $target = $this->option('output');
        try {
            if (! $resolver->publicationAvailable()) {
                throw new \RuntimeException('Published SEO storage is unavailable.');
            }
            $xml = $sitemap->xml();
            if ($this->option('restore-published')) {
                if (! $target) {
                    throw new \RuntimeException('Historical sitemap export requires an explicit --output path.');
                }
                $state = app(SeoStorage::class)->state();
                if (! hash_equals($state->sitemap_hash, hash('sha256', $state->sitemap_xml))) {
                    throw new \RuntimeException('Published sitemap integrity check failed.');
                }
                if ($sitemap->urls($state->sitemap_xml) !== $sitemap->urls($xml)) {
                    throw new \RuntimeException('The stored sitemap predates route or indexing changes. Generate the current sitemap instead.');
                }
                $xml = $state->sitemap_xml;
                $document = new DOMDocument;
                $document->loadXML($xml, LIBXML_NONET);
                $xpath = new DOMXPath($document);
                $xpath->registerNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
                $priorities = $xpath->query('/s:urlset/s:url/s:priority');
                if ($priorities->length > 0) {
                    foreach ($priorities as $priority) {
                        $priority->parentNode->removeChild($priority);
                    }
                    $xml = $document->saveXML();
                }
            }
            $urls = $sitemap->urls($xml);
            if (! $target || $this->option('check')) {
                if ($target && (! is_file($target) || $sitemap->urls(file_get_contents($target)) !== $urls)) {
                    $this->error('Sitemap does not match the published canonical registry.');

                    return self::FAILURE;
                }
                $this->info(count($urls).' canonical URLs verified; no files changed.');

                return self::SUCCESS;
            }
            $directory = realpath(dirname($target));
            if (
                $directory === false
                || strcasecmp(
                    str_replace('\\', '/', $directory.'/'.basename($target)),
                    str_replace('\\', '/', public_path('sitemap.xml'))
                ) === 0
            ) {
                throw new \RuntimeException('Use an existing export directory outside the public sitemap endpoint.');
            }
            (new SitemapArtifact($target))->replace($xml);
            $this->info('Generated '.count($urls).' canonical URLs. No dates were fabricated.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Sitemap generation failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
