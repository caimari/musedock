<?php
require_once '../vendor/autoload.php';

header('Content-Type: text/plain');

echo "=== TEST DE CONFIGURACIÓN DE LOGOS ===\n\n";

echo "1. Valores en BD:\n";
echo "   site_logo: " . setting('site_logo', 'NULL') . "\n";
echo "   site_favicon: " . setting('site_favicon', 'NULL') . "\n";
echo "   show_logo: " . setting('show_logo', 'NULL') . "\n";
echo "   show_title: " . setting('show_title', 'NULL') . "\n";

echo "\n2. URLs completas (como se pasan a React):\n";
$logo = setting('site_logo') ? asset(setting('site_logo')) : '';
$favicon = setting('site_favicon') ? asset(setting('site_favicon')) : '';
echo "   site_logo: $logo\n";
echo "   site_favicon: $favicon\n";

echo "\n3. Booleanos:\n";
$show_logo = setting('show_logo', '1') === '1';
$show_title = setting('show_title', '1') === '1';
echo "   show_logo: " . var_export($show_logo, true) . "\n";
echo "   show_title: " . var_export($show_title, true) . "\n";

echo "\n4. Archivos físicos:\n";
$logoPath = __DIR__ . '/assets/uploads/logos/691523a241fee.png';
$faviconPath = __DIR__ . '/assets/uploads/logos/691523a242345.ico';
echo "   Logo existe: " . (file_exists($logoPath) ? 'SI ✓' : 'NO ✗') . "\n";
echo "   Favicon existe: " . (file_exists($faviconPath) ? 'SI ✓' : 'NO ✗') . "\n";

echo "\n✅ Todo listo! Prueba accediendo a la home del sitio.\n";
