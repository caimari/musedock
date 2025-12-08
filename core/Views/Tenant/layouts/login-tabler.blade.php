<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Acceder')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

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
