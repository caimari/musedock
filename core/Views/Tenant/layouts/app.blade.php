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
  <link rel="stylesheet" href="/assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
  <!-- Sistema de Tickets CSS -->
  @if(setting('multi_tenant_enabled', config('multi_tenant_enabled', false)))
  <link href="/assets/superadmin/css/tickets.css" rel="stylesheet">
  @endif
  
  <style>
      html, body { overflow-x: hidden !important; }
      body { min-height: 100vh; overflow-y: auto !important; }
      .wrapper { display: flex !important; width: 100%; min-height: 100vh; align-items: stretch; }

      /* Sidebar Base */
      nav#sidebar.sidebar {
          position: relative !important; 
          z-index: 1 !important; 
          overflow-y: auto !important; 
          overflow-x: hidden !important; 
          flex-shrink: 0 !important; 
          display: flex; 
          flex-direction: column;
          transition: width 0.25s ease-in-out, min-width 0.25s ease-in-out, padding 0.25s ease-in-out, margin 0.25s ease-in-out;
          height: auto !important; 
          min-height: 100vh; 
          background: #222e3c !important; 
          color: #dee2e6 !important;
      }
      
      nav#sidebar.sidebar .sidebar-content { 
          display: flex; 
          flex-direction: column; 
          flex-grow: 1; 
          min-height: 100%; 
          height: auto; 
          overflow: visible !important; 
          opacity: 1; 
          transition: opacity 0.2s ease-in-out; 
      }

      /* Sidebar Expandido */
      nav#sidebar.sidebar:not(.collapsed) { 
          width: 220px !important; 
          min-width: 220px !important; 
          padding: 0 !important; 
          margin: 0 !important; 
          border-right: 1px solid #405063; 
      }

      /* Sidebar Colapsado */
      nav#sidebar.sidebar.collapsed { 
          width: 0px !important; 
          min-width: 0px !important; 
          overflow: hidden !important; 
          padding: 0 !important; 
          margin: 0 !important; 
          border-right: none !important; 
      }
      
      nav#sidebar.sidebar.collapsed .sidebar-content { 
          opacity: 0; 
          pointer-events: none; 
      }

      /* Sidebar Links & Icons */
      nav#sidebar.sidebar .sidebar-link, 
      nav#sidebar.sidebar a.sidebar-link {
          padding: .75rem 1.25rem !important; 
          white-space: nowrap; 
          overflow: hidden; 
          text-overflow: ellipsis; 
          display: flex; 
          align-items: center; 
          color: rgba(233, 236, 239, .65) !important; 
          transition: color .15s ease-in-out, background-color .15s ease-in-out; 
          border-left: 3px solid transparent !important;
      }
      
      nav#sidebar.sidebar .sidebar-link > span { 
          flex-grow: 1; 
          overflow: hidden; 
          text-overflow: ellipsis; 
      }
      
      nav#sidebar.sidebar .sidebar-link i, 
      nav#sidebar.sidebar a.sidebar-link i,
      nav#sidebar.sidebar .sidebar-link svg, 
      nav#sidebar.sidebar a.sidebar-link svg {
          margin-right: 0.75rem !important; 
          flex-shrink: 0; 
          width: 18px; 
          height: 18px; 
          color: inherit;
      }
      
      /* Link Hover/Focus (Not Active) */
      nav#sidebar.sidebar .sidebar-item:not(.active) > .sidebar-link:hover,
      nav#sidebar.sidebar .sidebar-item:not(.active) > .sidebar-link:focus {
          background-color: rgba(59, 125, 221, 0.1) !important; 
          color: #ffffff !important; 
          border-left-color: #3b7ddd !important;
      }
      
      /* Link Activo */
      nav#sidebar.sidebar .sidebar-item.active > .sidebar-link,
      nav#sidebar.sidebar .sidebar-item > .sidebar-link[aria-expanded="true"] {
          background-color: rgba(59, 125, 221, 0.15) !important; 
          color: #ffffff !important; 
          border-left-color: #3b7ddd !important;
      }
      
      nav#sidebar.sidebar .sidebar-item.active > .sidebar-link i,
      nav#sidebar.sidebar .sidebar-item.active > .sidebar-link svg,
      nav#sidebar.sidebar .sidebar-item > .sidebar-link[aria-expanded="true"] i,
      nav#sidebar.sidebar .sidebar-item > .sidebar-link[aria-expanded="true"] svg {
          color: #ffffff !important;
      }

      /* Submenu */
      nav#sidebar.sidebar .sidebar-nav .sidebar-item .sidebar-dropdown {
          background-color: rgba(0, 0, 0, 0.15) !important; 
          padding-left: 0rem !important; 
          list-style: none !important; 
          overflow: hidden;
      }
      
      nav#sidebar.sidebar .sidebar-nav .sidebar-item .sidebar-dropdown .sidebar-item .sidebar-link {
          padding: 0.5rem 1.25rem 0.5rem 2.8rem !important; 
          font-size: 0.85rem !important; 
          border-left: 3px solid transparent !important; 
          color: rgba(233,236,239,.6) !important;
      }
      
      /* Submenu Link Active */
      nav#sidebar.sidebar .sidebar-nav .sidebar-item .sidebar-dropdown .sidebar-item.active > .sidebar-link {
          color: #ffffff !important;
      }
      
      nav#sidebar.sidebar .sidebar-nav .sidebar-item .sidebar-dropdown .sidebar-item:not(.active) > .sidebar-link:hover,
      nav#sidebar.sidebar .sidebar-nav .sidebar-item .sidebar-dropdown .sidebar-item:not(.active) > .sidebar-link:focus {
          color: #ffffff !important; 
          background: transparent;
      }

      /* Flecha Dropdown */
      nav#sidebar.sidebar .sidebar-link[data-bs-toggle="collapse"]::after { 
          content: ''; 
          border: solid currentcolor; 
          border-width: 0 2px 2px 0; 
          display: inline-block; 
          padding: 3px; 
          transform: rotate(45deg); 
          margin-left: auto; 
          transition: transform 0.2s ease-in-out; 
          vertical-align: middle; 
          flex-shrink: 0;
      }
      
      nav#sidebar.sidebar .sidebar-link[data-bs-toggle="collapse"][aria-expanded="true"]::after { 
          transform: rotate(-135deg); 
          margin-top: 5px; 
      }

      /* Main Content Area */
      .main { 
          flex-grow: 1 !important; 
          width: auto !important; 
          margin-left: 0 !important; 
          padding: 0 !important; 
          display: flex !important; 
          flex-direction: column !important; 
          min-height: 100vh; 
          overflow-x: hidden; 
          background-color: #f5f7fb; 
      }
      
      .main > .navbar { 
          position: relative !important; 
          width: 100% !important; 
          z-index: 10 !important; 
          flex-shrink: 0; 
          box-shadow: 0 0.1rem 0.2rem rgba(0,0,0,.05) !important; 
          background-color: #ffffff; 
      }
      
      .main > .navbar a.sidebar-toggle { 
          display: flex !important; 
          visibility: visible !important; 
          opacity: 1 !important; 
          color: #6c757d; 
      }
      
      .main > .navbar a.sidebar-toggle:hover { 
          color: #3b7ddd; 
      }

      .language-dropdown {
          padding: 0.25rem 0 !important;
          min-width: 140px;
      }

      .language-dropdown .dropdown-item {
          display: flex;
          align-items: center;
          gap: 0.5rem;
          padding: 0.5rem 0.75rem;
          font-weight: 500;
      }

      .language-dropdown .language-flag {
          width: 22px;
          text-align: center;
          font-size: 1rem;
          line-height: 1;
      }

      .language-dropdown .language-label {
          flex: 1;
      }

      .main > .content { 
          flex-grow: 1; 
          padding: 1.5rem !important; 
          overflow-y: auto; 
          width: 100%; 
      }
      
      .main > footer.footer { 
          width: 100% !important; 
          flex-shrink: 0; 
          padding: 1rem 1.5rem !important; 
          background-color: #fff; 
          border-top: 1px solid #dee2e6; 
      }

      /* Quitar foco azul */
      .form-control:focus, 
      .form-select:focus { 
          outline: none !important; 
          box-shadow: none !important; 
          border-color: #ced4da !important; 
      }

      /* Overlay Móvil */
      #sidebar-overlay { 
          content: ''; 
          position: fixed; 
          top: 0; 
          left: 0; 
          right: 0; 
          bottom: 0; 
          background-color: rgba(0,0,0,0.3); 
          z-index: 1034; 
          opacity: 0; 
          transition: opacity 0.25s ease-in-out; 
          pointer-events: none; 
      }
      
      #sidebar-overlay.active { 
          opacity: 1; 
          pointer-events: auto; 
      }

      /* Vista Móvil */
      @media (max-width: 767.98px) {
          .wrapper { 
              display: block !important; 
          }
          
          nav#sidebar.sidebar {
              position: fixed !important; 
              left: 0; 
              top: 0; 
              bottom: 0; 
              height: 100vh !important;
              width: 260px !important; 
              min-width: 260px !important; 
              max-width: 260px !important;
              margin-left: 0 !important; 
              transform: translateX(-100%) !important; 
              transition: transform 0.25s ease-in-out !important;
              z-index: 1035 !important; 
              overflow-y: auto !important; 
              border-right: 1px solid #405063 !important;
          }
          
          nav#sidebar.sidebar:not(.collapsed) { 
              transform: translateX(0) !important; 
          }
          
          .main { 
              width: 100% !important; 
              margin-left: 0 !important; 
              height: auto; 
              min-height: 100vh; 
              display: block !important; 
              padding-top: 56px !important; 
          }
          
          .main > .navbar { 
              position: fixed !important; 
              top: 0; 
              right: 0; 
              left: 0; 
              z-index: 1030 !important; 
          }
          
          .main > .content { 
              padding: 1rem !important; 
              overflow-y: visible; 
          }
          
          .main > footer.footer { 
              position: relative; 
              z-index: 1; 
          }
          
          .navbar a.sidebar-toggle { 
              display: flex !important; 
          }
      }
  </style>
  
  @stack('styles')
  @stack('meta')
