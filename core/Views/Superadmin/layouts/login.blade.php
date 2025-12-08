<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Acceder')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {{-- Bootstrap 5 CSS --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    {{-- Estilos base AdminLTE --}}
    <link rel="stylesheet" href="/assets/superadmin/css/adminlte.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.min.css">
</head>
<body class="login-page bg-body-secondary">

    @yield('content')

    {{-- Bootstrap 5 JS Bundle (incluye Popper) --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    {{-- AdminLTE JS --}}
    <script src="/assets/superadmin/js/adminlte.js"></script>

    {{-- Scripts adicionales de las vistas --}}
    @stack('scripts')
</body>
</html>
