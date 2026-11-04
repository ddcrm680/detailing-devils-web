<title>{{ $metadata->title }}</title>
<meta name="description" content="{{ $metadata->description }}">
<link rel="canonical" href="{{ $metadata->canonical }}">
<meta name="robots" content="{{ $metadata->robots }}">
<meta property="og:title" content="{{ $metadata->ogTitle ?? $metadata->title }}">
<meta property="og:description" content="{{ $metadata->ogDescription ?? $metadata->description }}">
<meta property="og:type" content="{{ $metadata->ogType }}">
<meta property="og:url" content="{{ $metadata->canonical }}">
@if ($metadata->image)
    <meta property="og:image" content="{{ $metadata->image }}">
@endif
<meta name="twitter:card" content="{{ $metadata->image ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $metadata->twitterTitle ?? $metadata->ogTitle ?? $metadata->title }}">
<meta name="twitter:description" content="{{ $metadata->twitterDescription ?? $metadata->ogDescription ?? $metadata->description }}">
@if ($metadata->image)
    <meta name="twitter:image" content="{{ $metadata->image }}">
@endif
@if (config('site.google_site_verification'))
    <meta name="google-site-verification" content="{{ config('site.google_site_verification') }}">
@endif
@foreach ($metadata->structuredData as $node)
    <script type="application/ld+json">{!! $node->json() !!}</script>
@endforeach
