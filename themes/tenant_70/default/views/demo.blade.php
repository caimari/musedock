@php
    $demoLocale = (($_GET['demo_lang'] ?? '') === 'es') ? 'es' : 'en';
    $demoTranslation = $page->translation($demoLocale);
    $demoContent = $demoTranslation->content ?? $page->content;
@endphp
<!doctype html>
<html lang="{{ $demoLocale }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Demo Screen Art — MuseRelay AI Agent Demo</title>
<meta name="description" content="A fictional telecom support demo powered by MuseRelay. Explore the planned WhatsApp-to-chatbot ticket journey in English and Spanish.">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/svg+xml" href="/assets/demos/screenart-70/screen-art-favicon.svg?v=1">
<meta name="theme-color" content="#122b29">
<link rel="stylesheet" href="/assets/demos/screenart-70/demo.css?v=4">
<script defer src="/assets/demos/screenart-70/demo.js?v=1"></script>
</head><body>
{!! $demoContent !!}
{{-- Future MuseRelay embed: replace the placeholder inside #muserelay-chatbot-mount.
     Configure a sandbox-only agent and verify ticket isolation before activation. --}}
{{-- Native tenant setting: managed manually from Settings > General > Custom code. --}}
@php $_demoBodyEndCode = site_setting('custom_body_end_code', ''); @endphp
@if(!empty($_demoBodyEndCode))
{!! $_demoBodyEndCode !!}
@endif
</body></html>
