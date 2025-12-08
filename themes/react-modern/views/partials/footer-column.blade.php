@php
/**
 * Partial para renderizar una columna del footer
 * Muestra menús o widgets según esté configurado
 */

$pdo = \Screenart\Musedock\Database::connect();
$currentLang = $_SESSION['lang'] ?? setting('language', 'es');

// Verificar si existe un menú para esta ubicación
$stmt = $pdo->prepare("SELECT COUNT(*) FROM site_menus WHERE location = :location");
$stmt->execute([':location' => $location]);
$hasMenu = (int)$stmt->fetchColumn() > 0;

$menuTitle = '';
if ($hasMenu) {
    $stmt = $pdo->prepare("
        SELECT mt.title
        FROM site_menus m
        JOIN site_menu_translations mt ON m.id = mt.menu_id
        WHERE m.location = :location AND mt.locale = :locale
        ORDER BY mt.id DESC LIMIT 1
    ");
    $stmt->execute([':location' => $location, ':locale' => $currentLang]);
    $menuTitle = $stmt->fetchColumn();
}
@endphp

@if($hasMenu)
    {{-- Si hay menú definido, mostrarlo --}}
    <div class="footer-menu-column">
        @if($menuTitle)
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
