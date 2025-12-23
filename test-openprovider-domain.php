#!/usr/bin/env php
<?php
/**
 * Test script para verificar estado del dominio en OpenProvider
 */

require_once __DIR__ . '/core/bootstrap.php';

use CaddyDomainManager\Services\OpenProviderService;
use Screenart\Musedock\Logger;

$domainId = 15910; // El ID que aparece en los logs

try {
    $openProvider = new OpenProviderService();

    echo "Obteniendo información del dominio ID: {$domainId}\n\n";

    $domain = $openProvider->getDomain($domainId);

    if (!$domain) {
        echo "❌ Dominio no encontrado\n";
        exit(1);
    }

    echo "=== INFORMACIÓN DEL DOMINIO ===\n";
    echo "Nombre: " . ($domain['domain']['name'] ?? 'N/A') . "." . ($domain['domain']['extension'] ?? 'N/A') . "\n";
    echo "Estado: " . ($domain['status'] ?? 'N/A') . "\n";
    echo "Bloqueado: " . (($domain['is_locked'] ?? false) ? 'SÍ' : 'NO') . "\n";
    echo "Auto-renovación: " . ($domain['autorenew'] ?? 'N/A') . "\n";
    echo "Fecha activación: " . ($domain['activation_date'] ?? 'N/A') . "\n";
    echo "Fecha expiración: " . ($domain['expiration_date'] ?? 'N/A') . "\n\n";

    echo "=== NAMESERVERS ACTUALES ===\n";
    if (isset($domain['name_servers']) && is_array($domain['name_servers'])) {
        foreach ($domain['name_servers'] as $i => $ns) {
            $nsName = is_array($ns) ? ($ns['name'] ?? $ns['ns'] ?? 'N/A') : $ns;
            echo ($i + 1) . ". " . $nsName . "\n";
        }
    } else {
        echo "No hay nameservers configurados\n";
    }

    echo "\n=== PROTECCIONES ===\n";
    echo "Lock del dominio: " . (($domain['is_locked'] ?? false) ? '🔒 BLOQUEADO' : '🔓 Desbloqueado') . "\n";
    echo "Protección WHOIS: " . (($domain['is_private_whois_enabled'] ?? false) ? 'Activada' : 'Desactivada') . "\n";

    echo "\n=== ANÁLISIS ===\n";

    if (isset($domain['status'])) {
        switch ($domain['status']) {
            case 'ACT':
                echo "✅ Estado: ACTIVO - El dominio está operativo\n";
                break;
            case 'REQ':
                echo "⏳ Estado: EN PROCESO - El dominio aún se está registrando\n";
                echo "⚠️  NO puedes cambiar nameservers hasta que esté ACT (activo)\n";
                break;
            case 'PEN':
                echo "⏳ Estado: PENDIENTE - Esperando validación\n";
                break;
            default:
                echo "❓ Estado desconocido: {$domain['status']}\n";
        }
    }

    if ($domain['is_locked'] ?? false) {
        echo "⚠️  El dominio está BLOQUEADO (transfer lock)\n";
        echo "   Esto puede impedir cambios de nameservers en algunos TLDs\n";
    }

    // Verificar si los NS que queremos usar responden
    echo "\n=== VALIDACIÓN DE NS DESTINO ===\n";
    $targetNS = ['ns1.he.net', 'ns2.he.net'];
    foreach ($targetNS as $ns) {
        echo "Probando {$ns}... ";
        $ip = gethostbyname($ns);
        if ($ip && $ip !== $ns) {
            echo "✅ Resuelve a: {$ip}\n";
        } else {
            echo "❌ NO RESUELVE\n";
        }
    }

    echo "\n=== JSON COMPLETO ===\n";
    echo json_encode($domain, JSON_PRETTY_PRINT) . "\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    Logger::error("Test OpenProvider domain failed: " . $e->getMessage());
    exit(1);
}
