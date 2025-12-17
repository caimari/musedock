<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Acceder')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {{-- Favicon --}}
    <link rel="icon" type="image/png" href="/assets/img/favicon.png">
    <link rel="shortcut icon" href="/assets/img/favicon.png">

    {{-- Bootstrap 5 CSS --}}
    <link href="/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    {{-- Estilos base AdminLTE --}}
    <link rel="stylesheet" href="/assets/superadmin/css/adminlte.css">
    <link rel="stylesheet" href="/assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
</head>
<body class="login-page bg-body-secondary">

    @yield('content')

    {{-- Bootstrap 5 JS Bundle (incluye Popper) --}}
    <script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    {{-- AdminLTE JS --}}
    <script src="/assets/superadmin/js/adminlte.js"></script>

    {{-- Scripts adicionales de las vistas --}}
    @stack('scripts')
</body>
</html>
