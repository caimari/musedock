#!/usr/bin/env php
<?php
/**
 * Script de rollback automático
 * Generado: 2025-11-12 18:20:52
 */

define('APP_ROOT', __DIR__);
require_once __DIR__ . '/core/bootstrap.php';

use Screenart\Musedock\Database;

echo "Ejecutando rollback...\n";

// Revertir nombres de carpetas
rename(__DIR__ . '/modules/blog', __DIR__ . '/modules/Blog');
rename(__DIR__ . '/modules/media-manager', __DIR__ . '/modules/MediaManager');
echo "Carpetas revertidas\n";

echo "Rollback completado\n";
