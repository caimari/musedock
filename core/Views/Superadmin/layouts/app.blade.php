<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link rel="icon" type="image/x-icon" href="/assets/superadmin/img/favicon.ico">
  <title>@yield('title', 'Dashboard MuseDock')</title>

	
  <!-- AdminKit CSS (incluye Bootstrap 5) -->
	  <link href="/assets/superadmin/css/app.css" rel="stylesheet">
	  <link href="/assets/superadmin/css/pagination.css" rel="stylesheet">
	  <link rel="preload" href="/assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2?dd67030699838ea613ee6dbda90effa6" as="font" type="font/woff2" crossorigin>
	  <link rel="stylesheet" href="/assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
		<!-- Font Awesome (LOCAL) -->
		<link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">
	  <!-- Sistema de Tickets CSS -->
  @if(setting('multi_tenant_enabled', config('multi_tenant_enabled', false)))
  <link href="/assets/superadmin/css/tickets.css" rel="stylesheet">
  @endif

  <!-- Google Font (AdminKit usa Inter) -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
  <!-- SweetAlert2 CSS (Opcional, si no está incluido en tu app.css) -->
  <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css"> -->

  <!-- Estilos personalizados para layout "unfixed" y ajustes -->
  <style>
      html, body { overflow-x: hidden !important; }
      body { min-height: 100vh; overflow-y: auto !important; }
      .wrapper { display: flex !important; width: 100%; min-height: 100vh; align-items: stretch; }

      /* Evitar apariencia de link dentro de botones <a class="btn ..."> */
      a.btn,
      a.btn:hover,
      a.btn:focus {
          text-decoration: none !important;
      }

      /* Sidebar Base (Dark theme assumed based on AdminKit default) */
      nav#sidebar.sidebar {
          position: relative !important; z-index: 1 !important; overflow-y: auto !important; overflow-x: hidden !important; flex-shrink: 0 !important; display: flex; flex-direction: column;
          transition: width 0.25s ease-in-out, min-width 0.25s ease-in-out, padding 0.25s ease-in-out, margin 0.25s ease-in-out;
          height: auto !important; min-height: 100vh; background: #222e3c !important; /* Force dark bg */ color: #dee2e6 !important; /* Default light text */
      }
      nav#sidebar.sidebar .sidebar-content { display: flex; flex-direction: column; flex-grow: 1; min-height: 100%; height: auto; overflow: visible !important; opacity: 1; transition: opacity 0.2s ease-in-out; }

      /* Sidebar Expandido */
      nav#sidebar.sidebar:not(.collapsed) { width: 220px !important; min-width: 220px !important; padding: 0 !important; margin: 0 !important; border-right: 1px solid #405063; } /* Slightly lighter border */

      /* Sidebar Colapsado */
      nav#sidebar.sidebar.collapsed { width: 0px !important; min-width: 0px !important; overflow: hidden !important; padding: 0 !important; margin: 0 !important; border-right: none !important; }
      nav#sidebar.sidebar.collapsed .sidebar-content { opacity: 0; pointer-events: none; }

      /* Sidebar Links & Icons */
      nav#sidebar.sidebar .sidebar-link, nav#sidebar.sidebar a.sidebar-link {
          padding: .75rem 1.25rem !important; /* Slightly more padding */ white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; color: rgba(233, 236, 239, .65) !important; /* Default link color */ transition: color .15s ease-in-out, background-color .15s ease-in-out; border-left: 3px solid transparent !important;
      }
      nav#sidebar.sidebar .sidebar-link > span { flex-grow: 1; overflow: hidden; text-overflow: ellipsis; }
      nav#sidebar.sidebar .sidebar-link i, nav#sidebar.sidebar a.sidebar-link i,
      nav#sidebar.sidebar .sidebar-link svg, nav#sidebar.sidebar a.sidebar-link svg {
          margin-right: 0.75rem !important; flex-shrink: 0; width: 18px; height: 18px; color: inherit; /* Icon inherits link color */
      }
      /* Link Hover/Focus (Not Active) */
       nav#sidebar.sidebar .sidebar-item:not(.active) > .sidebar-link:hover,
       nav#sidebar.sidebar .sidebar-item:not(.active) > .sidebar-link:focus {
            background-color: rgba(59, 125, 221, 0.1) !important; /* Subtle blue background */
            color: #ffffff !important; /* White text */
            border-left-color: #3b7ddd !important; /* Blue indicator border */
      }
      /* Link Activo */
      nav#sidebar.sidebar .sidebar-item.active > .sidebar-link,
      nav#sidebar.sidebar .sidebar-item > .sidebar-link[aria-expanded="true"] /* Also highlight parent when open */
       {
          background-color: rgba(59, 125, 221, 0.15) !important; /* Slightly stronger blue */
          color: #ffffff !important; /* White text */
          border-left-color: #3b7ddd !important;
      }
      nav#sidebar.sidebar .sidebar-item.active > .sidebar-link i,
      nav#sidebar.sidebar .sidebar-item.active > .sidebar-link svg,
      nav#sidebar.sidebar .sidebar-item > .sidebar-link[aria-expanded="true"] i,
      nav#sidebar.sidebar .sidebar-item > .sidebar-link[aria-expanded="true"] svg {
            color: #ffffff !important; /* White icon when active/parent open */
      }


      /* Submenu */
      nav#sidebar.sidebar .sidebar-nav .sidebar-item .sidebar-dropdown {
          background-color: rgba(0, 0, 0, 0.15) !important; padding-left: 0rem !important; list-style: none !important; overflow: hidden;
      }
      nav#sidebar.sidebar .sidebar-nav .sidebar-item .sidebar-dropdown .sidebar-item .sidebar-link {
          padding: 0.5rem 1.25rem 0.5rem 2.8rem !important; /* Indentación */ font-size: 0.85rem !important; border-left: 3px solid transparent !important; color: rgba(233,236,239,.6) !important;
      }
      /* Submenu Link Active */
      nav#sidebar.sidebar .sidebar-nav .sidebar-item .sidebar-dropdown .sidebar-item.active > .sidebar-link {
            color: #ffffff !important;
            /* background-color: transparent; */ /* Optionally remove bg for active subitems */
      }
      nav#sidebar.sidebar .sidebar-nav .sidebar-item .sidebar-dropdown .sidebar-item:not(.active) > .sidebar-link:hover,
      nav#sidebar.sidebar .sidebar-nav .sidebar-item .sidebar-dropdown .sidebar-item:not(.active) > .sidebar-link:focus {
           color: #ffffff !important; background: transparent;
       }

      /* Flecha Dropdown */
      nav#sidebar.sidebar .sidebar-link[data-bs-toggle="collapse"]::after { content: ''; border: solid currentcolor; border-width: 0 2px 2px 0; display: inline-block; padding: 3px; transform: rotate(45deg); margin-left: auto; transition: transform 0.2s ease-in-out; vertical-align: middle; flex-shrink: 0;}
      nav#sidebar.sidebar .sidebar-link[data-bs-toggle="collapse"][aria-expanded="true"]::after { transform: rotate(-135deg); margin-top: 5px; } /* Point up when open */

      /* Main Content Area */
      .main { flex-grow: 1 !important; width: auto !important; margin-left: 0 !important; padding: 0 !important; display: flex !important; flex-direction: column !important; min-height: 100vh; overflow-x: hidden; background-color: #f5f7fb; }
      .main > .navbar { position: relative !important; width: 100% !important; z-index: 10 !important; flex-shrink: 0; box-shadow: 0 0.1rem 0.2rem rgba(0,0,0,.05) !important; background-color: #ffffff; /* White navbar */ }
      .main > .navbar a.sidebar-toggle { display: flex !important; visibility: visible !important; opacity: 1 !important; color: #6c757d; /* Default icon color */ }
       .main > .navbar a.sidebar-toggle:hover { color: #3b7ddd; } /* Hover color */

      .language-dropdown { padding: 0.25rem 0 !important; min-width: 140px; }
      .language-dropdown .dropdown-item { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.75rem; font-weight: 500; }
      .language-dropdown .language-flag { width: 22px; text-align: center; font-size: 1rem; line-height: 1; }
      .language-dropdown .language-label { flex: 1; }
      .main > .content { flex-grow: 1; padding: 1.5rem !important; overflow-y: auto; width: 100%; }
      .main > footer.footer { width: 100% !important; flex-shrink: 0; padding: 1rem 1.5rem !important; background-color: #fff; border-top: 1px solid #dee2e6; }

      /* Quitar foco azul de Bootstrap en todos los elementos de formulario */
      .form-control:focus,
      .form-select:focus,
      textarea:focus,
      input:focus,
      select:focus,
      button:focus,
      .btn:focus,
      .input-group-text:focus,
      [contenteditable]:focus,
      textarea.form-control:focus,
      input.form-control:focus {
        outline: none !important;
        box-shadow: none !important;
        border-color: #ced4da !important;
      }
      /* También para focus-visible (navegadores modernos) */
      .form-control:focus-visible,
      textarea:focus-visible,
      input:focus-visible {
        outline: none !important;
        box-shadow: none !important;
        border-color: #ced4da !important;
      }

      /* Overlay Móvil */
      #sidebar-overlay { content: ''; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0,0,0,0.3); z-index: 1034; opacity: 0; transition: opacity 0.25s ease-in-out; pointer-events: none; }
      #sidebar-overlay.active { opacity: 1; pointer-events: auto; }

      /* Vista Móvil */
	      @media (max-width: 767.98px) {
          .wrapper { display: block !important; }
           nav#sidebar.sidebar {
               position: fixed !important; left: 0; top: 0; bottom: 0; height: 100vh !important;
               width: 260px !important; min-width: 260px !important; max-width: 260px !important; /* Ancho estandar móvil */
               margin-left: 0 !important; transform: translateX(-100%) !important; transition: transform 0.25s ease-in-out !important;
               z-index: 1035 !important; overflow-y: auto !important; border-right: 1px solid #405063 !important;
           }
            nav#sidebar.sidebar:not(.collapsed) { transform: translateX(0) !important; }
           .main { width: 100% !important; margin-left: 0 !important; height: auto; min-height: 100vh; display: block !important; padding-top: 56px !important; /* Ajustar si navbar es más alta */ }
           .main > .navbar { position: fixed !important; top: 0; right: 0; left: 0; z-index: 1030 !important; }
           .main > .content { padding: 1rem !important; overflow-y: visible; }
           .main > footer.footer { position: relative; z-index: 1; }
	           .navbar a.sidebar-toggle { display: flex !important; }
	      }

	      /* Hardening: evitar overrides de otras librerías */
	      a.btn,
	      a.btn:hover {
	          text-decoration: none !important;
	      }

	      /* Asegurar Bootstrap Icons incluso si otra CSS pisa `.bi` */
	      .bi {
	          font-family: bootstrap-icons !important;
	          font-style: normal;
	          font-weight: 400;
	          line-height: 1;
	          vertical-align: -0.125em;
	      }
	      .bi::before {
	          display: inline-block;
	      }

	      /* Evitar que iconos queden ocultos por reglas de `opacity/visibility` */
	      .btn .bi,
	      .btn i[class*="bi-"],
	      .btn .fa,
	      .btn svg {
	          opacity: 1 !important;
	          visibility: visible !important;
	      }
	  </style>
@stack('styles')
@stack('meta')
</head>

<body>
		
@php
  // Lógica PHP de Autenticación y Permisos (SIN CAMBIOS)
  use Screenart\Musedock\Security\SessionSecurity;
  use Screenart\Musedock\Security\PermissionManager;
  use Screenart\Musedock\Models\AdminMenu;

  $auth = SessionSecurity::getAuthenticatedUser() ?? [];
  $userName = trim(($auth['first_name'] ?? '') . ' ' . ($auth['last_name'] ?? 'Usuario'));
  $userType = ($auth['type'] ?? 'user') === 'super_admin' ? 'Super Administrador' : 'Usuario';
  $userImage = $auth['profile_image_url'] ?? '/assets/superadmin/img/default-user.png'; // Asegúrate que esta imagen exista
  $userId = $auth['id'] ?? null;
  $tenantId = $auth['tenant_id'] ?? null;
  $isSuperadmin = ($auth['type'] ?? null) === 'super_admin';
  $currentUrl = $_SERVER['REQUEST_URI'];

 // Reemplaza esto con tu lógica real para obtener el slug del tema activo
  $activeThemeSlug = 'default'; // <<--- ¡¡¡REEMPLAZAR ESTO!!!

  // Cargar menús de la base de datos
  $adminMenus = [];
  try {
    // Usar getMenusWithCustomizations para obtener menús con personalizaciones aplicadas
    $adminMenus = AdminMenu::getMenusWithCustomizations(null);
  } catch (\Exception $e) {
    error_log("Error cargando AdminMenu::getMenusWithCustomizations(): " . $e->getMessage());
    $adminMenus = [];
  }

  // Función auxiliar para verificar si una URL está activa
  // Comparación exacta o con query string, pero NO por prefijo para evitar falsos positivos
  $isUrlActive = function($url, $currentUrl) {
    if (empty($url) || $url === '#') return false;

    // Remover query string para comparación
    $currentPath = strtok($currentUrl, '?');
    $urlPath = strtok($url, '?');

    // Comparación exacta
    if ($currentPath === $urlPath) return true;

    // Comparación exacta con trailing slash
    if (rtrim($currentPath, '/') === rtrim($urlPath, '/')) return true;

    return false;
  };

  // Función para verificar si un menú padre tiene algún hijo activo
  $hasActiveChild = function($menuData, $currentUrl) use ($isUrlActive) {
    if (empty($menuData['children'])) return false;
    foreach ($menuData['children'] as $child) {
      $childUrl = $child['url'] ?? '#';
      if ($isUrlActive($childUrl, $currentUrl)) {
        return true;
      }
    }
    return false;
  };

  function generate_id($prefix = 'menu-') { return uniqid($prefix); }

  /**
   * Función para verificar si el usuario puede ver un menú
   * Usa el sistema de permisos con soporte para is_root
   *
   * @param array $menuData Array con los datos del menú incluyendo 'permission'
   * @return bool True si el usuario puede ver el menú
   */
  $canViewMenu = function($menuData) use ($isSuperadmin, $userId) {
    // Super admin con is_root=1 ve todo
    if ($isSuperadmin && $userId) {
      if (\Screenart\Musedock\Helpers\PermissionHelper::isSuperAdminRoot($userId)) {
        return true;
      }
    }

    // Si el menú no tiene permiso requerido (null), solo super_admin root puede verlo
    $requiredPermission = $menuData['permission'] ?? null;

    if ($requiredPermission === null) {
      // Menú sin permiso definido: solo accesible para is_root=1
      return false;
    }

    // Para super_admin sin is_root o usuarios normales: verificar permiso
    // userCan() internamente usa PermissionHelper::currentUserCan() que maneja is_root
    return userCan($requiredPermission);
  };
@endphp

<div class="wrapper">
	<nav id="sidebar" class="sidebar js-sidebar">
		<div class="sidebar-content">
			<a class="sidebar-brand" href="/musedock">
               <img src="/assets/superadmin/img/AdminLTELogo.png" alt="MuseDock Logo" width="32" height="32" style="opacity: 0.9; margin-right: 10px; border-radius: 3px;">
               <span class="align-middle">MuseDock</span>
            </a>

			<ul class="sidebar-nav">
                {{-- Dashboard - Siempre visible para todos los usuarios autenticados --}}
                @php
                    $isDashboardActive = $currentUrl === '/musedock/dashboard' || $currentUrl === '/musedock' || $currentUrl === '/musedock/';
                @endphp
                <li class="sidebar-item {{ $isDashboardActive ? 'active' : '' }}">
                    <a class="sidebar-link" href="/musedock/dashboard">
                        <i class="align-middle bi bi-speedometer2"></i>
                        <span class="align-middle">Dashboard</span>
                    </a>
                </li>

                {{-- Renderizar todos los menús desde la base de datos --}}
                @foreach($adminMenus as $menuKey => $menuData)
                    @php
                        $menuSlug = $menuData['slug'] ?? $menuKey;

                        // FILTRAR: Verificar si el usuario puede ver este menú (pasando array completo)
                        if (!$canViewMenu($menuData)) {
                            continue; // Saltar este menú si no tiene permiso
                        }

                        $menuUrl = $menuData['url'] ?? '#';
                        $isMenuActive = $isUrlActive($menuUrl, $currentUrl);
                        $hasChildren = !empty($menuData['children']);

                        // Filtrar hijos según permisos (pasando array completo de cada hijo)
                        $visibleChildren = [];
                        if ($hasChildren) {
                            foreach ($menuData['children'] as $childKey => $childData) {
                                if ($canViewMenu($childData)) {
                                    $visibleChildren[$childKey] = $childData;
                                }
                            }
                            // Si no hay hijos visibles, saltar el menú padre
                            if (empty($visibleChildren)) {
                                continue;
                            }
                        }

                        // Verificar si algún hijo está activo (para mantener el menú padre abierto)
                        $hasActiveChildMenu = $hasActiveChild($menuData, $currentUrl);
                        $isMenuOrChildActive = $isMenuActive || $hasActiveChildMenu;

                        // Normalizar icono usando icon_type
                        $menuIcon = $menuData['icon'] ?? 'circle';
                        $iconType = $menuData['icon_type'] ?? 'bi';

                        // Si icon_type es 'bi' y el icon no tiene prefijo 'bi-', agregarlo
                        if ($iconType === 'bi' && !str_starts_with($menuIcon, 'bi-')) {
                            $menuIcon = 'bi-' . $menuIcon;
                        }
                        // Para compatibilidad: si menuIcon empieza con "bi " (bi espacio), convertir a "bi-"
                        if (str_starts_with($menuIcon, 'bi ') && !str_contains($menuIcon, 'bi-')) {
                            $menuIcon = 'bi bi-' . str_replace('bi ', '', $menuIcon);
                        }

                        $menuTitle = $menuData['title'] ?? ucfirst($menuKey);
                    @endphp

                    @if($hasChildren)
                        {{-- Menú con submenu --}}
                        @php $menuId = generate_id('menu-'); @endphp
                        <li class="sidebar-item {{ $isMenuOrChildActive ? 'active' : '' }}">
                            <a class="sidebar-link {{ $isMenuOrChildActive ? '' : 'collapsed' }}" href="#{{ $menuId }}" data-bs-toggle="collapse" aria-expanded="{{ $isMenuOrChildActive ? 'true' : 'false' }}">
                                <span>
                                    @if(str_contains($menuIcon, 'bi-'))
                                        <i class="align-middle {{ $menuIcon }}"></i>
                                    @else
                                        <i class="align-middle" data-feather="{{ $menuIcon }}"></i>
                                    @endif
                                    <span class="align-middle">{{ $menuTitle }}</span>
                                </span>
                            </a>
                            <ul id="{{ $menuId }}" class="sidebar-dropdown list-unstyled collapse {{ $isMenuOrChildActive ? 'show' : '' }}" data-bs-parent=".sidebar-nav">
                                @foreach($visibleChildren as $childKey => $childData)
                                    @php
                                        $childUrl = $childData['url'] ?? '#';
                                        $isChildActive = $isUrlActive($childUrl, $currentUrl);
                                        $childTitle = $childData['title'] ?? ucfirst($childKey);

                                        // Normalizar icono hijo usando icon_type
                                        $childIcon = $childData['icon'] ?? 'circle';
                                        $childIconType = $childData['icon_type'] ?? 'bi';

                                        // Si icon_type es 'bi' y el icon no tiene prefijo 'bi-', agregarlo
                                        if ($childIconType === 'bi' && !str_starts_with($childIcon, 'bi-')) {
                                            $childIcon = 'bi-' . $childIcon;
                                        }
                                        // Para compatibilidad: si childIcon empieza con "bi " (bi espacio), convertir a "bi-"
                                        if (str_starts_with($childIcon, 'bi ') && !str_contains($childIcon, 'bi-')) {
                                            $childIcon = 'bi bi-' . str_replace('bi ', '', $childIcon);
                                        }
                                    @endphp
                                    <li class="sidebar-item {{ $isChildActive ? 'active' : '' }}">
                                        <a class="sidebar-link" href="{{ $childUrl }}">
                                            @if(str_contains($childIcon, 'bi-'))
                                                <i class="align-middle {{ $childIcon }}"></i>
                                            @endif
                                            <span>{{ $childTitle }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @else
                        {{-- Menú sin submenu --}}
                        <li class="sidebar-item {{ $isMenuActive ? 'active' : '' }}">
                            <a class="sidebar-link" href="{{ $menuUrl }}">
                                @if(str_contains($menuIcon, 'bi-'))
                                    <i class="align-middle {{ $menuIcon }}"></i>
                                @else
                                    <i class="align-middle" data-feather="{{ $menuIcon }}"></i>
                                @endif
                                <span class="align-middle">{{ $menuTitle }}</span>
                            </a>
                        </li>
                    @endif
                @endforeach

                {{-- Renderizar menús de módulos dinámicamente desde $GLOBALS['ADMIN_MENU'] --}}
                @php
                  $moduleMenus = $GLOBALS['ADMIN_MENU'] ?? [];

                  // Obtener todos los slugs de menús que ya están en la base de datos
                  $existingSlugs = array_column($adminMenus, 'slug');

                  // Filtrar menús que ya existen en la base de datos para evitar duplicados
                  $moduleMenus = array_filter($moduleMenus, function($menuData, $menuKey) use ($existingSlugs) {
                    // Si el menuKey (slug) ya existe en la base de datos, omitirlo
                    return !in_array($menuKey, $existingSlugs);
                  }, ARRAY_FILTER_USE_BOTH);
                @endphp

                @foreach($moduleMenus as $menuKey => $menuData)
                    @php
                        $menuUrl = $menuData['url'] ?? '#';
                        $isModuleMenuActive = $isUrlActive($menuUrl, $currentUrl);
                        $hasChildren = !empty($menuData['children']);

                        // Verificar si algún hijo está activo
                        $hasActiveModuleChild = $hasActiveChild($menuData, $currentUrl);
                        $isModuleMenuOrChildActive = $isModuleMenuActive || $hasActiveModuleChild;

                        $menuIcon = $menuData['icon'] ?? 'bi bi-circle';
                        $menuTitle = $menuData['title'] ?? ucfirst($menuKey);
                    @endphp

                    @if($hasChildren)
                        {{-- Menú con submenu --}}
                        @php $moduleMenuId = generate_id('module-menu-'); @endphp
                        <li class="sidebar-item {{ $isModuleMenuOrChildActive ? 'active' : '' }}">
                            <a class="sidebar-link {{ $isModuleMenuOrChildActive ? '' : 'collapsed' }}" href="#{{ $moduleMenuId }}" data-bs-toggle="collapse" aria-expanded="{{ $isModuleMenuOrChildActive ? 'true' : 'false' }}">
                                <i class="align-middle {{ $menuIcon }}"></i>
                                <span class="align-middle">{{ $menuTitle }}</span>
                            </a>
                            <ul id="{{ $moduleMenuId }}" class="sidebar-dropdown list-unstyled collapse {{ $isModuleMenuOrChildActive ? 'show' : '' }}" data-bs-parent=".sidebar-nav">
                                @foreach($menuData['children'] as $childKey => $childData)
                                    @php
                                        $childUrl = $childData['url'] ?? '#';
                                        $isChildActive = $isUrlActive($childUrl, $currentUrl);
                                        $childTitle = $childData['title'] ?? ucfirst($childKey);
                                        $childIcon = $childData['icon'] ?? 'bi-circle';
                                    @endphp
                                    <li class="sidebar-item {{ $isChildActive ? 'active' : '' }}">
                                        <a class="sidebar-link" href="{{ $childUrl }}">
                                            @if(str_contains($childIcon, 'bi-'))
                                                <i class="align-middle {{ $childIcon }}"></i>
                                            @endif
                                            <span>{{ $childTitle }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @else
                        {{-- Menú sin submenu --}}
                        <li class="sidebar-item {{ $isModuleMenuActive ? 'active' : '' }}">
                            <a class="sidebar-link" href="{{ $menuUrl }}">
                                <i class="align-middle {{ $menuIcon }}"></i>
                                <span class="align-middle">{{ $menuTitle }}</span>
                            </a>
                        </li>
                    @endif
                @endforeach
			</ul>
             {{-- CTA Opcional de AdminKit (lo dejamos comentado por si lo quieres usar) --}}
             {{--
             <div class="sidebar-cta">
                 <div class="sidebar-cta-content">
                     <strong class="d-inline-block mb-2">Upgrade to Pro</strong>
                     <div class="mb-3 text-sm">Are you looking for more components? Check out our premium version.</div>
                     <div class="d-grid"><a href="upgrade-to-pro.html" class="btn btn-primary">Upgrade to Pro</a></div>
                 </div>
             </div>
             --}}
		</div>
	</nav>

	{{-- Contenedor Principal --}}
	<div class="main">
		{{-- Navbar Adaptada --}}
		<nav class="navbar navbar-expand navbar-light navbar-bg">
            <a class="sidebar-toggle js-sidebar-toggle">
              <i class="hamburger align-self-center"></i>
            </a>
            <div class="navbar-collapse collapse">
                <ul class="navbar-nav navbar-align">
                    {{-- Iconos Navbar (Adaptados de AdminLTE con Feather) --}}
                    {{-- <li class="nav-item">
                        <a class="nav-icon js-search-toggle" href="#" data-bs-toggle="collapse" data-bs-target="#navbar-search-collapse" aria-expanded="false">
                            <i class="align-middle" data-feather="search"></i>
                        </a>
                    </li> --}}

                    {{-- Campanilla de Notificaciones --}}
                    @if(setting('multi_tenant_enabled', config('multi_tenant_enabled', false)))
                        <li class="nav-item dropdown" id="notifications-dropdown">
                            <a class="nav-icon dropdown-toggle" href="#" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <div class="position-relative">
                                    <i class="align-middle" data-feather="bell"></i>
                                    <span class="indicator" id="notification-badge" style="display: none;"></span>
                                </div>
                            </a>
                            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end py-0" aria-labelledby="notificationDropdown" style="min-width: 320px; max-width: 400px;">
                                <div class="dropdown-menu-header">
                                    <span id="notification-count-text">0 notificaciones nuevas</span>
                                </div>
                                <div class="list-group" id="notifications-list" style="max-height: 400px; overflow-y: auto;">
                                    <div class="text-center py-4 text-muted" id="no-notifications">
                                        <i class="align-middle" data-feather="inbox" style="width: 48px; height: 48px;"></i>
                                        <p class="mt-2 mb-0">No hay notificaciones</p>
                                    </div>
                                </div>
                                <div class="dropdown-menu-footer">
                                    <a href="#" class="text-muted" id="mark-all-read">Marcar todas como leídas</a>
                                </div>
                            </div>
                        </li>
                    @endif

                    {{-- Selector de Idioma (solo si hay más de un idioma activo) --}}
                    @php
                        $adminActiveLanguages = [];
                        try {
                            $pdo = \Screenart\Musedock\Database::connect();
                            // En /musedock solo deben mostrarse los idiomas globales (tenant_id IS NULL)
                            $stmt = $pdo->prepare("SELECT code, name FROM languages WHERE tenant_id IS NULL AND active = 1 ORDER BY order_position ASC, id ASC");
                            $stmt->execute();
                            $adminActiveLanguages = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                        } catch (\Exception $e) {
                            $adminActiveLanguages = [['code' => 'es', 'name' => 'Español'], ['code' => 'en', 'name' => 'English']];
                        }

                        // Obtener idioma del superadmin (independiente de force_lang del frontend)
                        // Prioridad: superadmin_locale > locale > lang > cookie > default
                        if (session_status() !== PHP_SESSION_ACTIVE) {
                            \Screenart\Musedock\Security\SessionSecurity::startSession();
                        }
                        $currentLocale = $_SESSION['superadmin_locale']
                            ?? $_SESSION['locale']
                            ?? $_SESSION['lang']
                            ?? $_COOKIE['superadmin_locale']
                            ?? 'es';

                        $currentUrl = $_SERVER['REQUEST_URI'] ?? '/musedock/dashboard';
                        $showAdminLangSelector = count($adminActiveLanguages) > 1;

                        // Mapeo de códigos a banderas
                        $langFlags = [
                            'es' => '🇪🇸', 'en' => '🇺🇸', 'fr' => '🇫🇷', 'de' => '🇩🇪',
                            'it' => '🇮🇹', 'pt' => '🇵🇹', 'nl' => '🇳🇱', 'ru' => '🇷🇺',
                            'zh' => '🇨🇳', 'ja' => '🇯🇵', 'ko' => '🇰🇷', 'ar' => '🇸🇦'
                        ];
                    @endphp
                    @if($showAdminLangSelector)
                    <li class="nav-item dropdown">
                        <a class="nav-icon dropdown-toggle" href="#" id="languageDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="align-middle" data-feather="globe"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end language-dropdown" aria-labelledby="languageDropdown">
                            @foreach($adminActiveLanguages as $langItem)
                                <a class="dropdown-item d-flex align-items-center gap-2{{ $currentLocale === $langItem['code'] ? ' active' : '' }}"
                                   href="/musedock/language/switch?locale={{ $langItem['code'] }}&redirect={{ urlencode($currentUrl) }}">
                                    <span class="language-flag" aria-hidden="true">{{ $langFlags[$langItem['code']] ?? '🌐' }}</span>
                                    <span class="language-label">{{ $langItem['name'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </li>
                    @endif

                    {{-- Dropdown de Usuario (usando datos PHP) --}}
                    <li class="nav-item dropdown">
                        <a class="nav-icon dropdown-toggle d-inline-block d-sm-none" href="#" data-bs-toggle="dropdown"><i class="align-middle" data-feather="settings"></i></a>
                        <a class="nav-link dropdown-toggle d-none d-sm-inline-block" href="#" data-bs-toggle="dropdown">
                            @php
                                $user = \Screenart\Musedock\Security\SessionSecurity::getAuthenticatedUser();
                                $userName = $user['name'] ?? 'Usuario';
                                $userAvatar = $user['avatar'] ?? null;

                                // Generar inicial
                                $initial = strtoupper(substr($userName, 0, 1));
                            @endphp

                            @if($userAvatar && file_exists(APP_ROOT . '/storage/avatars/' . $userAvatar))
                                <img src="/musedock/avatar/{{ $userAvatar }}" class="avatar img-fluid rounded-circle me-1" alt="{{ $userName }}" style="width: 32px; height: 32px; object-fit: cover;" />
                            @else
                                <span class="avatar rounded-circle me-1 d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-weight: 600; font-size: 14px;">
                                    {{ $initial }}
                                </span>
                            @endif
                            <span class="text-dark">{{ $userName }}</span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item" href="/musedock/profile"><i class="align-middle me-1" data-feather="user"></i> Perfil</a>
                            <a class="dropdown-item" href="/musedock/logout"><i class="align-middle me-1" data-feather="log-out"></i> Cerrar sesión</a>
                        </div>
                    </li>
                </ul>
            </div>
            {{-- Bloque de búsqueda (opcional, si se activa con JS) --}}
             {{-- <div class="navbar-search-block collapse" id="navbar-search-collapse"> ... </div> --}}
		</nav>

		{{-- Área de Contenido Principal --}}
            <main class="content">
				
				
				
{{-- MENSAJES FLASH (Procesados para SweetAlert) --}}
@php
    // Obtener mensajes flash usando la misma lógica
    $flashSuccess = function_exists('consume_flash') ? consume_flash('success') : (session()->pull('success') ?? '');
    $flashError   = function_exists('consume_flash') ? consume_flash('error')   : (session()->pull('error') ?? '');
    $flashWarning = function_exists('consume_flash') ? consume_flash('warning') : (session()->pull('warning') ?? '');
     
    // Normalizar los mensajes a arrays
    if (function_exists('consume_flash')) {
      $flashSuccess = (array) $flashSuccess; $flashError = (array) $flashError; $flashWarning = (array) $flashWarning;
    } else {
      $flashSuccess = is_array($flashSuccess) ? $flashSuccess : (!empty($flashSuccess) ? [$flashSuccess] : []);
      $flashError   = is_array($flashError) ? $flashError : (!empty($flashError) ? [$flashError] : []);
      $flashWarning = is_array($flashWarning) ? $flashWarning : (!empty($flashWarning) ? [$flashWarning] : []);
    }
    
    // Convertir a formato JSON para usar en JavaScript
    $flashSuccessJson = json_encode($flashSuccess);
    $flashErrorJson = json_encode($flashError);
    $flashWarningJson = json_encode($flashWarning);
@endphp

{{-- No mostramos alertas Bootstrap, sólo preparamos los datos para SweetAlert --}}

			<div class="container-fluid p-0">
                {{-- AQUÍ VA EL CONTENIDO DE CADA PÁGINA --}}
				@yield('content')
			</div>
		</main>

		{{-- Footer --}}
		<footer class="footer">
			<div class="container-fluid">
				<div class="row text-muted">
					<div class="col-6 text-start">
						<p class="mb-0">
							<a class="text-muted" href="https://musedock.org" target="_blank"><strong>{{ cms_version('name') }}</strong></a>
							<span class="text-muted">v{{ cms_version('version') }}</span>
							{{ cms_copyright() }}
						</p>
					</div>
                    <div class="col-6 text-end"><ul class="list-inline"><li class="list-inline-item"></li></ul></div>
				</div>
			</div>
		</footer>
	</div>
    {{-- Fin .main --}}
</div>
{{-- Fin .wrapper --}}
<div id="sidebar-overlay"></div>

<!-- AdminKit JS (incluye Bootstrap 5 JS y Popper) -->
<script src="/assets/superadmin/js/app.js"></script>
<!-- Feather Icons JS (LOCAL) -->
<script src="/assets/vendor/feather-icons/feather.min.js"></script>
<!-- SweetAlert2 JS (LOCAL) -->
<script src="/assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
<!-- Sistema de Notificaciones -->
@if(setting('multi_tenant_enabled', config('multi_tenant_enabled', false)))
<script src="/assets/superadmin/js/notifications.js"></script>
@endif
<!-- jQuery (LOCAL) -->
<script src="/assets/vendor/jquery/jquery-3.6.0.min.js"></script>
	
{{-- Script SweetAlert unificado para mensajes flash --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Configuración y datos iniciales
    const flashes = { 
        success: <?php echo json_encode($flashSuccess, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 
        error: <?php echo json_encode($flashError, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 
        warning: <?php echo json_encode($flashWarning, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> 
    };
    const appDebug = {{ config('app.debug', false) ? 'true' : 'false' }};
    let hasFlashToShow = false;
    
    // Función para crear HTML sanitizado de mensajes
    function buildMessageHtml(messages) { 
        if (!Array.isArray(messages) || messages.length === 0) return ''; 
        
        if (messages.length === 1) { 
            const d = document.createElement('div'); 
            d.textContent = messages[0]; 
            return d.innerHTML; 
        } 
        
        let l = '<ul style="text-align: left; margin:0; padding-left: 20px; list-style: disc;">'; 
        messages.forEach(m => { 
            const d = document.createElement('div'); 
            d.textContent = m; 
            l += `<li>${d.innerHTML}</li>`; 
        }); 
        l += '</ul>'; 
        return l; 
    }
    
    // Mostrar mensajes con SweetAlert
    Object.keys(flashes).forEach(function (type) { 
        const messages = flashes[type]; 
        if (messages && Array.isArray(messages) && messages.length > 0) { 
            hasFlashToShow = true; 
            
            Swal.fire({ 
                icon: type, 
                title: type === 'success' ? 'Correcto' : (type === 'error' ? 'Error' : 'Aviso'), 
                html: buildMessageHtml(messages), 
                confirmButtonColor: type === 'success' ? '#28a745' : (type === 'error' ? '#dc3545' : '#ffc107'), 
                timer: 4000, 
                timerProgressBar: true, 
                showConfirmButton: false, 
                customClass: { popup: 'swal2-popup-custom' } 
            }); 
        }
    });
    
    // Limpiar mensajes flash en el servidor si es necesario
    const clearFlashUrl = '{{ route('settings.clearFlashes') ?? '/musedock/clear-flashes' }}'; 
    const csrfToken = '{{ csrf_token() }}'; 
    
    if (hasFlashToShow && clearFlashUrl && csrfToken) { 
        setTimeout(() => { 
            fetch(clearFlashUrl, { 
                method: 'POST', 
                headers: { 
                    'X-Requested-With': 'XMLHttpRequest', 
                    'X-CSRF-TOKEN': csrfToken, 
                    'Accept': 'application/json' 
                } 
            })
            .then(response => { 
                if (appDebug) { 
                    if (response.ok) console.log('✔ Flashes limpiados en backend.'); 
                    else console.error(`Error ${response.status} limpiando flashes en backend.`); 
                } 
            })
            .catch(error => { 
                if (appDebug) console.error('Error de red/fetch limpiando flashes:', error); 
            }); 
        }, 5000); 
    } else if (hasFlashToShow && (!clearFlashUrl || !csrfToken) && appDebug) { 
        console.warn('No se pudo limpiar flashes en backend: URL o CSRF token faltante.'); 
    }
});
</script>
{{-- FIN Mensajes Flash --}}

<!-- JS para manejo de Sidebar, Overlay y Feather Icons -->
<script>
 document.addEventListener("DOMContentLoaded", function() {
    // Inicializar Feather Icons
    if (typeof feather !== 'undefined') {
        try { feather.replace(); } catch (e) { console.error("Feather icon error:", e); }
    } else { console.warn("Feather Icons library not found."); }

    const sidebarToggler = document.querySelector('.js-sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const sidebarLinks = document.querySelectorAll('#sidebar .sidebar-link');

    const mobileBreakpoint = 767.98;
    window.lastWidth = window.innerWidth;

    const manageSidebarState = () => {
        const isMobile = window.innerWidth <= mobileBreakpoint;
        const isCollapsed = !sidebar || sidebar.classList.contains('collapsed');

        if (overlay) {
            if (isMobile && !isCollapsed) { overlay.classList.add('active'); }
            else { overlay.classList.remove('active'); }
        }

        if (isMobile && !isCollapsed && window.lastWidth > mobileBreakpoint) {
             if(sidebarToggler) sidebarToggler.click();
        }

        window.lastWidth = window.innerWidth;
    };

    if (sidebar && overlay && sidebarToggler) {
        manageSidebarState(); // Estado inicial

        sidebarToggler.addEventListener('click', function(e) {
            e.preventDefault();
            // Asume que app.js alterna la clase 'collapsed' en #sidebar
            setTimeout(manageSidebarState, 50);
        });

        overlay.addEventListener('click', () => { if(sidebarToggler) sidebarToggler.click(); });

        sidebarLinks.forEach(link => {
            if (!link.hasAttribute('data-bs-toggle')) { // No cerrar en links de submenú
                link.addEventListener('click', () => {
                    if (window.innerWidth <= mobileBreakpoint && sidebar && !sidebar.classList.contains('collapsed')) {
                        if(sidebarToggler) sidebarToggler.click();
                    }
                });
            }
        });

        window.addEventListener('resize', manageSidebarState);

    } else {
         console.warn("Required elements (toggler, sidebar, overlay) not found for full JS functionality.");
    }
 });
</script>
@stack('media_manager')
@stack('scripts')

{{-- Interceptor global para errores CSRF - permite actualizar token y reintentar --}}
<script>
(function() {
    // Guardar el fetch original
    const originalFetch = window.fetch;

    // Sobrescribir fetch para interceptar errores CSRF
    window.fetch = async function(...args) {
        const response = await originalFetch.apply(this, args);

        // Detectar error CSRF (código 419)
        if (response.status === 419) {
            try {
                const clonedResponse = response.clone();
                const data = await clonedResponse.json();

                // Si el servidor envió un nuevo token CSRF, actualizarlo
                if (data.new_csrf_token) {
                    // Actualizar meta tag
                    const metaTag = document.querySelector('meta[name="csrf-token"]');
                    if (metaTag) {
                        metaTag.setAttribute('content', data.new_csrf_token);
                    }

                    // Actualizar inputs hidden de formularios
                    document.querySelectorAll('input[name="_token"], input[name="_csrf"]').forEach(input => {
                        input.value = data.new_csrf_token;
                    });

                    console.log('Token CSRF actualizado automáticamente');
                }

                // Mostrar mensaje de error con opción de recargar
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Sesión expirada',
                        text: 'Tu sesión ha expirado. Haz clic en OK para recargar la página.',
                        confirmButtonText: 'Recargar página',
                        allowOutsideClick: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.reload();
                        }
                    });
                }
            } catch (e) {
                // Si no podemos parsear JSON, solo mostrar error genérico
                console.error('Error CSRF detectado pero no se pudo procesar la respuesta');
            }
        }

        return response;
    };
})();
</script>
</body>
</html>
