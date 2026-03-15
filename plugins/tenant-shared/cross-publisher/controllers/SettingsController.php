<?php

namespace CrossPublisher\Controllers;

use Screenart\Musedock\View;
use Screenart\Musedock\Services\TenantManager;
use Screenart\Musedock\Database;
use CrossPublisher\Models\Settings;

/**
 * Controlador de Configuración del Cross-Publisher
 */
class SettingsController
{
    /**
     * Mostrar configuración
     */
    public function index()
    {
        $tenantId = TenantManager::currentTenantId();
        $settings = Settings::getWithDefaults($tenantId);

        // Obtener proveedores de IA disponibles
        $aiProviders = $this->getAIProviders($tenantId);

        return View::renderTenantAdmin('plugins.cross-publisher.settings', [
            'settings' => $settings,
            'aiProviders' => $aiProviders
        ]);
    }

    /**
     * Guardar configuración
     */
    public function save()
    {
        $tenantId = TenantManager::currentTenantId();
        $lang = $this->loadLang();

        $data = [
            'ai_provider_id' => !empty($_POST['ai_provider_id']) ? (int) $_POST['ai_provider_id'] : null,
            'auto_translate' => isset($_POST['auto_translate']),
            'default_status' => $_POST['default_status'] ?? 'draft',
            'include_featured_image' => isset($_POST['include_featured_image']),
            'add_canonical_link' => isset($_POST['add_canonical_link']),
            'add_source_credit' => isset($_POST['add_source_credit']),
            'source_credit_template' => $_POST['source_credit_template'] ?? '',
            'enabled' => isset($_POST['enabled'])
        ];

        try {
            Settings::save($tenantId, $data);
            $_SESSION['flash_success'] = $lang['settings_saved'] ?? 'Configuración guardada';
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = ($lang['settings_error'] ?? 'Error') . ': ' . $e->getMessage();
        }

        header('Location: /admin/plugins/cross-publisher/settings');
        exit;
    }

    /**
     * Obtener proveedores de IA del tenant
     */
    private function getAIProviders(int $tenantId): array
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
                SELECT id, name, provider_type
                FROM ai_providers
                WHERE tenant_id = ? AND active = true
                ORDER BY name
            ");
            $stmt->execute([$tenantId]);
            return $stmt->fetchAll(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            return [];
        }
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
