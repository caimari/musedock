<?php
namespace AIWriter; // Correcto

// Dependencias del Core (asegúrate que los namespaces son correctos)
use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;
// Importar helpers si no son globales
// use function Screenart\Musedock\Helpers\tenant_id;
// use function Screenart\Musedock\Helpers\admin_url;
// use function Screenart\Musedock\Helpers\flash;

class AdminController
{
    private $isSuperAdmin = false;
    private $tenantId = null; // Almacenar tenantId

    public function __construct()
    {
        $this->isSuperAdmin = isset($_SESSION['super_admin']);
        // Obtener tenantId una vez si es posible y está disponible globalmente
        $this->tenantId = function_exists('tenant_id') ? tenant_id() : null;
    }

    /**
     * Muestra la página de configuración
     */
    public function settings()
    {
        // Datos por defecto
        $defaultSettings = [
            'ai_default_provider' => '',
            'ai_daily_token_limit' => '0',
            'ai_log_all_prompts' => '1'
        ];
        $settings = $defaultSettings;
        $providers = [];

        try {
            // Construir consulta base para settings
            $sqlSettings = "SELECT setting_key, setting_value FROM ai_settings WHERE ";
            $paramsSettings = [];

            if ($this->tenantId !== null) {
                $sqlSettings .= "tenant_id = :tenant_id";
                $paramsSettings['tenant_id'] = $this->tenantId;
            } else {
                // Superadmin solo ve configuraciones globales
                $sqlSettings .= "tenant_id IS NULL";
            }

            $resultSettings = Database::query($sqlSettings, $paramsSettings)->fetchAll();
            foreach ($resultSettings as $row) {
                if (array_key_exists($row['setting_key'], $settings)) { // Solo sobrescribir claves conocidas
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
            }

            // Obtener proveedores disponibles (solo para seleccionar el default)
            $sqlProviders = "SELECT id, name FROM ai_providers WHERE active = 1";
            $paramsProviders = [];
            if ($this->tenantId !== null) {
                // Tenant ve los suyos y los globales activos
                $sqlProviders .= " AND (tenant_id = :tenant_id OR (tenant_id IS NULL AND system_wide = 1))";
                $paramsProviders['tenant_id'] = $this->tenantId;
            } else {
                // Superadmin solo ve los globales activos
                $sqlProviders .= " AND tenant_id IS NULL";
            }
            $sqlProviders .= " ORDER BY name ASC";

            $providers = Database::query($sqlProviders, $paramsProviders)->fetchAll();

        } catch (\Throwable $e) { // Capturar Throwable
            Logger::error("Error en AIWriter settings: " . $e->getMessage(), ['exception' => $e]);
            // Usar flash de error si está disponible
            if (function_exists('flash')) {
                 flash('error', 'Error al cargar la configuración: ' . $e->getMessage());
            }
        }

        // Seleccionar vista adecuada
        $viewName = $this->isSuperAdmin ? 'aiwriter.settings' : 'aiwriter.admin.settings';
        $renderMethod = $this->isSuperAdmin ? 'renderSuperadmin' : 'renderTenantAdmin';

        // Asegurarse que el método de renderizado existe
        if (method_exists(View::class, $renderMethod)) {
            return View::$renderMethod($viewName, [ // Llamada estática si son métodos estáticos
                'title' => 'Configuración de AI Writer',
                'settings' => $settings,
                'providers' => $providers,
                 // Pasar tenantId a la vista puede ser útil
                'tenant_id' => $this->tenantId
            ]);
        } else {
            Logger::error("Método de renderizado no encontrado: View::{$renderMethod}");
            // Mostrar un error genérico o redirigir
            return "Error: Vista no disponible.";
        }
    }

    /**
     * Actualiza la configuración
     */
    public function updateSettings()
    {
        // Validar CSRF token si tu framework lo usa
        // if (!validate_csrf($_POST['_token'] ?? '')) { ... }

        try {
            // Validar y sanitizar entradas
            $defaultProvider = filter_input(INPUT_POST, 'default_provider', FILTER_SANITIZE_NUMBER_INT);
            $dailyLimit = filter_input(INPUT_POST, 'daily_token_limit', FILTER_SANITIZE_NUMBER_INT);
            $logPrompts = isset($_POST['log_all_prompts']) ? '1' : '0'; // Booleano como string '1' o '0'

            $settingsToUpdate = [
                'ai_default_provider' => $defaultProvider ?: null, // Guardar NULL si está vacío
                'ai_daily_token_limit' => $dailyLimit ?: '0',
                'ai_log_all_prompts' => $logPrompts
            ];

            Database::beginTransaction(); // Iniciar transacción

            foreach ($settingsToUpdate as $key => $value) {
                // Usar UPSERT o lógica de INSERT/UPDATE
                $sql = "
                    INSERT INTO ai_settings (setting_key, setting_value, tenant_id, created_at, updated_at)
                    VALUES (:key, :value, :tenant_id, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value),
                    updated_at = NOW()
                ";
                // Nota: ON DUPLICATE KEY UPDATE requiere una clave única (UNIQUE o PRIMARY) en (setting_key, tenant_id)
                // Asegúrate que tu tabla `ai_settings` tenga esa clave única.
                // El `tenant_id` en la consulta debe ser NULL para superadmin
                $params = [
                    'key' => $key,
                    'value' => $value,
                    'tenant_id' => $this->tenantId // Será NULL si $this->tenantId es null
                ];
                Database::query($sql, $params);
            }

            Database::commit(); // Confirmar transacción

            if (function_exists('flash')) {
                flash('success', 'Configuración actualizada correctamente');
            }

        } catch (\Throwable $e) { // Capturar Throwable
             Database::rollBack(); // Revertir en caso de error
             Logger::error("Error al actualizar la configuración AI Writer: " . $e->getMessage(), ['exception' => $e]);
             if (function_exists('flash')) {
                flash('error', 'Error al actualizar la configuración: ' . $e->getMessage());
             }
        }

        // Redireccionar
        $adminBaseUrl = $this->isSuperAdmin ? '/musedock' : (function_exists('admin_url') ? admin_url() : '/admin');
        $redirectUrl = rtrim($adminBaseUrl, '/') . '/aiwriter/settings';

        header('Location: ' . $redirectUrl);
        exit;
    }
}