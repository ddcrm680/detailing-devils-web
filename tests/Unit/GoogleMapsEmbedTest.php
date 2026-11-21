<?php

namespace Tests\Unit;

use App\Support\GoogleMapsEmbed;
use PHPUnit\Framework\TestCase;

class GoogleMapsEmbedTest extends TestCase
{
    public function test_google_generated_urls_and_iframes_normalize_to_only_the_url(): void
    {
        $url = 'https://www.google.com/maps/embed?pb=!1m18!2sSynthetic%20Studio%20%26%20Cars';
        foreach ([$url, '  '.$url.'  ', str_replace('www.google.com', 'google.com', $url), '<iframe src="'.$url.'" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>', "<iframe\n src='$url' frameborder='0'></iframe>"] as $value) {
            $this->assertSame(str_contains($value, 'www.google.com') ? $url : str_replace('www.google.com', 'google.com', $url), GoogleMapsEmbed::normalize($value));
        }
    }

    public function test_non_embed_urls_scripts_html_and_ambiguous_parameters_are_rejected(): void
    {
        $url = 'https://www.google.com/maps/embed?pb=!1m18';
        foreach ([
            '', 'javascript:alert(1)', '//www.google.com/maps/embed?pb=!1m18', "<iframe src=\"$url\"\0></iframe>",
            str_replace('https:', 'http:', $url), str_replace('google.com', 'google.com.evil.example', $url),
            'https://evil.example/maps/embed?pb=!1m18', 'https://www.google.com@evil.example/maps/embed?pb=!1m18',
            'https://user:pass@www.google.com/maps/embed?pb=!1m18',
            'https://www.google.com:8443/maps/embed?pb=!1m18',
            'https://maps.app.goo.gl/Fixture', 'https://www.google.com/maps/search/?api=1&query=Studio',
            'https://www.google.com/maps/embed/v1/place?key=fixture&q=Studio',
            'https://www.google.com/maps/embed', 'https://www.google.com/maps/embed?pb=',
            'https://www.google.com/maps/embed?pb=not-google-data', $url.'&pb=!2m14',
            'https://www.google.com/maps/embed?pb[]=!1m18', $url.'&other=value', $url.'#fragment',
            $url.'%0A', $url.'%3Cscript%3E', str_replace('/maps/embed', '/maps/%65mbed', $url),
            '<script>alert(1)</script>', '<iframe src="'.$url.'" onload="alert(1)"></iframe>',
            '<iframe src="'.$url.'" srcdoc="script"></iframe>',
            '<iframe src="'.$url.'" style="background:url(javascript:alert(1))"></iframe>',
            '<iframe src="'.$url.'" src="https://evil.example/"></iframe>',
            '<iframe src="'.$url.'"></iframe><script>alert(1)</script>',
            '<div><iframe src="'.$url.'"></iframe></div>', '<iframe src="'.$url.'">unrelated content</iframe>',
            '<iframe src="'.$url.'">', '<!DOCTYPE html><iframe src="'.$url.'"></iframe>',
            'https://www.google.com\\@evil.example/maps/embed?pb=!1m18', str_repeat('x', 12001),
        ] as $value) {
            $this->assertNull(GoogleMapsEmbed::normalize($value), $value);
        }
    }
}
