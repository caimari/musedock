<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>@yield('title')</title>
    <link rel="alternate" type="application/rss+xml" title="{{ setting('site_name', 'MuseDock') }} RSS Feed" href="{{ url('/feed') }}">
    <link href="/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    @yield('content')
</body>
</html>
