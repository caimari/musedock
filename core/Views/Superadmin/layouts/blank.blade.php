<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Editor Visual' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @yield('head')
</head>
<body style="margin:0; padding:0;">
    @yield('content')
</body>
</html>
