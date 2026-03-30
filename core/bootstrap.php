<?php
/**
 * Bootstrap para scripts de migración y CLI
 * Carga el mínimo necesario para ejecutar migraciones
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', realpath(__DIR__ . '/../'));
}

// Cargar variables de entorno
require_once APP_ROOT . '/core/Env.php';
\Screenart\Musedock\Env::load();

// Cargar ModuleAutoloader para autoload de clases
require_once APP_ROOT . '/core/ModuleAutoloader.php';
\Screenart\Musedock\ModuleAutoloader::init();

// Registrar namespaces principales
\Screenart\Musedock\ModuleAutoloader::registerNamespace(
    'Screenart\\Musedock',
    APP_ROOT . '/core'
);

// Cargar Logger (requerido por Database)
require_once APP_ROOT . '/core/Logger.php';
$debug = \Screenart\Musedock\Env::get('APP_DEBUG', false);
$logLevel = $debug ? 'DEBUG' : 'ERROR';
\Screenart\Musedock\Logger::init(null, $logLevel);

// Cargar funciones helper globales
require_once APP_ROOT . '/core/helpers.php';

// Cargar clase Database y sus dependencias
require_once APP_ROOT . '/core/Database.php';
require_once APP_ROOT . '/core/Database/QueryBuilder.php';
require_once APP_ROOT . '/core/Database/DatabaseDriver.php';
require_once APP_ROOT . '/core/Database/Drivers/MySQLDriver.php';
require_once APP_ROOT . '/core/Database/Drivers/PostgreSQLDriver.php';
