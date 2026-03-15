<?php

namespace NewsAggregatorAdmin\Controllers;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use NewsAggregator\Models\Settings;

class SettingsController
{
    private function requireTenantId(): int
    {
        $tenantId = (int) ($_GET['tenant'] ?? $_POST['tenant_id'] ?? 0);
        if (!$tenantId) {
            flash('error', 'Selecciona un tenant primero.');
            header('Location: /musedock/news-aggregator');
            exit;
        }
        return $tenantId;
    }

    private function getTenantsWithPlugin(): array
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query("
                SELECT t.id, t.domain, t.name
                FROM tenants t
                INNER JOIN tenant_plugins tp ON t.id = tp.tenant_id
                WHERE tp.slug = 'news-aggregator' AND tp.active = 1
                ORDER BY t.domain
            ");
            return $stmt->fetchAll(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function index()
    {
        $tenantId = $this->requireTenantId();
        $settings = Settings::getWithDefaults($tenantId);

        $aiProviders = $this->getAIProviders($tenantId);
        $categories = $this->getBlogCategories($tenantId);

        return View::renderSuperadmin('plugins.news-aggregator.settings', [
            'settings' => $settings,
            'aiProviders' => $aiProviders,
            'categories' => $categories,
            'tenantId' => $tenantId,
            'tenants' => $this->getTenantsWithPlugin(),
        ]);
    }

    public function save()
    {
        $tenantId = $this->requireTenantId();
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
            flash('success', $lang['settings_saved'] ?? 'Configuración guardada.');
        } catch (\Exception $e) {
            flash('error', ($lang['settings_error'] ?? 'Error') . ': ' . $e->getMessage());
        }

        header("Location: /musedock/news-aggregator/settings?tenant={$tenantId}");
        exit;
    }

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
            return [];
        }
    }

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
