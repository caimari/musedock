<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Acceder')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    {{-- Favicon --}}
    @php
        $faviconPath = site_setting('site_favicon', '');
        $faviconUrl = !empty($faviconPath) ? public_file_url($faviconPath) : '/assets/img/favicon.png';
    @endphp
    <link rel="icon" type="image/png" href="{{ $faviconUrl }}">
    <link rel="shortcut icon" href="{{ $faviconUrl }}">

    <link href="/assets/tenant/dist/css/tabler.css" rel="stylesheet" />
    <link href="/assets/tenant/dist/css/tabler-flags.css" rel="stylesheet" />
    <link href="/assets/tenant/dist/css/tabler-socials.css" rel="stylesheet" />
    <link href="/assets/tenant/dist/css/tabler-vendors.css" rel="stylesheet" />
    <link href="/assets/tenant/dist/css/tabler-marketing.css" rel="stylesheet" />
    <link href="/assets/tenant/dist/css/tabler-themes.css" rel="stylesheet" />
</head>
<body class="d-flex flex-column theme-light">
    @yield('content')
    <script src="/assets/tenant/dist/js/tabler.min.js"></script>
    @stack('scripts')
</body>
</html>
