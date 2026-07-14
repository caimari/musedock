<?php
/**
 * Plugin Name: Node Agent
 * Description: API privada para ejecución local de aprovisionamiento en nodos CMS
 * Version: 0.2.0
 * Author: MuseDock
 * Author URI: https://musedock.com
 * Requires PHP: 8.0
 * Requires MuseDock: 2.0.0
 * Namespace: NodeAgent
 */

namespace NodeAgent;

use Screenart\Musedock\Logger;

if (!defined('APP_ROOT')) {
    exit('No direct access allowed');
}

define('NODE_AGENT_PLUGIN_DIR', __DIR__);
define('NODE_AGENT_VERSION', '0.2.0');

spl_autoload_register(function ($class) {
    $prefix = 'NodeAgent\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

Logger::debug('Node Agent plugin loaded - v' . NODE_AGENT_VERSION);
