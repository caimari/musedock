@php
/**
 * Partial para renderizar una columna del footer
 * Muestra menús o widgets según esté configurado
 */

$pdo = \Screenart\Musedock\Database::connect();
$currentLang = function_exists('detectLanguage') ? detectLanguage() : ($_SESSION['lang'] ?? setting('language', 'es'));

// Verificar si existe un menú para esta ubicación
$stmt = $pdo->prepare("SELECT m.id, m.show_title FROM site_menus m WHERE m.location = :location LIMIT 1");
$stmt->execute([':location' => $location]);
$menuData = $stmt->fetch(\PDO::FETCH_ASSOC);
$hasMenu = !empty($menuData);

$menuTitle = '';
$showMenuTitle = true;
if ($hasMenu) {
    $showMenuTitle = (bool)($menuData['show_title'] ?? 1);
    $stmt = $pdo->prepare("
        SELECT mt.title
        FROM site_menu_translations mt
        WHERE mt.menu_id = :menu_id AND mt.locale = :locale
        ORDER BY mt.id DESC LIMIT 1
    ");
    $stmt->execute([':menu_id' => $menuData['id'], ':locale' => $currentLang]);
    $menuTitle = $stmt->fetchColumn();
}
@endphp

@if($hasMenu)
    {{-- Si hay menú definido, mostrarlo --}}
    <div class="footer-menu-column">
        @if($menuTitle && $showMenuTitle)
            <h4 class="text-lg font-bold mb-4 text-white">{{ $menuTitle }}</h4>
        @endif

        @custommenu($location, null, [
            'nav_class' => 'space-y-3',
            'li_class' => '',
            'a_class' => 'text-gray-400 hover:text-primary-400 transition-colors block'
        ])
    </div>
@else
    {{-- Si no hay menú, intentar mostrar widgets --}}
    @include('partials.widget-renderer', ['areaSlug' => $location])
@endif