</head>
<body>

@php
$currentUrl = $_SERVER['REQUEST_URI'];
$adminPath = rtrim(admin_path(), '/');

// Obtener el admin_path desde .env para las URLs de tenant_menus
$adminPathTenant = '/' . trim(\Screenart\Musedock\Env::get('ADMIN_PATH_TENANT', 'admin'), '/');

// Cargar menús del tenant
$tenantId = function_exists('tenant_id') ? tenant_id() : null;
$adminMenus = [];

try {
    if ($tenantId) {
        $pdo = \Screenart\Musedock\Database::connect();

        // Filtrar menús:
        // 1. Menús sin module_id (menús del sistema) siempre se muestran
        // 2. Menús con module_id solo si el módulo está activo globalmente Y habilitado para el tenant
        $stmt = $pdo->prepare("
            SELECT tm.*
            FROM tenant_menus tm
            LEFT JOIN modules m ON tm.module_id = m.id
            LEFT JOIN tenant_modules tmod ON tmod.module_id = tm.module_id AND tmod.tenant_id = tm.tenant_id
            WHERE tm.tenant_id = ?
              AND tm.is_active = 1
              AND (
                tm.module_id IS NULL  -- Menús del sistema
                OR (
                  m.active = 1  -- Módulo activo globalmente
                  AND (tmod.enabled IS NULL OR tmod.enabled = 1)  -- Habilitado para el tenant (o sin configuración)
                )
              )
            ORDER BY tm.parent_id IS NOT NULL, tm.parent_id, tm.order_position ASC
        ");
        $stmt->execute([$tenantId]);
        $menus = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Construir jerarquía
        $result = [];
        $children = [];

        foreach ($menus as $menu) {
            // Reemplazar {admin_path} con el valor de ADMIN_PATH_TENANT del .env
            $url = $menu['url'];
            if (str_contains($url, '{admin_path}')) {
                $url = str_replace('{admin_path}', $adminPathTenant, $url);
            }
            // Asegurarnos de que empiece con /
            if (!str_starts_with($url, '/')) {
                $url = '/' . $url;
            }

            if ($menu['parent_id'] === null) {
                $result[$menu['slug']] = [
                    'id' => $menu['id'],
                    'title' => $menu['title'],
                    'url' => $url,
                    'icon' => $menu['icon'],
                    'icon_type' => $menu['icon_type'],
                    'order' => $menu['order_position'],
                    'parent' => null,
                    'children' => []
                ];
            } else {
                $children[] = array_merge($menu, ['url' => $url]);
            }
        }

        // Asignar hijos a padres
        foreach ($children as $child) {
            foreach ($result as $parentSlug => &$parentMenu) {
                if ($parentMenu['id'] == $child['parent_id']) {
                    $parentMenu['children'][$child['slug']] = [
                        'title' => $child['title'],
                        'url' => $child['url'],
                        'icon' => $child['icon'],
                        'icon_type' => $child['icon_type'],
                        'order' => $child['order_position'],
                        'permission' => $child['permission']
                    ];
                    break;
                }
            }
        }

        // Eliminar menús padres que solo tengan URL '#' y no tengan hijos visibles
        // Esto oculta menús contenedores cuando todos sus submódulos están desactivados
        foreach ($result as $slug => $menu) {
            if (($menu['url'] === '#' || empty($menu['url'])) && empty($menu['children'])) {
                unset($result[$slug]);
            }
        }

        $adminMenus = $result;
    }
} catch (\Exception $e) {
    error_log("Error cargando tenant_menus: " . $e->getMessage());
    $adminMenus = [];
}

// Funciones auxiliares
$isUrlActive = function($url, $currentUrl) {
    if (empty($url) || $url === '#') return false;
    return str_starts_with($currentUrl, $url) || $currentUrl === $url;
};

$hasActiveChild = function($menuData, $currentUrl) use ($isUrlActive) {
    if (empty($menuData['children'])) return false;
    foreach ($menuData['children'] as $child) {
        if ($isUrlActive($child['url'] ?? '#', $currentUrl)) {
            return true;
        }
    }
    return false;
};

function generate_id($prefix = 'menu-') { 
    return uniqid($prefix); 
}
@endphp

<div class="wrapper">
    <nav id="sidebar" class="sidebar js-sidebar">
        <div class="sidebar-content">
            <a class="sidebar-brand" href="{{ $adminPath }}/dashboard">
                <img src="/assets/superadmin/img/AdminLTELogo.png" alt="MuseDock Logo" width="32" height="32" style="opacity: 0.9; margin-right: 10px; border-radius: 3px;">
                <span class="align-middle">MuseDock</span>
            </a>
            
            <ul class="sidebar-nav">
                @foreach($adminMenus as $menuKey => $menuData)
                    @php
                        $menuUrl = $menuData['url'] ?? '#';
                        $isMenuActive = $isUrlActive($menuUrl, $currentUrl);
                        $hasChildren = !empty($menuData['children']);
                        $hasActiveChildMenu = $hasActiveChild($menuData, $currentUrl);
                        $isMenuOrChildActive = $isMenuActive || $hasActiveChildMenu;

                        // Normalizar icono
                        $menuIcon = $menuData['icon'] ?? 'circle';
                        $iconType = $menuData['icon_type'] ?? 'bi';

                        if ($iconType === 'bi' && !str_starts_with($menuIcon, 'bi-')) {
                            $menuIcon = 'bi-' . $menuIcon;
                        }
                        if (str_starts_with($menuIcon, 'bi ') && !str_contains($menuIcon, 'bi-')) {
                            $menuIcon = 'bi bi-' . str_replace('bi ', '', $menuIcon);
                        }

                        $menuTitle = $menuData['title'] ?? ucfirst($menuKey);
                    @endphp

                    @if($hasChildren)
                        @php $menuId = generate_id('menu-'); @endphp
                        <li class="sidebar-item {{ $isMenuOrChildActive ? 'active' : '' }}">
                            <a class="sidebar-link {{ $isMenuOrChildActive ? '' : 'collapsed' }}" 
                               href="#{{ $menuId }}" 
                               data-bs-toggle="collapse" 
                               aria-expanded="{{ $isMenuOrChildActive ? 'true' : 'false' }}">
                                <span>
                                    @if(str_contains($menuIcon, 'bi-'))
                                        <i class="align-middle {{ $menuIcon }}"></i>
                                    @elseif($iconType === 'far' || $iconType === 'fas')
                                        <i class="align-middle {{ $iconType }} fa-{{ $menuIcon }}"></i>
                                    @else
                                        <i class="align-middle" data-feather="{{ $menuIcon }}"></i>
                                    @endif
                                    <span class="align-middle">{{ $menuTitle }}</span>
                                </span>
                            </a>
                            <ul id="{{ $menuId }}" 
                                class="sidebar-dropdown list-unstyled collapse {{ $isMenuOrChildActive ? 'show' : '' }}" 
                                data-bs-parent=".sidebar-nav">
                                @foreach($menuData['children'] as $childKey => $childData)
                                    @php
                                        $childUrl = $childData['url'] ?? '#';
                                        $isChildActive = $isUrlActive($childUrl, $currentUrl);
                                        $childTitle = $childData['title'] ?? ucfirst($childKey);
                                        $childIcon = $childData['icon'] ?? 'circle';
                                        $childIconType = $childData['icon_type'] ?? 'bi';

                                        if ($childIconType === 'bi' && !str_starts_with($childIcon, 'bi-')) {
                                            $childIcon = 'bi-' . $childIcon;
                                        }
                                        if (str_starts_with($childIcon, 'bi ') && !str_contains($childIcon, 'bi-')) {
                                            $childIcon = 'bi bi-' . str_replace('bi ', '', $childIcon);
                                        }
                                    @endphp
                                    <li class="sidebar-item {{ $isChildActive ? 'active' : '' }}">
                                        <a class="sidebar-link" href="{{ $childUrl }}">
                                            @if(str_contains($childIcon, 'bi-'))
                                                <i class="align-middle {{ $childIcon }}"></i>
                                            @elseif($childIconType === 'far' || $childIconType === 'fas')
                                                <i class="align-middle {{ $childIconType }} fa-{{ $childIcon }}"></i>
                                            @endif
                                            <span>{{ $childTitle }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @else
                        <li class="sidebar-item {{ $isMenuActive ? 'active' : '' }}">
                            <a class="sidebar-link" href="{{ $menuUrl }}">
                                @if(str_contains($menuIcon, 'bi-'))
                                    <i class="align-middle {{ $menuIcon }}"></i>
                                @elseif($iconType === 'far' || $iconType === 'fas')
                                    <i class="align-middle {{ $iconType }} fa-{{ $menuIcon }}"></i>
                                @else
                                    <i class="align-middle" data-feather="{{ $menuIcon }}"></i>
                                @endif
                                <span class="align-middle">{{ $menuTitle }}</span>
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </div>
    </nav>

    <div class="main">
        <nav class="navbar navbar-expand navbar-light navbar-bg">
            <a class="sidebar-toggle js-sidebar-toggle">
                <i class="hamburger align-self-center"></i>
            </a>
            
            <div class="navbar-collapse collapse">
                <ul class="navbar-nav navbar-align">
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

                    {{-- Selector de Idioma --}}
                    <li class="nav-item dropdown">
                        <a class="nav-icon dropdown-toggle" href="#" id="languageDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="align-middle" data-feather="globe"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end language-dropdown" aria-labelledby="languageDropdown">
                            @php
                                $currentLocale = app_locale();
                                $currentUrl = $_SERVER['REQUEST_URI'] ?? admin_url('dashboard');
                            @endphp
                            <a class="dropdown-item d-flex align-items-center gap-2{{ $currentLocale === 'es' ? ' active' : '' }}" href="{{ admin_url('language/switch') }}?locale=es&redirect={{ urlencode($currentUrl) }}">
                                <span class="language-flag" aria-hidden="true">🇪🇸</span>
                                <span class="language-label">Español</span>
                            </a>
                            <a class="dropdown-item d-flex align-items-center gap-2{{ $currentLocale === 'en' ? ' active' : '' }}" href="{{ admin_url('language/switch') }}?locale=en&redirect={{ urlencode($currentUrl) }}">
                                <span class="language-flag" aria-hidden="true">🇺🇸</span>
                                <span class="language-label">English</span>
                            </a>
                        </div>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-icon dropdown-toggle d-inline-block d-sm-none" href="#" data-bs-toggle="dropdown">
                            <i class="align-middle" data-feather="settings"></i>
                        </a>
                        <a class="nav-link dropdown-toggle d-none d-sm-inline-block" href="#" data-bs-toggle="dropdown">
                            @php
                                $user = \Screenart\Musedock\Security\SessionSecurity::getAuthenticatedUser();
                                $userName = $user['name'] ?? 'Usuario';
                                $userId = $user['id'] ?? null;

                                // Cargar avatar fresco desde la BD para asegurar que esté actualizado
                                $userAvatar = null;
                                if ($userId && $user['type'] === 'admin') {
                                    try {
                                        $pdo = \Screenart\Musedock\Database::connect();
                                        $stmt = $pdo->prepare("SELECT avatar FROM admins WHERE id = ?");
                                        $stmt->execute([$userId]);
                                        $avatarFromDb = $stmt->fetchColumn();
                                        if ($avatarFromDb) {
                                            $userAvatar = $avatarFromDb;
                                            // Actualizar sesión con el avatar actual
                                            if (!isset($_SESSION['admin']['avatar']) || $_SESSION['admin']['avatar'] !== $avatarFromDb) {
                                                $_SESSION['admin']['avatar'] = $avatarFromDb;
                                            }
                                        }
                                    } catch (\Exception $e) {
                                        error_log("Error cargando avatar: " . $e->getMessage());
                                        $userAvatar = $user['avatar'] ?? null;
                                    }
                                } elseif ($user['type'] === 'super_admin') {
                                    $userAvatar = $user['avatar'] ?? null;
                                } else {
                                    $userAvatar = $user['avatar'] ?? null;
                                }

                                $initial = strtoupper(substr($userName, 0, 1));
                            @endphp

                            <span id="header-avatar-container">
                                @if($userAvatar)
                                    @php
                                        $avatarPath = APP_ROOT . '/storage/avatars/' . $userAvatar;
                                        $avatarExists = file_exists($avatarPath);
                                    @endphp
                                    @if($avatarExists)
                                        <img id="header-avatar-img"
                                             src="{{ admin_url('/avatar/' . $userAvatar) }}?v={{ filemtime($avatarPath) }}"
                                             class="avatar img-fluid rounded-circle me-1"
                                             alt="{{ $userName }}"
                                             style="width: 32px; height: 32px; object-fit: cover;" />
                                    @else
                                        <span id="header-avatar-initial"
                                              class="avatar rounded-circle me-1 d-inline-flex align-items-center justify-content-center"
                                              style="width: 32px; height: 32px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-weight: 600; font-size: 14px;">
                                            {{ $initial }}
                                        </span>
                                    @endif
                                @else
                                    <span id="header-avatar-initial"
                                          class="avatar rounded-circle me-1 d-inline-flex align-items-center justify-content-center"
                                          style="width: 32px; height: 32px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-weight: 600; font-size: 14px;">
                                        {{ $initial }}
                                    </span>
                                @endif
                            </span>
                            <span class="text-dark" id="header-user-name">{{ $userName }}</span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item" href="{{ admin_url('/profile') }}">
                                <i class="align-middle me-1" data-feather="user"></i> Perfil
                            </a>
                            <a class="dropdown-item" href="{{ admin_url('/logout') }}">
                                <i class="align-middle me-1" data-feather="log-out"></i> Cerrar sesión
                            </a>
                        </div>
                    </li>
                </ul>
            </div>
        </nav>

        <main class="content">
            @php
            $flashSuccess = function_exists('consume_flash') ? consume_flash('success') : (session()->pull('success') ?? '');
            $flashError   = function_exists('consume_flash') ? consume_flash('error')   : (session()->pull('error') ?? '');
            $flashWarning = function_exists('consume_flash') ? consume_flash('warning') : (session()->pull('warning') ?? '');
            
            if (function_exists('consume_flash')) {
                $flashSuccess = (array) $flashSuccess; 
                $flashError = (array) $flashError; 
                $flashWarning = (array) $flashWarning;
            } else {
                $flashSuccess = is_array($flashSuccess) ? $flashSuccess : (!empty($flashSuccess) ? [$flashSuccess] : []);
                $flashError   = is_array($flashError) ? $flashError : (!empty($flashError) ? [$flashError] : []);
                $flashWarning = is_array($flashWarning) ? $flashWarning : (!empty($flashWarning) ? [$flashWarning] : []);
            }
            @endphp
            
            <div class="container-fluid p-0">
                @yield('content')
            </div>
        </main>

        <footer class="footer">
            <div class="container-fluid">
                <div class="row text-muted">
                    <div class="col-6 text-start">
                        <p class="mb-0">
                            <a class="text-muted" href="https://musedock.org" target="_blank">
                                <strong>{{ cms_version('name') }}</strong>
                            </a>
                            <span class="text-muted">v{{ cms_version('version') }}</span>
                            {{ cms_copyright() }}
                        </p>
                    </div>
                    <div class="col-6 text-end">
                        <ul class="list-inline">
                            <li class="list-inline-item"></li>
                        </ul>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</div>

<div id="sidebar-overlay"></div>

<!-- Scripts -->
<script src="/assets/superadmin/js/app.js"></script>
<script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Sistema de Notificaciones -->
@if(setting('multi_tenant_enabled', config('multi_tenant_enabled', false)))
<script>
    // Definir admin_path global para el sistema de notificaciones
    window.ADMIN_PATH = '{{ admin_path() }}';
</script>
<script src="/assets/superadmin/js/notifications.js"></script>
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    const flashes = { 
        success: <?php echo json_encode($flashSuccess, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 
        error: <?php echo json_encode($flashError, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 
        warning: <?php echo json_encode($flashWarning, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> 
    };
    const appDebug = {{ config('app.debug', false) ? 'true' : 'false' }};
    let hasFlashToShow = false;
    
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
    
    const clearFlashUrl = '{{ route('settings.clearFlashes') ?? '' }}'; 
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
                if (appDebug && !response.ok) { 
                    console.error(`Error ${response.status} limpiando flashes en backend.`); 
                } 
            })
            .catch(error => { 
                if (appDebug) console.error('Error limpiando flashes:', error); 
            }); 
        }, 5000); 
    }
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    if (typeof feather !== 'undefined') {
        try { feather.replace(); } catch (e) { console.error("Feather icon error:", e); }
    }

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
            if (isMobile && !isCollapsed) { 
                overlay.classList.add('active'); 
            } else { 
                overlay.classList.remove('active'); 
            }
        }

        if (isMobile && !isCollapsed && window.lastWidth > mobileBreakpoint) {
            if(sidebarToggler) sidebarToggler.click();
        }

        window.lastWidth = window.innerWidth;
    };

    if (sidebar && overlay && sidebarToggler) {
        manageSidebarState();

        sidebarToggler.addEventListener('click', function(e) {
            e.preventDefault();
            setTimeout(manageSidebarState, 50);
        });

        overlay.addEventListener('click', () => { 
            if(sidebarToggler) sidebarToggler.click(); 
        });

        sidebarLinks.forEach(link => {
            if (!link.hasAttribute('data-bs-toggle')) {
                link.addEventListener('click', () => {
                    if (window.innerWidth <= mobileBreakpoint && sidebar && !sidebar.classList.contains('collapsed')) {
                        if(sidebarToggler) sidebarToggler.click();
                    }
                });
            }
        });

        window.addEventListener('resize', manageSidebarState);
    }
});
</script>

@stack('media_manager')
@stack('scripts')

</body>
</html>
