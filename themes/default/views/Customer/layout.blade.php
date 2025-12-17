<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Customer Panel - MuseDock' ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/assets/img/favicon.png">

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

        /* Header */
        .customer-header {
            background: white;
            border-bottom: 1px solid #e0e0e0;
            padding: 12px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .customer-header .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .customer-header .logo-container {
            padding-left: 20px;
        }

        .customer-header .logo-container a {
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .customer-header .logo-container img {
            height: 42px;
            max-width: 180px;
            object-fit: contain;
        }

        /* User Menu Dropdown */
        .user-menu {
            position: relative;
        }

        .user-menu-trigger {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 6px 12px;
            border-radius: 25px;
            transition: all 0.3s;
            background: transparent;
            border: none;
        }

        .user-menu-trigger:hover {
            background: #f8f9fa;
        }

        .user-menu-trigger .user-avatar-small {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--gradient);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .user-menu-trigger .user-name {
            font-weight: 500;
            color: #333;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .user-menu-trigger .dropdown-icon {
            color: #999;
            font-size: 0.75rem;
        }

        .user-menu-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 8px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            min-width: 240px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s;
            z-index: 1001;
        }

        .user-menu-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .user-menu-dropdown .dropdown-header {
            padding: 16px;
            border-bottom: 1px solid #f0f0f0;
        }

        .user-menu-dropdown .dropdown-header .user-info-dropdown {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-menu-dropdown .user-avatar-dropdown {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--gradient);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .user-menu-dropdown .user-details h6 {
            margin: 0 0 2px 0;
            font-size: 0.95rem;
            font-weight: 600;
            color: #333;
        }

        .user-menu-dropdown .user-details small {
            color: #999;
            font-size: 0.8rem;
        }

        .user-menu-dropdown .dropdown-menu-items {
            padding: 8px 0;
        }

        .user-menu-dropdown .dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 18px;
            color: #333;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }

        .user-menu-dropdown .dropdown-item:hover {
            background: #f8f9fa;
        }

        .user-menu-dropdown .dropdown-item i {
            width: 20px;
            text-align: center;
            color: #667eea;
            font-size: 1.1rem;
        }

        .user-menu-dropdown .dropdown-item.logout {
            color: #dc3545;
            border-top: 1px solid #f0f0f0;
            margin-top: 4px;
        }

        .user-menu-dropdown .dropdown-item.logout i {
            color: #dc3545;
        }

        /* Sidebar */
        .sidebar {
            background: white;
            min-height: calc(100vh - 68px);
            border-right: 1px solid #e0e0e0;
            padding: 20px 15px;
            position: sticky;
            top: 68px;
            height: calc(100vh - 68px);
            overflow-y: auto;
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

        .main-content h2,
        .main-content h3,
        .main-content h4 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 25px;
        }

        /* Auth Pages (sin sidebar) */
        .auth-container {
            min-height: calc(100vh - 68px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .user-menu-trigger .user-name {
                display: none;
            }

            .user-menu-trigger .dropdown-icon {
                display: none;
            }

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
        }

        /* Mobile Sidebar Toggle - En el header */
        .mobile-sidebar-toggle {
            display: none;
            background: transparent;
            color: #333;
            border: none;
            width: 40px;
            height: 40px;
            cursor: pointer;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 15px;
        }

        @media (max-width: 768px) {
            .customer-header .header-content {
                position: relative;
            }

            .mobile-sidebar-toggle {
                display: flex;
            }

            .customer-header .logo-container {
                padding-left: 5px;
            }

            .sidebar {
                position: fixed;
                top: 68px;
                left: 0;
                width: 280px;
                height: calc(100vh - 68px);
                z-index: 1000;
                box-shadow: 2px 0 10px rgba(0,0,0,0.1);
                transform: translateX(0);
                transition: transform 0.3s ease;
            }

            .sidebar.mobile-hidden {
                transform: translateX(-100%);
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="customer-header">
        <div class="container-fluid">
            <div class="header-content">
                <!-- Mobile Sidebar Toggle (solo si está autenticado) -->
                <?php if (isset($customer)): ?>
                <button class="mobile-sidebar-toggle" onclick="toggleSidebar()">
                    <i class="bi bi-list"></i>
                </button>
                <?php endif; ?>

                <!-- Logo -->
                <div class="logo-container">
                    <a href="/customer/dashboard">
                        <img src="/assets/logo-default.png" alt="MuseDock">
                    </a>
                </div>

                <!-- User Menu (solo si está autenticado) -->
                <?php if (isset($customer)): ?>
                <div class="user-menu">
                    <button class="user-menu-trigger" onclick="toggleUserMenu()">
                        <div class="user-avatar-small">
                            <?= strtoupper(substr($customer['name'], 0, 1)) ?>
                        </div>
                        <span class="user-name"><?= htmlspecialchars($customer['name']) ?></span>
                        <i class="bi bi-chevron-down dropdown-icon"></i>
                    </button>

                    <div class="user-menu-dropdown" id="userMenuDropdown">
                        <div class="dropdown-header">
                            <div class="user-info-dropdown">
                                <div class="user-avatar-dropdown">
                                    <?= strtoupper(substr($customer['name'], 0, 1)) ?>
                                </div>
                                <div class="user-details">
                                    <h6><?= htmlspecialchars($customer['name']) ?></h6>
                                    <small><?= htmlspecialchars($customer['email']) ?></small>
                                </div>
                            </div>
                        </div>
                        <div class="dropdown-menu-items">
                            <a href="/customer/dashboard" class="dropdown-item">
                                <i class="bi bi-speedometer2"></i>
                                <span>Dashboard</span>
                            </a>
                            <a href="/customer/profile" class="dropdown-item">
                                <i class="bi bi-person"></i>
                                <span>Mi Perfil</span>
                            </a>
                            <button onclick="logout()" class="dropdown-item logout">
                                <i class="bi bi-box-arrow-right"></i>
                                <span>Cerrar Sesión</span>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Contenido Principal -->
    <div class="container-fluid">
        <div class="row">
            <?php if (isset($customer)): ?>
            <!-- Layout con Sidebar (Usuario Autenticado) -->
            <div class="col-md-3 col-lg-2 sidebar mobile-hidden" id="sidebar">
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
                @yield('content')
            </div>

            <?php else: ?>
            <!-- Layout sin Sidebar (Login/Register/Forgot Password) -->
            <div class="col-12">
                <div class="auth-container">
                    @yield('content')
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
        // Toggle user menu dropdown
        function toggleUserMenu() {
            const dropdown = document.getElementById('userMenuDropdown');
            dropdown.classList.toggle('show');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const userMenu = document.querySelector('.user-menu');
            const dropdown = document.getElementById('userMenuDropdown');

            if (dropdown && userMenu && !userMenu.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });

        // Logout function
        function logout() {
            // Close dropdown
            const dropdown = document.getElementById('userMenuDropdown');
            if (dropdown) dropdown.classList.remove('show');

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
                    // Crear un formulario para hacer POST
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '/customer/logout';

                    // Agregar CSRF token
                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = '_csrf_token';
                    csrfInput.value = '<?= csrf_token() ?>';
                    form.appendChild(csrfInput);

                    document.body.appendChild(form);
                    form.submit();
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

                if (sidebar && toggle && !sidebar.contains(event.target) && !toggle.contains(event.target)) {
                    sidebar.classList.add('mobile-hidden');
                }
            }
        });
    </script>
</body>
</html>
