<?php

namespace CrossPublisher\Controllers;

use Screenart\Musedock\View;
use Screenart\Musedock\Services\TenantManager;
use Screenart\Musedock\Database;
use CrossPublisher\Models\Network;

/**
 * Controlador de la Red del Cross-Publisher
 */
class NetworkController
{
    /**
     * Mostrar configuración de red
     */
    public function index()
    {
        $tenantId = TenantManager::currentTenantId();

        $networkKey = Network::getNetworkKey($tenantId);
        $networkTenants = $networkKey ? Network::getNetworkTenants($networkKey) : [];
        $config = Network::getConfig($tenantId);

        return View::renderTenantAdmin('plugins.cross-publisher.network.index', [
            'networkKey' => $networkKey,
            'networkTenants' => $networkTenants,
            'config' => $config
        ]);
    }

    /**
     * Registrar tenant en una red
     */
    public function register()
    {
        $tenantId = TenantManager::currentTenantId();
        $lang = $this->loadLang();

        $networkKey = trim($_POST['network_key'] ?? '');

        if (empty($networkKey)) {
            $_SESSION['flash_error'] = $lang['error_network_key_required'] ?? 'La clave de red es requerida';
            header('Location: /admin/plugins/cross-publisher/network');
            exit;
        }

        // Validar formato de network_key (solo letras, números, guiones)
        if (!preg_match('/^[a-z0-9-]+$/', $networkKey)) {
            $_SESSION['flash_error'] = $lang['error_network_key_invalid'] ?? 'Formato de clave inválido';
            header('Location: /admin/plugins/cross-publisher/network');
            exit;
        }

        Network::register($tenantId, $networkKey, [
            'default_language' => $_POST['default_language'] ?? 'es',
            'can_publish' => isset($_POST['can_publish']),
            'can_receive' => isset($_POST['can_receive'])
        ]);

        $_SESSION['flash_success'] = $lang['network_registered'] ?? 'Registrado en la red correctamente';
        header('Location: /admin/plugins/cross-publisher/network');
        exit;
    }

    /**
     * Actualizar configuración de red
     */
    public function update()
    {
        $tenantId = TenantManager::currentTenantId();
        $lang = $this->loadLang();

        Network::updateConfig($tenantId, [
            'default_language' => $_POST['default_language'] ?? 'es',
            'can_publish' => isset($_POST['can_publish']),
            'can_receive' => isset($_POST['can_receive']),
            'is_active' => isset($_POST['is_active'])
        ]);

        $_SESSION['flash_success'] = $lang['network_updated'] ?? 'Configuración actualizada';
        header('Location: /admin/plugins/cross-publisher/network');
        exit;
    }

    /**
     * Cargar traducciones
     */
    private function loadLang(): array
    {
        $locale = function_exists('detectLanguage') ? detectLanguage() : 'es';
        $langFile = __DIR__ . '/../lang/' . $locale . '.php';

        if (!file_exists($langFile)) {
            $langFile = __DIR__ . '/../lang/es.php';
        }

        return require $langFile;
    }
}
