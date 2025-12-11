<?php
/**
 * Script de diagnóstico para problemas de temas en PostgreSQL
 * Eliminar después de usar
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/core/Database.php';

use Screenart\Musedock\Database;

header('Content-Type: text/plain; charset=utf-8');

echo "=== Diagnóstico de Temas ===\n\n";

try {
    $pdo = Database::connect();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    echo "Driver: {$driver}\n\n";

    // 1. Ver todos los temas
    echo "--- Tabla themes ---\n";
    $stmt = $pdo->query("SELECT id, name, slug, active FROM themes");
    $themes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($themes as $theme) {
        echo "ID: {$theme['id']}, Name: {$theme['name']}, Slug: {$theme['slug']}, Active: " . var_export($theme['active'], true) . "\n";
    }

    // 2. Buscar tema activo
    echo "\n--- Buscar tema activo (active = 1) ---\n";
    $stmt = $pdo->query("SELECT slug FROM themes WHERE active = 1 LIMIT 1");
    $activeSlug = $stmt->fetchColumn();
    echo "Resultado: " . var_export($activeSlug, true) . "\n";

    // 3. Buscar con CAST
    echo "\n--- Buscar tema activo con CAST ---\n";
    if ($driver === 'pgsql') {
        $stmt = $pdo->query("SELECT slug FROM themes WHERE active::int = 1 LIMIT 1");
    } else {
        $stmt = $pdo->query("SELECT slug FROM themes WHERE active = 1 LIMIT 1");
    }
    $activeSlugCast = $stmt->fetchColumn();
    echo "Resultado con CAST: " . var_export($activeSlugCast, true) . "\n";

    // 4. Ver setting default_theme
    echo "\n--- Setting default_theme ---\n";
    $keyCol = Database::qi('key');
    $stmt = $pdo->query("SELECT * FROM settings WHERE {$keyCol} = 'default_theme'");
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($setting) {
        print_r($setting);
    } else {
        echo "No existe el setting default_theme\n";
    }

    // 5. Verificar tipo de dato de active
    echo "\n--- Tipo de columna active ---\n";
    if ($driver === 'pgsql') {
        $stmt = $pdo->query("SELECT column_name, data_type, column_default FROM information_schema.columns WHERE table_name = 'themes' AND column_name = 'active'");
    } else {
        $stmt = $pdo->query("SHOW COLUMNS FROM themes WHERE Field = 'active'");
    }
    $colInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    print_r($colInfo);

    // 6. Probar Theme::getActiveSlug()
    echo "\n--- Theme::getActiveSlug() ---\n";
    require_once __DIR__ . '/core/Theme.php';
    $activeTheme = \Screenart\Musedock\Theme::getActiveSlug();
    echo "Resultado: {$activeTheme}\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
