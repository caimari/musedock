<?php

namespace Screenart\Musedock\Controllers\Superadmin;

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
        // Only show CMS products (not panel)
        $catalog = array_values(array_filter($catalog, fn($p) => ($p['target'] ?? 'cms') === 'cms'));
        $storedLicenses = LicenseClient::getStoredLicenses();

        // Check which products are installed locally
        $installed = [];
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
            $product['license_expires'] = null;

            // Check if installed on disk
            if ($type === 'cms-plugin' && $privatPluginsPath) {
                $pluginJson = $privatPluginsPath . '/superadmin/' . $slug . '/plugin.json';
                if (file_exists($pluginJson)) {
                    $product['is_installed'] = true;
                    $meta = json_decode(file_get_contents($pluginJson), true);
                    $product['installed_version'] = $meta['version'] ?? null;
                }
                // Also check tenant-shared
                $pluginJsonShared = $privatPluginsPath . '/tenant-shared/' . $slug . '/plugin.json';
                if (!$product['is_installed'] && file_exists($pluginJsonShared)) {
                    $product['is_installed'] = true;
                    $meta = json_decode(file_get_contents($pluginJsonShared), true);
                    $product['installed_version'] = $meta['version'] ?? null;
                }
            } elseif ($type === 'cms-module' && $privateModulesPath) {
                $moduleJson = $privateModulesPath . '/' . $slug . '/module.json';
                if (file_exists($moduleJson)) {
                    $product['is_installed'] = true;
                    $meta = json_decode(file_get_contents($moduleJson), true);
                    $product['installed_version'] = $meta['version'] ?? null;
                }
            }

            // Check license
            $license = LicenseClient::findLicenseByProduct($slug);
            if ($license) {
                $product['has_license'] = true;
                $product['license_expires'] = $license['expires'] ?? null;
            }

            // Check if update available
            $product['update_available'] = false;
            if ($product['is_installed'] && $product['installed_version'] && ($product['current_version'] ?? null)) {
                $product['update_available'] = version_compare($product['current_version'], $product['installed_version'], '>');
            }
        }
        unset($product);

        return View::renderSuperadmin('plugin-store.index', [
            'title'   => 'Plugin Store',
            'catalog' => $catalog,
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

        $domain = $_SERVER['HTTP_HOST'] ?? ($_ENV['APP_URL'] ?? 'localhost');
        $domain = preg_replace('#^https?://#', '', $domain);

        // First try to activate domain if needed
        LicenseClient::activateLicense($key, $domain);

        // Then verify
        $result = LicenseClient::verifyLicense($key, $domain, $productSlug);

        if (!$result || !($result['valid'] ?? false)) {
            echo json_encode([
                'success' => false,
                'error'   => $result['error'] ?? 'Licencia no valida para este producto y dominio.',
            ]);
            return;
        }

        // Save to local storage
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
            'version' => $result['version'] ?? null,
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

        // Find stored license for this product
        $license = LicenseClient::findLicenseByProduct($productSlug);
        if (!$license) {
            echo json_encode(['success' => false, 'error' => 'No hay licencia verificada para este producto. Verifica primero.']);
            return;
        }

        $key = $license['key'];
        $productType = $license['product_type'] ?? 'cms-plugin';

        // Download ZIP
        $tmpFile = LicenseClient::downloadProduct($productSlug, $key);
        if (!$tmpFile || !file_exists($tmpFile)) {
            echo json_encode(['success' => false, 'error' => 'Error al descargar el producto. Comprueba la licencia.']);
            return;
        }

        // Determine target directory
        $privatPluginsPath = defined('PRIVATE_PLUGINS_PATH') ? PRIVATE_PLUGINS_PATH
            : ($_ENV['PRIVATE_PLUGINS_PATH'] ?? '');
        $privateModulesPath = defined('PRIVATE_MODULES_PATH') ? PRIVATE_MODULES_PATH
            : ($_ENV['PRIVATE_MODULES_PATH'] ?? '');

        if ($productType === 'cms-module') {
            $targetDir = $privateModulesPath . '/' . $productSlug;
        } else {
            // Default: plugin goes to superadmin
            $targetDir = $privatPluginsPath . '/superadmin/' . $productSlug;
        }

        if (empty($targetDir)) {
            @unlink($tmpFile);
            echo json_encode(['success' => false, 'error' => 'Ruta de destino no configurada. Revisa PRIVATE_PLUGINS_PATH / PRIVATE_MODULES_PATH en .env']);
            return;
        }

        // Extract ZIP
        $zip = new \ZipArchive();
        if ($zip->open($tmpFile) !== true) {
            @unlink($tmpFile);
            echo json_encode(['success' => false, 'error' => 'El archivo descargado no es un ZIP valido.']);
            return;
        }

        // Create target dir if not exists
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $zip->extractTo($targetDir);
        $zip->close();
        @unlink($tmpFile);

        // Run install.php if exists
        $installFile = $targetDir . '/install.php';
        if (file_exists($installFile)) {
            try {
                require_once $installFile;
            } catch (\Throwable $e) {
                // Log but don't fail the install
                error_log("Plugin Store install.php error for {$productSlug}: " . $e->getMessage());
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "Producto '{$productSlug}' instalado correctamente.",
            'path'    => $targetDir,
        ]);
    }

    /**
     * Called by cron to verify all stored premium licenses.
     * Deactivates plugins/modules with expired licenses.
     */
    public static function checkLicenses(): void
    {
        $licenses = LicenseClient::getStoredLicenses();
        $domain = $_ENV['APP_URL'] ?? $_ENV['MAIN_DOMAIN'] ?? 'localhost';
        $domain = preg_replace('#^https?://#', '', $domain);

        foreach ($licenses as $key => $license) {
            $productSlug = $license['product_slug'] ?? '';
            if (empty($productSlug)) continue;

            $result = LicenseClient::verifyLicense($key, $domain, $productSlug);

            if (!$result || !($result['valid'] ?? false)) {
                // License expired or invalid — log it
                error_log("License expired for product '{$productSlug}' (key: {$key})");

                // Update local record
                $license['expired'] = true;
                $license['verified_at'] = date('c');
                LicenseClient::saveLicense($key, $license);
            } else {
                // Update verification timestamp and version
                $license['verified_at'] = date('c');
                $license['expires'] = $result['expires'] ?? $license['expires'];
                $license['version'] = $result['version'] ?? $license['version'];
                $license['expired'] = false;
                LicenseClient::saveLicense($key, $license);
            }
        }
    }
}
