<!DOCTYPE html>
<html lang="{{ setting('language', 'es') }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    {{-- Título dinámico --}}
    <title>{{ \Screenart\Musedock\View::yieldSection('title') ?: setting('site_name', 'MuseDock CMS') }}</title>

    {{-- Meta tags dinámicas --}}
    <meta name="description" content="{{ \Screenart\Musedock\View::yieldSection('description') ?: setting('site_description', 'Tu gestor de contenidos moderno') }}">
    <meta name="author" content="{{ setting('site_author', 'Autor del sitio') }}">
    <meta name="keywords" content="{{ \Screenart\Musedock\View::yieldSection('keywords') ?: setting('site_keywords', 'React, TypeScript, Tailwind, CMS') }}">

    {{-- Favicon --}}
    @if(setting('site_favicon'))
        <link rel="icon" type="image/x-icon" href="{{ asset(ltrim(setting('site_favicon'), '/')) }}">
    @else
        <link rel="shortcut icon" type="image/x-icon" href="{{ asset('img/favicon.png') }}">
    @endif

    {{-- Open Graph --}}
    <meta property="og:title" content="{{ \Screenart\Musedock\View::yieldSection('og_title') ?: setting('site_name', 'MuseDock') }}">
    <meta property="og:description" content="{{ \Screenart\Musedock\View::yieldSection('og_description') ?: setting('site_description', '') }}">
    <meta property="og:url" content="{{ url($_SERVER['REQUEST_URI']) }}">
    <meta property="og:site_name" content="{{ setting('site_name', 'MuseDock') }}">
    <meta property="og:type" content="website">
    @if(setting('og_image'))
        <meta property="og:image" content="{{ asset(setting('og_image')) }}">
    @endif

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ \Screenart\Musedock\View::yieldSection('twitter_title') ?: setting('site_name', '') }}">
    <meta name="twitter:description" content="{{ \Screenart\Musedock\View::yieldSection('twitter_description') ?: setting('site_description', '') }}">
    @if(setting('twitter_site'))
        <meta name="twitter:site" content="{{ setting('twitter_site') }}">
    @endif

    {{-- Canonical URL --}}
    <link rel="canonical" href="{{ url($_SERVER['REQUEST_URI']) }}">
    <meta name="robots" content="{{ trim(\Screenart\Musedock\View::yieldSection('robots', 'index,follow')) }}">

    {{-- Font Awesome para iconos --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    {{-- CSS compilado de Tailwind + React --}}
    <link rel="stylesheet" href="{{ asset('themes/react-modern/dist/style.css') }}?v={{ filemtime(APP_ROOT . '/themes/react-modern/dist/style.css') }}">

    {{-- CSS personalizado del usuario --}}
    @if(themeOption('custom_css'))
        <style>{!! themeOption('custom_css') !!}</style>
    @endif

    {{-- Stack de estilos adicionales --}}
    @stack('styles')
</head>
<body class="antialiased">
    @php
        // Obtener menú de navegación principal
        $pdo = \Screenart\Musedock\Database::connect();
        $currentLang = function_exists('detectLanguage') ? detectLanguage() : ($_SESSION['lang'] ?? setting('language', 'es'));

        // Obtener tenant_id actual para filtrar correctamente
        $tenantData = tenant();
        $tenantId = $tenantData['id'] ?? null;

        // Query del menú principal (con filtro por tenant)
        if ($tenantId) {
            $stmt = $pdo->prepare("
                SELECT m.id, mt.title, m.location
                FROM site_menus m
                JOIN site_menu_translations mt ON m.id = mt.menu_id
                WHERE m.location = 'nav' AND mt.locale = :locale AND m.tenant_id = :tenant_id
                LIMIT 1
            ");
            $stmt->execute([':locale' => $currentLang, ':tenant_id' => $tenantId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT m.id, mt.title, m.location
                FROM site_menus m
                JOIN site_menu_translations mt ON m.id = mt.menu_id
                WHERE m.location = 'nav' AND mt.locale = :locale AND m.tenant_id IS NULL
                LIMIT 1
            ");
            $stmt->execute([':locale' => $currentLang]);
        }
        $mainMenu = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Obtener items del menú si existe
        $menuItems = [];
        if ($mainMenu) {
            $stmt = $pdo->prepare("
                SELECT
                    id,
                    title,
                    link as url,
                    target,
                    parent,
                    sort
                FROM site_menu_items
                WHERE menu_id = :menu_id
                ORDER BY sort ASC
            ");
            $stmt->execute([':menu_id' => $mainMenu['id']]);
            $flatItems = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Construir estructura jerárquica
            $menuItems = buildMenuTree($flatItems);
        }

        // Preparar datos del menú para React
        $menuData = $mainMenu ? [
            'id' => $mainMenu['id'],
            'title' => $mainMenu['title'],
            'location' => $mainMenu['location'],
            'items' => $menuItems
        ] : null;

        // Obtener idiomas activos
        $stmt = $pdo->prepare("SELECT code, name FROM languages WHERE active = 1 ORDER BY id ASC");
        $stmt->execute();
        $activeLanguages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Preparar configuraciones para React
        $settings = [
            'site_name' => setting('site_name', ''),
            'site_description' => setting('site_description', ''),
            'site_logo' => setting('site_logo') ? asset(setting('site_logo')) : '',
            'site_favicon' => setting('site_favicon') ? asset(setting('site_favicon')) : '',
            'show_logo' => setting('show_logo', '1') === '1',
            'show_site_title' => setting('show_title', '1') === '1',
            'language' => $currentLang,

            // Redes sociales
            'social_facebook' => setting('social_facebook', ''),
            'social_twitter' => setting('social_twitter', ''),
            'social_instagram' => setting('social_instagram', ''),
            'social_linkedin' => setting('social_linkedin', ''),
            'social_youtube' => setting('social_youtube', ''),
            'social_pinterest' => setting('social_pinterest', ''),

            // Contacto
            'contact_email' => setting('contact_email', ''),
            'contact_phone' => setting('contact_phone', ''),
            'contact_address' => setting('contact_address', ''),
            'contact_whatsapp' => setting('contact_whatsapp', ''),

            // Footer (usando translatable_setting para soporte multiidioma)
            'footer_short_description' => translatable_setting('footer_short_description', ''),
            'footer_col4_title' => setting('footer_col4_title', 'Contacto'),
            'site_credit' => setting('site_credit', ''),

            // Theme options
            'primary_color' => themeOption('primary_color', '#667eea'),
            'secondary_color' => themeOption('secondary_color', '#764ba2'),
            'accent_color' => themeOption('accent_color', '#f59e0b'),
            'header_bg_color' => themeOption('header_bg_color', '#ffffff'),
            'header_text_color' => themeOption('header_text_color', '#1f2937'),
            'footer_bg_color' => themeOption('footer_bg_color', '#1f2937'),
            'footer_text_color' => themeOption('footer_text_color', '#ffffff'),
            'header_transparent' => themeOption('header_transparent', false),
            'header_sticky' => themeOption('header_sticky', true),
            'custom_js' => themeOption('custom_js', ''),
        ];

        // Función helper para construir árbol de menú
        function buildMenuTree($items, $parentId = null) {
            $branch = [];
            foreach ($items as $item) {
                if ($item['parent'] == $parentId) {
                    $children = buildMenuTree($items, $item['id']);
                    $menuItem = [
                        'id' => $item['id'],
                        'title' => $item['title'],
                        'url' => $item['url'],
                        'target' => $item['target'] ?? '_self',
                    ];
                    if ($children) {
                        $menuItem['children'] = $children;
                    }
                    $branch[] = $menuItem;
                }
            }
            return $branch;
        }
    @endphp

    {{-- Div oculto que contiene todos los datos para React --}}
    <div
        id="react-app-data"
        style="display: none;"
        data-settings='@json($settings)'
        data-current-lang="{{ $currentLang }}"
        data-languages='@json($activeLanguages)'
        @if($menuData)
            data-menu='@json($menuData)'
        @endif
    ></div>

    {{-- Header React Component --}}
    <div id="react-header"></div>

    {{-- Contenido principal --}}
    <main id="main-content" class="min-h-screen">
        @yield('content')
    </main>

    {{-- Footer React Component --}}
    <div id="react-footer">
        {{-- Columnas de footer renderizadas por Blade para widgets/menús dinámicos --}}
        <div style="display: none;">
            <div id="footer-col-1-content">
                @include('partials.footer-column', ['location' => 'footer1'])
            </div>
            <div id="footer-col-2-content">
                @include('partials.footer-column', ['location' => 'footer2'])
            </div>
        </div>
    </div>

    {{-- JavaScript compilado de React --}}
    <script type="module" src="{{ asset('themes/react-modern/dist/main.js') }}?v={{ filemtime(APP_ROOT . '/themes/react-modern/dist/main.js') }}"></script>

    {{-- Scripts adicionales --}}
    @stack('scripts')

    {{-- Código personalizado de seguimiento (Analytics, etc.) --}}
    @if(setting('analytics_code'))
        {!! setting('analytics_code') !!}
    @endif
</body>
</html>

@php
// Helper function para obtener opciones del tema
if (!function_exists('themeOption')) {
    function themeOption($key, $default = null) {
        static $options = null;

        // Cargar opciones del tema actual una sola vez
        if ($options === null) {
            try {
                $pdo = \Screenart\Musedock\Database::connect();
                $themeSlug = setting('default_theme', 'default');

                // Intentar obtener opciones específicas del tenant
                $tenantData = tenant();
                $tenantId = $tenantData['id'] ?? null;

                $stmt = $pdo->prepare('SELECT value FROM theme_options WHERE theme_slug = ? AND tenant_id ' . ($tenantId ? '= ?' : 'IS NULL'));
                if ($tenantId) {
                    $stmt->execute([$themeSlug, $tenantId]);
                } else {
                    $stmt->execute([$themeSlug]);
                }

                $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                $options = $result ? json_decode($result['value'], true) : [];
            } catch (\Exception $e) {
                \Screenart\Musedock\Logger::log("Error cargando theme options: " . $e->getMessage(), 'ERROR');
                $options = [];
            }
        }

        return $options[$key] ?? $default;
    }
}
@endphp
