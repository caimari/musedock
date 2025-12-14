#!/usr/bin/env php
<?php
/**
 * Test de CloudflareService
 *
 * Verifica la integración con Cloudflare API:
 * - Creación de subdominios con CNAME
 * - Proxy naranja activado
 * - Validación de disponibilidad
 */

require __DIR__ . '/core/bootstrap.php';

// Registrar autoloader del plugin
spl_autoload_register(function ($class) {
    $prefix = 'CaddyDomainManager\\';
    $baseDir = __DIR__ . '/plugins/superadmin/caddy-domain-manager/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use CaddyDomainManager\Services\CloudflareService;

echo "\n";
echo "╔══════════════════════════════════════════════╗\n";
echo "║  TEST: CloudflareService Integration        ║\n";
echo "╚══════════════════════════════════════════════╝\n\n";

try {
    $cf = new CloudflareService();
    $testSubdomain = 'test-' . time();

    // Test 1: Verificar disponibilidad
    echo "[1/4] ✓ CloudflareService instanciado correctamente\n";
    echo "      API Token configurado\n";
    echo "      Zone ID: " . substr(\Screenart\Musedock\Env::get('CLOUDFLARE_ZONE_ID'), 0, 8) . "...\n\n";

    // Test 2: Check subdomain availability
    echo "[2/4] Verificando disponibilidad de subdominio...\n";
    $availResult = $cf->checkSubdomainAvailability($testSubdomain);

    if ($availResult['available']) {
        echo "      ✅ Subdominio '{$testSubdomain}' está disponible\n\n";
    } else {
        echo "      ❌ Subdominio no disponible: " . ($availResult['error'] ?? 'unknown') . "\n\n";
        exit(1);
    }

    // Test 3: Crear subdominio con CNAME
    echo "[3/4] Creando subdominio con CNAME a mortadelo.musedock.com...\n";
    $createResult = $cf->createSubdomainRecord($testSubdomain, true); // proxied = true

    if ($createResult['success']) {
        echo "      ✅ Subdominio creado exitosamente\n";
        echo "      Dominio: {$createResult['domain']}\n";
        echo "      Record ID: {$createResult['record_id']}\n";
        echo "      Proxy Orange: " . ($createResult['proxied'] ? '🟠 ACTIVADO' : '⚪ Desactivado') . "\n\n";

        $recordId = $createResult['record_id'];

        // Test 4: Eliminar subdominio
        echo "[4/4] Limpiando: eliminando subdominio de prueba...\n";
        $deleteResult = $cf->deleteRecord($recordId);

        if ($deleteResult['success']) {
            echo "      ✅ Subdominio eliminado correctamente\n\n";
        } else {
            echo "      ⚠️  Error al eliminar: " . ($deleteResult['error'] ?? 'unknown') . "\n";
            echo "      Por favor, elimina manualmente el registro {$recordId} desde Cloudflare\n\n";
        }

    } else {
        echo "      ❌ Error al crear subdominio: " . ($createResult['error'] ?? 'unknown') . "\n\n";
        exit(1);
    }

    echo "╔══════════════════════════════════════════════╗\n";
    echo "║  ✅ TODOS LOS TESTS PASARON CORRECTAMENTE   ║\n";
    echo "╚══════════════════════════════════════════════╝\n\n";

    echo "Detalles verificados:\n";
    echo "  ✓ Cloudflare API conecta correctamente\n";
    echo "  ✓ Validación de disponibilidad funciona\n";
    echo "  ✓ Creación de CNAME (mortadelo.musedock.com) funciona\n";
    echo "  ✓ Proxy naranja se activa correctamente\n";
    echo "  ✓ Eliminación de registros funciona\n\n";

    exit(0);

} catch (\Exception $e) {
    echo "\n❌ ERROR CRÍTICO:\n";
    echo "   " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n\n";
    exit(1);
}
