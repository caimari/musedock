<?php
/**
 * News Aggregator Superadmin Plugin - Installer
 *
 * Ejecuta las migraciones al instalar el plugin.
 */

require_once __DIR__ . '/migrations/install.php';

$migration = new NewsAggregatorInstall();
$result = $migration->up();

return $result;
