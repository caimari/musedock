<?php

namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\Database;
use Screenart\Musedock\View;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\Services\AI\Models\TenantProvider;
use Screenart\Musedock\Services\AI\Models\Usage;
use Screenart\Musedock\Services\AI\BlogAutoTagger;

class AISettingsController
{
    use RequiresPermission;

    /**
     * Mostrar panel de configuración de IA del tenant
     */
    public function settings()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.view');

        $tenantId = tenant_id();
        if (!$tenantId) {
            $_SESSION['error'] = 'No se ha detectado el tenant actual';
            header('Location: /' . admin_path());
            exit;
        }

        // Obtener proveedor propio del tenant (si tiene)
        $tenantProvider = TenantProvider::getForTenant($tenantId);

        // Obtener cuota del sistema para este tenant
        $quota = Usage::hasTenantExceededDailyLimit($tenantId);

        // Obtener uso de hoy (usar CURRENT_DATE de DB para evitar desfase de timezone PHP vs PostgreSQL)
        $todayTokens = (int) Database::query("
            SELECT COALESCE(SUM(tokens_used), 0)
            FROM ai_usage_logs
            WHERE tenant_id = :tid AND status = 'success'
            AND created_at >= CURRENT_DATE
            AND created_at < CURRENT_DATE + INTERVAL '1 day'
        ", ['tid' => $tenantId])->fetchColumn();

        $todayRequests = (int) Database::query("
            SELECT COUNT(*)
            FROM ai_usage_logs
            WHERE tenant_id = :tid AND status = 'success'
            AND created_at >= CURRENT_DATE
            AND created_at < CURRENT_DATE + INTERVAL '1 day'
        ", ['tid' => $tenantId])->fetchColumn();

        return View::renderTenantAdmin('ai/settings', [
            'title' => 'Configuración de IA',
            'tenantProvider' => $tenantProvider,
            'quota' => $quota,
            'todayTokens' => $todayTokens,
            'todayRequests' => $todayRequests,
        ]);
    }

    /**
     * Guardar o actualizar API key propia del tenant
     */
    public function update()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        $tenantId = tenant_id();
        if (!$tenantId) {
            $_SESSION['error'] = 'No se ha detectado el tenant actual';
            header('Location: /' . admin_path());
            exit;
        }

        $providerType = $_POST['provider_type'] ?? 'openai';
        $apiKey = trim($_POST['api_key'] ?? '');

        // Ollama no requiere API key obligatoria
        if (empty($apiKey) && $providerType !== 'ollama') {
            $_SESSION['flash_error'] = 'La API Key no puede estar vacía.';
            header('Location: /' . admin_path() . '/ai/settings');
            exit;
        }
        if (empty($apiKey) && $providerType === 'ollama') {
            $apiKey = 'none';
        }

        try {
            TenantProvider::save($tenantId, [
                'provider_type' => $_POST['provider_type'] ?? 'openai',
                'api_key' => $apiKey,
                'model' => $_POST['model'] ?? 'gpt-4',
                'max_tokens' => (int) ($_POST['max_tokens'] ?? 1500),
                'temperature' => (float) ($_POST['temperature'] ?? 0.7),
                'endpoint' => trim($_POST['endpoint'] ?? ''),
            ]);

            $_SESSION['flash_success'] = 'API Key propia configurada correctamente. Tus llamadas a IA ya no consumirán la cuota del sistema.';
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Error al guardar: ' . $e->getMessage();
        }

        header('Location: /' . admin_path() . '/ai/settings');
        exit;
    }

    /**
     * Eliminar API key propia (volver a usar la del sistema)
     */
    public function deleteKey()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        $tenantId = tenant_id();
        if (!$tenantId) {
            $_SESSION['error'] = 'No se ha detectado el tenant actual';
            header('Location: /' . admin_path());
            exit;
        }

        try {
            TenantProvider::delete($tenantId);
            $_SESSION['flash_success'] = 'API Key propia eliminada. Las llamadas a IA volverán a usar el proveedor del sistema (con cuota).';
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = 'Error al eliminar: ' . $e->getMessage();
        }

        header('Location: /' . admin_path() . '/ai/settings');
        exit;
    }

    /**
     * Mostrar panel de auto-categorización y etiquetado
     */
    public function autoTagger()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.view');

        $tenantId = tenant_id();
        if (!$tenantId) {
            $_SESSION['error'] = 'No se ha detectado el tenant actual';
            header('Location: /' . admin_path());
            exit;
        }

        // Obtener estadísticas de posts
        $totalPosts = (int) Database::query(
            "SELECT COUNT(*) FROM blog_posts WHERE tenant_id = :tid AND status = 'published'",
            ['tid' => $tenantId]
        )->fetchColumn();

        $totalCategories = (int) Database::query(
            "SELECT COUNT(*) FROM blog_categories WHERE tenant_id = :tid",
            ['tid' => $tenantId]
        )->fetchColumn();

        $totalTags = (int) Database::query(
            "SELECT COUNT(*) FROM blog_tags WHERE tenant_id = :tid",
            ['tid' => $tenantId]
        )->fetchColumn();

        return View::renderTenantAdmin('ai/auto-tagger', [
            'title' => 'Auto-Categorizar y Etiquetar',
            'totalPosts' => $totalPosts,
            'totalCategories' => $totalCategories,
            'totalTags' => $totalTags,
        ]);
    }

    /**
     * Ejecutar auto-tagging vía AJAX
     */
    public function runAutoTagger()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        header('Content-Type: application/json');

        $tenantId = tenant_id();
        if (!$tenantId) {
            echo json_encode(['success' => false, 'message' => 'Tenant no detectado.']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $csrfToken = $input['_csrf'] ?? '';
        if (!validate_csrf($csrfToken)) {
            echo json_encode(['success' => false, 'message' => 'Token de seguridad invalido.']);
            exit;
        }

        $dryRun = !empty($input['dry_run']);
        $scope = $input['scope'] ?? 'all';
        $postIds = [];

        // Si se envían sugerencias preseleccionadas, aplicarlas directamente
        if (!empty($input['suggestions']) && is_array($input['suggestions'])) {
            try {
                $result = BlogAutoTagger::applyFiltered($tenantId, $input['suggestions']);
                echo json_encode($result);
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
        }

        if ($scope === 'untagged') {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
                SELECT bp.id FROM blog_posts bp
                LEFT JOIN (SELECT post_id, COUNT(*) as cnt FROM blog_post_categories GROUP BY post_id) pc ON bp.id = pc.post_id
                LEFT JOIN (SELECT post_id, COUNT(*) as cnt FROM blog_post_tags GROUP BY post_id) pt ON bp.id = pt.post_id
                WHERE bp.tenant_id = ? AND bp.status = 'published'
                AND (COALESCE(pc.cnt, 0) + COALESCE(pt.cnt, 0)) < 4
                ORDER BY bp.id
            ");
            $stmt->execute([$tenantId]);
            $postIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            if (empty($postIds)) {
                echo json_encode(['success' => true, 'dry_run' => $dryRun, 'suggestions' => [], 'message' => 'Todos los posts ya tienen al menos 4 categorias/tags.']);
                exit;
            }
        }

        try {
            $result = BlogAutoTagger::analyze($tenantId, $postIds, $dryRun);
            echo json_encode($result);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }
}
