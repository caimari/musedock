#!/usr/bin/env php
<?php
/**
 * Debug script to test customer login endpoint
 */

define('APP_ROOT', __DIR__);
require __DIR__ . '/core/bootstrap.php';

echo "\n=== DEBUG CUSTOMER LOGIN ===\n\n";

// Simulate POST request to /customer/login
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/customer/login';
$_SERVER['HTTP_HOST'] = 'musedock.net';
$_SERVER['HTTPS'] = 'on';

// Check if tenant is resolved
$tenant = tenant();
echo "Tenant resolved: " . (empty($tenant) ? 'NO (superadmin mode)' : 'YES - ' . ($tenant['domain'] ?? 'unknown')) . "\n";

// Check if routes are loaded
$reflection = new ReflectionClass('Screenart\Musedock\Route');
$property = $reflection->getProperty('routes');
$property->setAccessible(true);
$routes = $property->getValue();

echo "\nPOST /customer/login registered: ";
if (isset($routes['POST']['/customer/login'])) {
    echo "YES - Handler: {$routes['POST']['/customer/login']}\n";
} else {
    echo "NO\n";
    echo "\nAll POST routes with 'customer':\n";
    if (isset($routes['POST'])) {
        $found = false;
        foreach ($routes['POST'] as $path => $handler) {
            if (stripos($path, 'customer') !== false) {
                echo "  - $path => $handler\n";
                $found = true;
            }
        }
        if (!$found) {
            echo "  (none found)\n";
        }
    } else {
        echo "  No POST routes registered at all\n";
    }
}

echo "\nGET /customer/login registered: ";
if (isset($routes['GET']['/customer/login'])) {
    echo "YES - Handler: {$routes['GET']['/customer/login']}\n";
} else {
    echo "NO\n";
}

// Check if plugin routes file exists
$pluginRoutesFile = __DIR__ . '/plugins/superadmin/caddy-domain-manager/routes.php';
echo "\nPlugin routes file exists: " . (file_exists($pluginRoutesFile) ? 'YES' : 'NO') . "\n";
if (file_exists($pluginRoutesFile)) {
    echo "Plugin routes file path: $pluginRoutesFile\n";
}

// Check if plugin is active
try {
    $activePlugins = \Screenart\Musedock\Models\SuperadminPlugin::getActive();
    echo "\nActive plugins (" . count($activePlugins) . "):\n";
    foreach ($activePlugins as $plugin) {
        echo "  - {$plugin->name} (slug: {$plugin->slug})\n";
        echo "    Path: {$plugin->path}\n";
        $routesFile = $plugin->path . '/routes.php';
        echo "    Routes file exists: " . (file_exists($routesFile) ? 'YES' : 'NO') . "\n";
    }
} catch (\Exception $e) {
    echo "\nERROR getting active plugins: " . $e->getMessage() . "\n";
}

echo "\n";
