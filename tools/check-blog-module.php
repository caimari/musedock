<?php
/**
 * Script para verificar y habilitar el módulo Blog
 */

require_once __DIR__ . '/../core/Bootstrap/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';

use Screenart\Musedock\Database;

echo "=== Verificando Módulo Blog ===\n\n";

try {
    $pdo = Database::connect();

    // Verificar si el módulo existe
    $stmt = $pdo->prepare("SELECT * FROM modules WHERE slug = ? OR slug = ?");
    $stmt->execute(['blog', 'Blog']);
    $module = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($module) {
        echo "✓ Módulo blog encontrado en la base de datos\n";
        echo "  - ID: {$module['id']}\n";
        echo "  - Slug: {$module['slug']}\n";
        echo "  - Nombre: {$module['name']}\n";
        echo "  - Activo: " . ($module['active'] ? 'SÍ' : 'NO') . "\n";
        echo "  - CMS Habilitado: " . ($module['cms_enabled'] ? 'SÍ' : 'NO') . "\n\n";

        // Activar si no está activo
        if (!$module['active']) {
            echo "⚠ El módulo NO está activo. Activando...\n";
            $stmt = $pdo->prepare("UPDATE modules SET active = 1 WHERE id = ?");
            $stmt->execute([$module['id']]);
            echo "✓ Módulo activado correctamente\n\n";
        }

        if (!$module['cms_enabled']) {
            echo "⚠ El módulo NO tiene cms_enabled. Habilitando...\n";
            $stmt = $pdo->prepare("UPDATE modules SET cms_enabled = 1 WHERE id = ?");
            $stmt->execute([$module['id']]);
            echo "✓ CMS habilitado correctamente\n\n";
        }
    } else {
        echo "✗ Módulo blog NO encontrado en la base de datos\n";
        echo "  Insertando módulo...\n\n";

        $stmt = $pdo->prepare("
            INSERT INTO modules (slug, name, description, version, active, cms_enabled, created_at)
            VALUES (?, ?, ?, ?, 1, 1, NOW())
        ");
        $stmt->execute([
            'blog',
            'Blog',
            'Sistema de blog con posts, categorías y etiquetas',
            '1.0.0'
        ]);

        echo "✓ Módulo blog insertado y activado\n\n";
    }

    // Verificar las rutas del módulo
    echo "=== Verificando Rutas del Módulo ===\n";
    $routesFile = __DIR__ . '/../modules/blog/routes.php';
    if (file_exists($routesFile)) {
        echo "✓ Archivo routes.php existe: $routesFile\n";

        // Contar rutas
        $content = file_get_contents($routesFile);
        $count = substr_count($content, 'Route::');
        echo "✓ Rutas definidas: ~$count\n";
    } else {
        echo "✗ Archivo routes.php NO existe\n";
    }

    echo "\n=== Verificación Completa ===\n";
    echo "El módulo blog debería estar funcionando ahora.\n";
    echo "Accede a: https://musedock.net/musedock/blog/posts\n\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
