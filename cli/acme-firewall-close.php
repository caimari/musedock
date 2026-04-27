<?php
/**
 * Cierra reglas temporales de firewall creadas por ACME Assistant.
 *
 * Uso:
 *   php cli/acme-firewall-close.php --ticket=foo --delay=1800
 */

define('APP_ROOT', realpath(__DIR__ . '/..'));
require_once APP_ROOT . '/vendor/autoload.php';

use Screenart\Musedock\Services\AcmeAssistantService;

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Este script solo se puede ejecutar por CLI.\n");
    exit(1);
}

$ticket = 'manual';
$delay = 0;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--ticket=')) {
        $ticket = substr($arg, 9);
    } elseif (str_starts_with($arg, '--delay=')) {
        $delay = (int)substr($arg, 8);
    }
}

$ticket = preg_replace('/[^a-zA-Z0-9_\-]/', '', $ticket) ?: 'manual';
$delay = max(0, min(3600, $delay));

if ($delay > 0) {
    sleep($delay);
}

$service = new AcmeAssistantService();
$result = $service->removeTemporaryPorts($ticket);

if (!($result['success'] ?? false)) {
    fwrite(STDERR, "[acme-firewall-close] ERROR: " . ($result['message'] ?? 'No se pudo cerrar reglas.') . "\n");
    exit(1);
}

echo "[acme-firewall-close] OK ticket={$ticket} removed=" . implode(',', $result['removed_ports'] ?? []) . "\n";
exit(0);

