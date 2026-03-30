#!/usr/bin/env php
<?php

/**
 * Script CLI: Ejecutar Tareas Programadas (Cron Real)
 *
 * Este script se ejecuta desde el crontab del sistema.
 * NO debe ser accesible vía web.
 *
 * USO:
 * Agregar al crontab - ejecutar cada hora:
 * 0 * * * * /usr/bin/php /ruta/a/musedock/cli/cron.php >> /var/log/musedock-cron.log 2>&1
 *
 * O cada 6 horas:
 * 0 (asterisco)/6 * * * /usr/bin/php /ruta/a/musedock/cli/cron.php >> /var/log/musedock-cron.log 2>&1
 *
 * Verificar que ejecuta - una vez cada 5 minutos para testing:
 * (asterisco)/5 * * * * /usr/bin/php /ruta/a/musedock/cli/cron.php >> /tmp/musedock-cron.log 2>&1
 *
 * SEGURIDAD:
 * - Solo ejecutable desde CLI (verificación SAPI)
 * - No expuesto vía web (.htaccess en /cli)
 * - Usa locks para evitar ejecuciones concurrentes
 *
 * @package Screenart\Musedock\CLI
 */

// Verificar que se ejecuta desde CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Este script solo puede ejecutarse desde línea de comandos (CLI)');
}

// Determinar la ruta raíz del proyecto
define('APP_ROOT', dirname(__DIR__));

// Cargar autoloader de Composer si existe
if (file_exists(APP_ROOT . '/vendor/autoload.php')) {
    require_once APP_ROOT . '/vendor/autoload.php';
}

// Cargar sistema de .env del framework
if (file_exists(APP_ROOT . '/core/Env.php')) {
    require_once APP_ROOT . '/core/Env.php';
    \Screenart\Musedock\Env::load();
} else {
    // Fallback: cargar .env manualmente (sin parse_ini_file que es muy estricto)
    if (file_exists(APP_ROOT . '/.env')) {
        $lines = file(APP_ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Ignorar comentarios
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            // Parsear línea KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }
}

// Cargar archivo de configuración (bootstrap básico)
if (file_exists(APP_ROOT . '/config/config.php')) {
    require_once APP_ROOT . '/config/config.php';
} elseif (file_exists(APP_ROOT . '/config.php')) {
    require_once APP_ROOT . '/config.php';
}

// Importar clases necesarias
use Screenart\Musedock\Services\CronService;
use Screenart\Musedock\Services\Tasks\CleanupTrashTask;
use Screenart\Musedock\Services\Tasks\CleanupRevisionsTask;

// Banner
echo "\n";
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║  MUSEDOCK CMS - Tareas Programadas (Cron)              ║\n";
echo "║  " . date('Y-m-d H:i:s') . "                                      ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n";
echo "\n";

// Verificar modo de cron
$cronMode = getenv('CRON_MODE') ?: 'pseudo';

if ($cronMode !== 'real') {
    echo "⚠️  ADVERTENCIA: CRON_MODE no está configurado como 'real'\n";
    echo "   Valor actual: {$cronMode}\n";
    echo "   Para usar este script, configura CRON_MODE=real en .env\n";
    echo "\n";

    if ($cronMode === 'disabled') {
        echo "❌ Sistema de tareas programadas DESACTIVADO\n";
        exit(0);
    }

    echo "ℹ️  Continuando de todas formas...\n";
    echo "\n";
}

try {
    echo "📋 Registrando tareas...\n";

    // Registrar tareas programadas
    CronService::register(
        'cleanup_trash',
        function() {
            echo "  ├─ Ejecutando: Limpieza de papelera\n";
            $result = CleanupTrashTask::run();

            if (!$result['enabled']) {
                echo "  │  └─ Desactivada en configuración\n";
                return $result;
            }

            $totalDeleted = $result['pages']['deleted'] + $result['blog_posts']['deleted'];
            echo "  │  └─ Eliminados: {$totalDeleted} items (Pages: {$result['pages']['deleted']}, Posts: {$result['blog_posts']['deleted']})\n";

            return $result;
        },
        3600 // Intervalo no importa en modo real, se controla por crontab
    );

    CronService::register(
        'cleanup_revisions',
        function() {
            echo "  ├─ Ejecutando: Limpieza de revisiones\n";
            $result = CleanupRevisionsTask::run();

            if (!$result['enabled']) {
                echo "  │  └─ Desactivada en configuración\n";
                return $result;
            }

            $totalDeleted = $result['pages']['deleted'] + $result['blog_posts']['deleted'];
            echo "  │  └─ Eliminadas: {$totalDeleted} revisiones (Pages: {$result['pages']['deleted']}, Posts: {$result['blog_posts']['deleted']})\n";

            return $result;
        },
        86400 // Intervalo no importa en modo real
    );

    echo "✓ Tareas registradas\n";
    echo "\n";

    echo "🚀 Ejecutando tareas...\n";

    // Ejecutar tareas con force=true (ignora throttle, crontab controla frecuencia)
    $results = CronService::run($force = true);

    echo "\n";
    echo "✅ COMPLETADO\n";
    echo "\n";

    // Mostrar resumen
    echo "📊 RESUMEN:\n";
    foreach ($results['executed'] as $taskName => $taskResult) {
        echo "  • {$taskName}: {$taskResult['status']}";

        if (isset($taskResult['duration'])) {
            echo " ({$taskResult['duration']}s)";
        }

        echo "\n";
    }

    echo "\n";

    exit(0);

} catch (\Exception $e) {
    echo "\n";
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    echo "\n";

    exit(1);
}
