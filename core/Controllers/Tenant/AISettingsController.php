<?php

namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\Database;
use Screenart\Musedock\View;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\Services\AI\Models\TenantProvider;
use Screenart\Musedock\Services\AI\Models\Usage;

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

        // Obtener uso de hoy
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');
        $todayTokens = (int) Database::query("
            SELECT COALESCE(SUM(tokens_used), 0)
            FROM ai_usage_logs
            WHERE tenant_id = :tid AND status = 'success'
            AND created_at BETWEEN :from AND :to
        ", ['tid' => $tenantId, 'from' => $todayStart, 'to' => $todayEnd])->fetchColumn();

        $todayRequests = (int) Database::query("
            SELECT COUNT(*)
            FROM ai_usage_logs
            WHERE tenant_id = :tid AND status = 'success'
            AND created_at BETWEEN :from AND :to
        ", ['tid' => $tenantId, 'from' => $todayStart, 'to' => $todayEnd])->fetchColumn();

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
}
