<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Customer Panel - MuseDock' ?></title>

    <!-- CSS -->
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    @yield('styles')

    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --gradient: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        /* Header Simple */
        .customer-header {
            background: white;
            border-bottom: 1px solid #e0e0e0;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .customer-header .logo-container {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .customer-header .logo-container img {
            height: 40px;
        }

        .customer-header .site-name {
            font-size: 1.5rem;
            font-weight: 700;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Sidebar */
        .sidebar {
            background: white;
            min-height: calc(100vh - 70px);
            border-right: 1px solid #e0e0e0;
            padding: 30px 20px;
            position: sticky;
            top: 70px;
            height: calc(100vh - 70px);
            overflow-y: auto;
        }

        .sidebar .user-info {
            text-align: center;
            padding-bottom: 25px;
            margin-bottom: 25px;
            border-bottom: 2px solid #f0f0f0;
        }

        .sidebar .user-info .avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            font-size: 2rem;
            font-weight: bold;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .sidebar .user-info h6 {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .sidebar .user-info small {
            color: #999;
            font-size: 0.85rem;
        }

        .sidebar .nav {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .sidebar .nav-link {
            color: #555;
            padding: 14px 18px;
            border-radius: 12px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            font-weight: 500;
        }

        .sidebar .nav-link:hover {
            background: #f8f9fa;
            color: var(--primary-color);
            transform: translateX(5px);
        }

        .sidebar .nav-link.active {
            background: var(--gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .sidebar .nav-link i {
            margin-right: 12px;
            font-size: 1.2rem;
        }

        .sidebar .logout-link {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }

        .sidebar .logout-link .nav-link {
            color: #dc3545;
        }

        .sidebar .logout-link .nav-link:hover {
            background: #fff5f5;
            color: #dc3545;
        }

        /* Main Content */
        .main-content {
            padding: 40px 30px;
        }

        /* Auth Pages (sin sidebar) */
        .auth-container {
            min-height: calc(100vh - 70px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                position: relative;
                min-height: auto;
                height: auto;
                border-right: none;
                border-bottom: 1px solid #e0e0e0;
            }

            .sidebar .user-info {
                padding: 15px 0;
                margin-bottom: 15px;
            }

            .sidebar .user-info .avatar {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }

            .main-content {
                padding: 20px 15px;
            }

            .customer-header .site-name {
                font-size: 1.2rem;
            }
        }

        /* Mobile Sidebar Toggle */
        .mobile-sidebar-toggle {
            display: none;
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1001;
            background: var(--gradient);
            color: white;
            border: none;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .mobile-sidebar-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.5rem;
            }

            .sidebar.mobile-hidden {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Header Simple -->
    <header class="customer-header">
        <div class="container-fluid">
            <div class="logo-container">
                <img src="/assets/logo2_footer.png" alt="MuseDock">
                <div class="site-name">MuseDock</div>
            </div>
        </div>
    </header>

    <!-- Contenido Principal -->
    <div class="container-fluid">
        <div class="row">
            <?php if (isset($customer)): ?>
            <!-- Layout con Sidebar (Usuario Autenticado) -->
            <div class="col-md-3 col-lg-2 sidebar mobile-hidden" id="sidebar">
                <div class="user-info">
                    <div class="avatar"><?= strtoupper(substr($customer['name'], 0, 1)) ?></div>
                    <h6><?= htmlspecialchars($customer['name']) ?></h6>
                    <small><?= htmlspecialchars($customer['email']) ?></small>
                </div>

                <nav class="nav">
                    <a class="nav-link <?= ($current_page ?? '') === 'dashboard' ? 'active' : '' ?>" href="/customer/dashboard">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                    <a class="nav-link <?= ($current_page ?? '') === 'profile' ? 'active' : '' ?>" href="/customer/profile">
                        <i class="bi bi-person"></i> Mi Perfil
                    </a>
                </nav>

                <div class="logout-link">
                    <a class="nav-link" href="#" onclick="logout(); return false;">
                        <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                    </a>
                </div>
            </div>

            <div class="col-md-9 col-lg-10 main-content">
                <?= $content ?>
            </div>

            <!-- Mobile Sidebar Toggle -->
            <button class="mobile-sidebar-toggle" onclick="toggleSidebar()">
                <i class="bi bi-list"></i>
            </button>

            <?php else: ?>
            <!-- Layout sin Sidebar (Login/Register/Forgot Password) -->
            <div class="col-12">
                <div class="auth-container">
                    <?= $content ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @yield('scripts')

    <script>
        // Logout function
        function logout() {
            Swal.fire({
                title: '¿Cerrar sesión?',
                text: "¿Estás seguro que deseas salir?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#667eea',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, cerrar sesión',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '/customer/logout';
                }
            });
        }

        // Toggle sidebar on mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.classList.toggle('mobile-hidden');
            }
        }

        // Hide sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 768) {
                const sidebar = document.getElementById('sidebar');
                const toggle = document.querySelector('.mobile-sidebar-toggle');

                if (sidebar && !sidebar.contains(event.target) && !toggle.contains(event.target)) {
                    sidebar.classList.add('mobile-hidden');
                }
            }
        });
    </script>
</body>
</html>
