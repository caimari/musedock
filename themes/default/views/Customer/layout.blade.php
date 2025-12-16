<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Customer Panel - MuseDock' ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="/img/favicon.png">
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
        }
        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar-custom {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .navbar-custom .navbar-brand {
            color: white !important;
            font-weight: bold;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
        }
        .navbar-custom .navbar-brand img {
            height: 35px;
            margin-right: 10px;
            filter: brightness(0) invert(1);
        }
        .navbar-custom .nav-link {
            color: rgba(255,255,255,0.9) !important;
        }
        .navbar-custom .nav-link:hover {
            color: white !important;
        }
        .sidebar {
            background: white;
            min-height: calc(100vh - 56px);
            border-right: 1px solid #e0e0e0;
            padding: 20px;
        }
        .sidebar .user-info {
            text-align: center;
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            margin-bottom: 20px;
        }
        .sidebar .user-info .avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            color: white;
            font-size: 2rem;
            font-weight: bold;
        }
        .sidebar .nav-link {
            color: #333;
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 5px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover {
            background: #f8f9fa;
            color: var(--primary-color);
        }
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .main-content {
            padding: 30px;
        }
    </style>
    @yield('styles')
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="/customer/dashboard">
                <img src="/assets/logo2_footer.png" alt="MuseDock">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($customer['name'] ?? '') ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="logout(); return false;">
                            <i class="bi bi-box-arrow-right"></i> Cerrar sesion
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar d-none d-md-block">
                <div class="user-info">
                    <div class="avatar"><?= strtoupper(substr($customer['name'] ?? 'U', 0, 1)) ?></div>
                    <h6><?= htmlspecialchars($customer['name'] ?? '') ?></h6>
                    <small class="text-muted"><?= htmlspecialchars($customer['email'] ?? '') ?></small>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link <?= ($current_page ?? '') === 'dashboard' ? 'active' : '' ?>" href="/customer/dashboard">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                    <a class="nav-link <?= ($current_page ?? '') === 'profile' ? 'active' : '' ?>" href="/customer/profile">
                        <i class="bi bi-person"></i> Mi Perfil
                    </a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                @yield('content')
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function logout() {
            Swal.fire({
                title: 'Cerrar sesion?',
                text: "Estas seguro que deseas salir?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#667eea',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Si, cerrar sesion',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '/customer/logout';
                }
            });
        }
    </script>
    @yield('scripts')
</body>
</html>
