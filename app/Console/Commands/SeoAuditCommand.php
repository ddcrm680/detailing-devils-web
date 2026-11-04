<?php

namespace App\Console\Commands;

use App\Services\Seo\SeoDiagnostics;
use App\Services\Seo\SeoResolver;
use App\Services\Seo\SeoSitemap;
use Illuminate\Console\Command;
use Throwable;

final class SeoAuditCommand extends Command
{
    protected $signature = 'seo:audit {--search= : Filter page inventory by ID/title} {--html= : Saved rendered HTML file to inspect} {--page= : Registered page ID for HTML comparison}';

    protected $description = 'Read-only SEO inventory, policy and completeness diagnostics';

    public function handle(SeoResolver $resolver, SeoDiagnostics $diagnostics, SeoSitemap $sitemap): int
    {
        $pages = $resolver->inventory();
        $this->table(
            ['Page ID', 'Title', 'Canonical', 'Robots', 'Sitemap'],
            array_map(
                fn ($page) => [
                    $page->id,
                    $page->title,
                    $page->canonical,
                    $page->robots,
                    $page->sitemap && $page->indexable() ? 'yes' : 'no',
                ],
                array_values(array_filter(
                    $pages,
                    fn ($page) => ! $this->option('search')
                    || str_contains(strtolower($page->id.' '.$page->title), strtolower($this->option('search')))
                ))
            )
        );
        $issues = $diagnostics->inspect($pages);
        try {
            $sitemap->urls($sitemap->xml());
            if (! $resolver->publicationAvailable() || is_file(public_path('sitemap.xml'))) {
                $issues[] = [
                    'level' => 'error',
                    'page' => 'sitemap',
                    'message' => 'Publication storage is unavailable or an obsolete static sitemap masks the dynamic endpoint.',
                ];
            }
        } catch (Throwable) {
            $issues[] = [
                'level' => 'error',
                'page' => 'sitemap',
                'message' => 'Invalid or unavailable sitemap registry.',
            ];
        }
        if ($this->option('html')) {
            $id = $this->option('page');
            $matches = array_values(array_filter($pages, fn ($page) => $page->id === $id));
            if (count($matches) !== 1 || ! is_file($this->option('html'))) {
                $this->error('Saved HTML inspection requires an existing file and registered --page ID.');

                return self::FAILURE;
            }
            foreach ($diagnostics->html(file_get_contents($this->option('html')), $matches[0]) as $message) {
                $issues[] = [
                    'level' => 'error',
                    'page' => $id,
                    'message' => $message,
                ];
            }
        }
        $this->table(['Level', 'Page', 'Finding'], array_map(fn ($issue) => array_values($issue), $issues));

        return count(array_filter(
            $issues,
            fn ($issue) => $issue['level'] === 'error'
        )) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
