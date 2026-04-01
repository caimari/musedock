<?php

namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\View;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Services\LicenseClient;
use Screenart\Musedock\Traits\RequiresPermission;

class PluginStoreController
{
    use RequiresPermission;

    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

        $catalog = LicenseClient::getCatalog();

        // Filter: tenants only see CMS products (not panel)
        $catalog = array_filter($catalog, fn($p) => ($p['target'] ?? 'cms') === 'cms');

        // Check installed status
        $privatPluginsPath = defined('PRIVATE_PLUGINS_PATH') ? PRIVATE_PLUGINS_PATH
            : ($_ENV['PRIVATE_PLUGINS_PATH'] ?? '');
        $privateModulesPath = defined('PRIVATE_MODULES_PATH') ? PRIVATE_MODULES_PATH
            : ($_ENV['PRIVATE_MODULES_PATH'] ?? '');

        foreach ($catalog as &$product) {
            $slug = $product['slug'] ?? '';
            $type = $product['type'] ?? '';
            $product['is_installed'] = false;
            $product['installed_version'] = null;
            $product['has_license'] = false;

            if ($type === 'cms-plugin' && $privatPluginsPath) {
                foreach (['tenant-shared', 'superadmin'] as $subdir) {
                    $pluginJson = $privatPluginsPath . '/' . $subdir . '/' . $slug . '/plugin.json';
                    if (file_exists($pluginJson)) {
                        $product['is_installed'] = true;
                        $meta = json_decode(file_get_contents($pluginJson), true);
                        $product['installed_version'] = $meta['version'] ?? null;
                        break;
                    }
                }
            } elseif ($type === 'cms-module' && $privateModulesPath) {
                $moduleJson = $privateModulesPath . '/' . $slug . '/module.json';
                if (file_exists($moduleJson)) {
                    $product['is_installed'] = true;
                    $meta = json_decode(file_get_contents($moduleJson), true);
                    $product['installed_version'] = $meta['version'] ?? null;
                }
            }

            $license = LicenseClient::findLicenseByProduct($slug);
            if ($license) {
                $product['has_license'] = true;
                $product['license_expires'] = $license['expires'] ?? null;
            }

            $product['update_available'] = false;
            if ($product['is_installed'] && $product['installed_version'] && ($product['current_version'] ?? null)) {
                $product['update_available'] = version_compare($product['current_version'], $product['installed_version'], '>');
            }
        }
        unset($product);

        return View::renderTenant('plugin-store.index', [
            'title'   => 'Plugin Store',
            'catalog' => array_values($catalog),
        ]);
    }

    public function verify()
    {
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

        header('Content-Type: application/json');

        $key = trim($_POST['key'] ?? '');
        $productSlug = trim($_POST['product_slug'] ?? '');

        if (empty($key) || empty($productSlug)) {
            echo json_encode(['success' => false, 'error' => 'Introduce la clave de licencia y selecciona un producto.']);
            return;
        }

        $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $domain = preg_replace('#^https?://#', '', $domain);

        LicenseClient::activateLicense($key, $domain);
        $result = LicenseClient::verifyLicense($key, $domain, $productSlug);

        if (!$result || !($result['valid'] ?? false)) {
            echo json_encode([
                'success' => false,
                'error'   => $result['error'] ?? 'Licencia no valida para este producto y dominio.',
            ]);
            return;
        }

        LicenseClient::saveLicense($key, [
            'product_slug' => $productSlug,
            'product_type' => $result['product']['type'] ?? 'cms-plugin',
            'domain'       => $domain,
            'expires'      => $result['expires'] ?? null,
            'version'      => $result['version'] ?? null,
            'plan'         => $result['plan'] ?? 'yearly',
        ]);

        echo json_encode([
            'success' => true,
            'product' => $result['product'] ?? null,
            'expires' => $result['expires'] ?? null,
        ]);
    }

    public function install()
    {
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

        header('Content-Type: application/json');

        $productSlug = trim($_POST['product_slug'] ?? '');

        if (empty($productSlug)) {
            echo json_encode(['success' => false, 'error' => 'Producto no especificado.']);
            return;
        }

        $license = LicenseClient::findLicenseByProduct($productSlug);
        if (!$license) {
            echo json_encode(['success' => false, 'error' => 'No hay licencia verificada para este producto.']);
            return;
        }

        $key = $license['key'];
        $productType = $license['product_type'] ?? 'cms-plugin';

        $tmpFile = LicenseClient::downloadProduct($productSlug, $key);
        if (!$tmpFile || !file_exists($tmpFile)) {
            echo json_encode(['success' => false, 'error' => 'Error al descargar el producto.']);
            return;
        }

        $privatPluginsPath = defined('PRIVATE_PLUGINS_PATH') ? PRIVATE_PLUGINS_PATH
            : ($_ENV['PRIVATE_PLUGINS_PATH'] ?? '');
        $privateModulesPath = defined('PRIVATE_MODULES_PATH') ? PRIVATE_MODULES_PATH
            : ($_ENV['PRIVATE_MODULES_PATH'] ?? '');

        if ($productType === 'cms-module') {
            $targetDir = $privateModulesPath . '/' . $productSlug;
        } else {
            $targetDir = $privatPluginsPath . '/tenant-shared/' . $productSlug;
        }

        if (empty($targetDir)) {
            @unlink($tmpFile);
            echo json_encode(['success' => false, 'error' => 'Ruta de destino no configurada.']);
            return;
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmpFile) !== true) {
            @unlink($tmpFile);
            echo json_encode(['success' => false, 'error' => 'Archivo descargado no es un ZIP valido.']);
            return;
        }

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $zip->extractTo($targetDir);
        $zip->close();
        @unlink($tmpFile);

        $installFile = $targetDir . '/install.php';
        if (file_exists($installFile)) {
            try {
                require_once $installFile;
            } catch (\Throwable $e) {
                error_log("Plugin Store install error for {$productSlug}: " . $e->getMessage());
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "'{$productSlug}' instalado correctamente.",
        ]);
    }
}
