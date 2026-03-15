<?php
/**
 * Cross-Publisher Superadmin Plugin - Installer
 *
 * Ejecuta las migraciones al instalar el plugin.
 */

require_once __DIR__ . '/migrations/install.php';

$migration = new CrossPublisherAdminInstall();
$result = $migration->up();

return $result;
