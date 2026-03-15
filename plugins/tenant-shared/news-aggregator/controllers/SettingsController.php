<?php

namespace NewsAggregator\Controllers;

use Screenart\Musedock\Services\TenantManager;
use Screenart\Musedock\Database;
use Screenart\Musedock\View;
use NewsAggregator\Models\Settings;

/**
 * Controlador de Configuración del News Aggregator
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

        // Obtener categorías del blog
        $categories = $this->getBlogCategories($tenantId);

        return View::renderTenantAdmin('plugins.news-aggregator.settings', [
            'settings' => $settings,
            'aiProviders' => $aiProviders,
            'categories' => $categories
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
            'output_language' => $_POST['output_language'] ?? 'es',
            'rewrite_prompt' => $_POST['rewrite_prompt'] ?? null,
            'default_category_id' => !empty($_POST['default_category_id']) ? (int) $_POST['default_category_id'] : null,
            'auto_rewrite' => isset($_POST['auto_rewrite']),
            'auto_approve' => isset($_POST['auto_approve']),
            'auto_publish' => isset($_POST['auto_publish']),
            'auto_generate_tags' => isset($_POST['auto_generate_tags']),
            'publish_status' => $_POST['publish_status'] ?? 'draft',
            'duplicate_check_days' => (int) ($_POST['duplicate_check_days'] ?? 7),
            'cleanup_unverified_hours' => (int) ($_POST['cleanup_unverified_hours'] ?? 6),
            'enabled' => isset($_POST['enabled']),
            'currentsapi_key' => trim($_POST['currentsapi_key'] ?? ''),
            'newsapi_key' => trim($_POST['newsapi_key'] ?? ''),
            'gnews_key' => trim($_POST['gnews_key'] ?? ''),
            'thenewsapi_key' => trim($_POST['thenewsapi_key'] ?? ''),
            'mediastack_key' => trim($_POST['mediastack_key'] ?? ''),
        ];

        try {
            Settings::save($tenantId, $data);
            $_SESSION['success'] = $lang['settings_saved'];
        } catch (\Exception $e) {
            $_SESSION['error'] = $lang['settings_error'] . ': ' . $e->getMessage();
        }

        header('Location: /admin/plugins/news-aggregator/settings');
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
                SELECT id, name, provider_type AS provider
                FROM ai_providers
                WHERE active = 1
                  AND (tenant_id = ? OR (tenant_id IS NULL AND system_wide = 1))
                ORDER BY name
            ");
            $stmt->execute([$tenantId]);
            return $stmt->fetchAll(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            error_log("[NewsAggregator] getAIProviders error for tenant $tenantId: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener categorías del blog
     */
    private function getBlogCategories(int $tenantId): array
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
                SELECT id, name
                FROM blog_categories
                WHERE tenant_id = ?
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
