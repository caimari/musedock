{{-- Sidebar con soporte para menús y widgets --}}
@php
    $pdo = \Screenart\Musedock\Database::connect();
    $currentLang = function_exists('detectLanguage') ? detectLanguage() : ($_SESSION['lang'] ?? setting('language', 'es'));

    $stmt = $pdo->prepare("SELECT m.id, m.show_title FROM site_menus m WHERE m.location = 'sidebar' LIMIT 1");
    $stmt->execute();
    $sidebarMenu = $stmt->fetch(\PDO::FETCH_ASSOC);
    $hasSidebarMenu = !empty($sidebarMenu);

    $sidebarTitle = '';
    $showSidebarTitle = true;
    if ($hasSidebarMenu) {
        $showSidebarTitle = (bool)($sidebarMenu['show_title'] ?? 1);
        $stmt = $pdo->prepare("
            SELECT mt.title
            FROM site_menu_translations mt
            WHERE mt.menu_id = ? AND mt.locale = ?
            ORDER BY mt.id DESC LIMIT 1
        ");
        $stmt->execute([$sidebarMenu['id'], $currentLang]);
        $sidebarTitle = $stmt->fetchColumn();
    }
@endphp

<style>
/* Estilos para el menú del sidebar */
.sidebar-menu-widget {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
.sidebar-menu-widget .sidebar-title {
    color: #333;
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f0f0f0;
}
.sidebar-menu-widget .sidebar-nav {
    list-style: none;
    padding: 0;
    margin: 0;
}
.sidebar-menu-widget .sidebar-item {
    margin-bottom: 8px;
}
.sidebar-menu-widget .sidebar-link {
    color: #333 !important;
    text-decoration: none;
    display: block;
    padding: 8px 12px;
    border-radius: 4px;
    transition: all 0.2s ease;
}
.sidebar-menu-widget .sidebar-link:hover {
    background: #f5f5f5;
    color: #ff5e14 !important;
}
.sidebar-menu-widget .sidebar-submenu {
    list-style: none;
    padding-left: 15px;
    margin-top: 5px;
}
.sidebar-menu-widget .sidebar-submenu .sidebar-link {
    font-size: 14px;
    padding: 6px 12px;
}
</style>

<div class="sidebar">
    @if($hasSidebarMenu)
        {{-- Si hay menú asignado a sidebar, mostrarlo --}}
        <div class="sidebar-menu-widget mb-4">
            @if($sidebarTitle && $showSidebarTitle)
                <h4 class="sidebar-title">{{ $sidebarTitle }}</h4>
            @endif
            @custommenu('sidebar', null, [
                'nav_class' => 'sidebar-nav',
                'li_class' => 'sidebar-item',
                'a_class' => 'sidebar-link',
                'submenu_class' => 'sidebar-submenu'
            ])
        </div>
    @endif

    {{-- Siempre mostrar widgets (pueden coexistir con el menú) --}}
    @include('partials.widget-renderer', ['areaSlug' => 'sidebar'])
</div>
