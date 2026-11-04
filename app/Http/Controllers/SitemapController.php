<?php

namespace App\Http\Controllers;

use App\Services\Seo\SeoResolver;
use App\Services\Seo\SeoSitemap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SitemapController extends Controller
{
    public function __invoke(Request $request, SeoResolver $resolver, SeoSitemap $sitemap)
    {
        try {
            if (! $resolver->publicationAvailable()) {
                throw new \RuntimeException('Publication storage unavailable.');
            }
            $xml = $sitemap->xml();
            $response = response(
                $xml,
                200,
                [
                    'Content-Type' => 'application/xml; charset=UTF-8',
                    'Cache-Control' => 'no-cache, max-age=0, must-revalidate',
                ]
            );
            $response->setEtag(hash('sha256', $xml));
            $response->isNotModified($request);

            return $response;
        } catch (Throwable $exception) {
            Log::warning('SEO sitemap temporarily unavailable.', ['exception' => $exception::class]);

            return response(
                'Sitemap temporarily unavailable.',
                503,
                [
                    'Content-Type' => 'text/plain; charset=UTF-8',
                    'Cache-Control' => 'no-store',
                    'Retry-After' => '60',
                ]
            );
        }
    }
}
