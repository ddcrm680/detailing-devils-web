<?php

namespace App\Support;

use DOMDocument;

class GoogleMapsEmbed
{
    public static function normalize(string $input): ?string
    {
        $url = trim($input);
        if (strlen($url) > 12000 || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $url)) {
            return null;
        }
        if (str_starts_with($url, '<')) {
            if (! preg_match('~\A<iframe\b[^<>]*>\s*</iframe>\z~isD', $url)) {
                return null;
            }
            $previous = libxml_use_internal_errors(true);
            libxml_clear_errors();
            try {
                $document = new DOMDocument;
                $loaded = $document->loadHTML($url, LIBXML_NONET);
                $errors = libxml_get_errors();
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            $iframe = $document->getElementsByTagName('iframe')->item(0);
            if (! $loaded || $errors || ! $iframe) {
                return null;
            }
            foreach ($iframe->attributes as $attribute) {
                if (! in_array($attribute->name, ['src', 'width', 'height', 'style', 'allowfullscreen', 'loading', 'referrerpolicy', 'frameborder', 'title'], true)) {
                    return null;
                }
                if ($attribute->name === 'style' && ! preg_match('/\A\s*border\s*:\s*0\s*;?\s*\z/iD', $attribute->value)) {
                    return null;
                }
            }
            $url = $iframe->getAttribute('src');
        }
        if (! filter_var($url, FILTER_VALIDATE_URL) || preg_match('/[\x00-\x20\x7f<>"\'\\\\]/', $url)) {
            return null;
        }
        $parts = parse_url($url);
        if (strtolower($parts['scheme'] ?? '') !== 'https'
            || ! in_array(strtolower($parts['host'] ?? ''), ['www.google.com', 'google.com'], true)
            || ($parts['path'] ?? '') !== '/maps/embed'
            || isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['port']) && $parts['port'] !== 443)
            || isset($parts['fragment'])
            || ! preg_match('/\Apb=([^&]+)\z/D', $parts['query'] ?? '', $query)) {
            return null;
        }
        $pb = rawurldecode($query[1]);
        if (! preg_match('/\A!\d+[a-z]/iD', $pb) || preg_match('/[\x00-\x1f\x7f<>]/', $pb)) {
            return null;
        }

        return $url;
    }
}
