<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $publicAcademy = data_get($page, 'props.academy');
            $publicSeo = data_get($page, 'props.site.seo');
            $isPublicAcademyPage = str_starts_with((string) data_get($page, 'component', ''), 'public/')
                && is_array($publicAcademy)
                && is_array($publicSeo);
        @endphp

        {{--
            Academy website pages: rendered here so link previews and search engines see the academy.
            Each tag carries data-inertia, so the page's <Head> replaces it once the app starts.
        --}}
        @if ($isPublicAcademyPage)
            <title data-inertia="seo-title">{{ data_get($publicSeo, 'title', data_get($publicAcademy, 'name')) }}</title>
            <meta data-inertia="seo-description" name="description" content="{{ data_get($publicSeo, 'description') }}">
            <meta data-inertia="seo-keywords" name="keywords" content="{{ data_get($publicSeo, 'keywords') }}">
            <meta data-inertia="seo-robots" name="robots" content="{{ data_get($publicSeo, 'robots', 'index, follow') }}">
            <meta data-inertia="seo-theme" name="theme-color" content="{{ data_get($publicAcademy, 'theme.primary') }}">
            <link data-inertia="seo-canonical" rel="canonical" href="{{ data_get($publicSeo, 'canonical_url') }}">
            <meta data-inertia="seo-og-type" property="og:type" content="website">
            <meta data-inertia="seo-og-site" property="og:site_name" content="{{ data_get($publicAcademy, 'name') }}">
            <meta data-inertia="seo-og-title" property="og:title" content="{{ data_get($publicSeo, 'title') }}">
            <meta data-inertia="seo-og-description" property="og:description" content="{{ data_get($publicSeo, 'description') }}">
            <meta data-inertia="seo-og-url" property="og:url" content="{{ data_get($publicSeo, 'canonical_url') }}">
            <meta data-inertia="seo-twitter-card" name="twitter:card" content="{{ data_get($publicSeo, 'image_url') ? 'summary_large_image' : 'summary' }}">
            <meta data-inertia="seo-twitter-title" name="twitter:title" content="{{ data_get($publicSeo, 'title') }}">
            <meta data-inertia="seo-twitter-description" name="twitter:description" content="{{ data_get($publicSeo, 'description') }}">

            @if (data_get($publicSeo, 'image_url'))
                <meta data-inertia="seo-og-image" property="og:image" content="{{ data_get($publicSeo, 'image_url') }}">
                <meta data-inertia="seo-og-image-alt" property="og:image:alt" content="{{ data_get($publicSeo, 'title') }}">
                <meta data-inertia="seo-twitter-image" name="twitter:image" content="{{ data_get($publicSeo, 'image_url') }}">
            @endif

            @if (data_get($publicSeo, 'icon_url'))
                <link data-inertia="seo-icon" rel="icon" href="{{ data_get($publicSeo, 'icon_url') }}" sizes="any">
                <link data-inertia="seo-apple-icon" rel="apple-touch-icon" href="{{ data_get($publicSeo, 'icon_url') }}">
            @endif

            @if (data_get($publicSeo, 'structured_data'))
                <script data-inertia="seo-ld" type="application/ld+json">{!! json_encode(data_get($publicSeo, 'structured_data'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
            @endif
        @endif

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: #F3F7FB;
            }

            html.dark {
                background-color: #04132F;
            }
        </style>

        @unless ($isPublicAcademyPage)
            <link rel="icon" href="{{ asset('images/bootcademy-mark.png') }}?v=bootcademy" type="image/png" sizes="256x256">
            <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=bootcademy">
            <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v=bootcademy" sizes="180x180">
        @endunless

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            @unless ($isPublicAcademyPage)
                <title>{{ config('app.name', 'Bootcademy') }}</title>
            @endunless
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
