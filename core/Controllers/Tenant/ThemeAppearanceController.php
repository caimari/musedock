<?php

namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\View;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\Database;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Logger;
use Screenart\Musedock\Models\ThemeOption;
use PDO;

/**
 * TenantThemeAppearanceController
 *
 * Permite a los admins de tenant personalizar la apariencia de sus temas
 * de forma aislada (cada tenant tiene sus propios settings).
 */
class ThemeAppearanceController
{
    use RequiresPermission;

    /**
     * Muestra la página de personalización de apariencia para el tenant actual.
     */
    public function index($slug = null)
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        $tenantId = tenant_id();

        // Si no se especifica slug, usar el tema activo del tenant
        if (!$slug) {
            $tenant = Database::query(
                "SELECT theme_type, custom_theme_slug, theme FROM tenants WHERE id = :id",
                ['id' => $tenantId]
            )->fetch(PDO::FETCH_ASSOC);

            $slug = $tenant['theme_type'] === 'custom' && $tenant['custom_theme_slug']
                ? $tenant['custom_theme_slug']
                : ($tenant['theme'] ?? 'default');
        }

        // Determinar ruta del tema
        $themePath = $this->getThemePath($slug, $tenantId);

        if (!$themePath) {
            flash('error', 'El tema no existe o no se pudo encontrar.');
            header('Location: /' . admin_path() . '/themes');
            exit;
        }

        // Cargar theme.json
        $configFile = $themePath . '/theme.json';
        if (!file_exists($configFile)) {
            flash('warning', 'El tema no tiene archivo de configuración (theme.json).');
            header('Location: /' . admin_path() . '/themes');
            exit;
        }

        $config = json_decode(file_get_contents($configFile), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($config)) {
            flash('error', 'Error al leer theme.json.');
            header('Location: /' . admin_path() . '/themes');
            exit;
        }

        // Verificar si el tema es personalizable
        if (!($config['customizable'] ?? false) || empty($config['customizable_options'])) {
            flash('warning', 'Este tema no tiene opciones de personalización disponibles.');
            header('Location: /' . admin_path() . '/themes');
            exit;
        }

        // Cargar opciones guardadas del tenant
        $savedOptions = ThemeOption::getOptions($slug, $tenantId);

        // Cargar presets disponibles
        $presets = $this->getPresets($slug, $tenantId);

        return View::renderTenantAdmin('themes.appearance', [
            'title' => 'Personalizar: ' . ($config['name'] ?? $slug),
            'theme' => $config,
            'slug' => $slug,
            'tenantId' => $tenantId,
            'optionsSchema' => $config['customizable_options'],
            'savedOptions' => $savedOptions,
            'presets' => $presets,
            'hasCustomOptions' => !empty($savedOptions)
        ]);
    }

    /**
     * Guarda las opciones de apariencia del tenant.
     */
    public function save($slug)
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            flash('error', 'Token de seguridad inválido.');
            header('Location: /' . admin_path() . '/themes/appearance/' . $slug);
            exit;
        }

        $tenantId = tenant_id();

        // Recopilar opciones del formulario
        $submittedOptions = [];
        if (isset($_POST['options']) && is_array($_POST['options'])) {
            $submittedOptions = $_POST['options'];
        }

        if (empty($submittedOptions)) {
            flash('error', 'No se recibieron datos para guardar.');
            header('Location: /' . admin_path() . '/themes/appearance/' . $slug);
            exit;
        }

        // Guardar opciones
        $success = ThemeOption::saveOptions($slug, $tenantId, $submittedOptions);

        if ($success) {
            try {
                // Generar CSS personalizado
                $this->generateCustomCSS($slug, $submittedOptions, $tenantId);

                // Generar JS personalizado
                $this->generateCustomJS($slug, $submittedOptions, $tenantId);

                flash('success', 'Personalización guardada correctamente.');
            } catch (\Exception $e) {
                Logger::log("Error generando archivos para tenant {$tenantId}: " . $e->getMessage(), 'ERROR');
                flash('warning', 'Opciones guardadas, pero hubo un error al generar archivos: ' . $e->getMessage());
            }
        } else {
            flash('error', 'Error al guardar las opciones.');
        }

        header('Location: /' . admin_path() . '/themes/appearance/' . $slug);
        exit;
    }

    /**
     * Restaura las opciones a los valores por defecto.
     */
    public function reset($slug)
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            flash('error', 'Token de seguridad inválido.');
            header('Location: /' . admin_path() . '/themes/appearance/' . $slug);
            exit;
        }

        $tenantId = tenant_id();

        // Eliminar opciones de la BD
        $deleted = ThemeOption::deleteOptions($slug, $tenantId);

        if ($deleted) {
            // Eliminar archivos CSS/JS personalizados
            $this->deleteCustomFiles($slug, $tenantId);
            flash('success', 'Opciones restauradas a los valores por defecto.');
        } else {
            flash('info', 'No había opciones personalizadas para restaurar.');
        }

        header('Location: /' . admin_path() . '/themes/appearance/' . $slug);
        exit;
    }

    // ==================== PRESETS ====================

    /**
     * Guarda las opciones actuales como un preset.
     */
    public function savePreset($slug)
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            $this->jsonResponse(['success' => false, 'error' => 'Token inválido']);
        }

        $tenantId = tenant_id();
        $presetName = trim($_POST['preset_name'] ?? '');

        if (empty($presetName)) {
            $this->jsonResponse(['success' => false, 'error' => 'El nombre del preset es requerido']);
        }

        // Obtener opciones actuales del tenant
        $currentOptions = ThemeOption::getOptions($slug, $tenantId);

        // Si el tenant no tiene opciones propias, usar las globales como base
        if (empty($currentOptions) && $tenantId !== null) {
            $currentOptions = ThemeOption::getOptions($slug, null);
        }

        if (empty($currentOptions)) {
            $this->jsonResponse(['success' => false, 'error' => 'No hay opciones guardadas para crear un preset. Guarda al menos una personalización primero.']);
        }

        // Generar slug único
        $presetSlug = $this->generatePresetSlug($presetName);

        // Guardar preset
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            INSERT INTO theme_presets (tenant_id, theme_slug, preset_slug, preset_name, options, created_at, updated_at)
            VALUES (:tenant_id, :theme_slug, :preset_slug, :preset_name, :options, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                preset_name = :preset_name2,
                options = :options2,
                updated_at = NOW()
        ");

        $optionsJson = json_encode($currentOptions, JSON_UNESCAPED_UNICODE);

        $success = $stmt->execute([
            'tenant_id' => $tenantId,
            'theme_slug' => $slug,
            'preset_slug' => $presetSlug,
            'preset_name' => $presetName,
            'options' => $optionsJson,
            'preset_name2' => $presetName,
            'options2' => $optionsJson
        ]);

        if ($success) {
            $this->jsonResponse([
                'success' => true,
                'message' => "Preset '{$presetName}' guardado correctamente",
                'preset' => [
                    'slug' => $presetSlug,
                    'name' => $presetName
                ]
            ]);
        } else {
            $this->jsonResponse(['success' => false, 'error' => 'Error al guardar el preset']);
        }
    }

    /**
     * Carga un preset existente.
     */
    public function loadPreset($slug, $presetSlug)
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            flash('error', 'Token de seguridad inválido.');
            header('Location: /' . admin_path() . '/themes/appearance/' . $slug);
            exit;
        }

        $tenantId = tenant_id();

        // Obtener preset
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT options FROM theme_presets
            WHERE tenant_id = :tenant_id AND theme_slug = :theme_slug AND preset_slug = :preset_slug
        ");
        $stmt->execute([
            'tenant_id' => $tenantId,
            'theme_slug' => $slug,
            'preset_slug' => $presetSlug
        ]);
        $preset = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$preset) {
            flash('error', 'Preset no encontrado.');
            header('Location: /' . admin_path() . '/themes/appearance/' . $slug);
            exit;
        }

        $options = json_decode($preset['options'], true);

        // Aplicar opciones del preset
        $success = ThemeOption::saveOptions($slug, $tenantId, $options);

        if ($success) {
            try {
                $this->generateCustomCSS($slug, $options, $tenantId);
                $this->generateCustomJS($slug, $options, $tenantId);
                flash('success', 'Preset aplicado correctamente.');
            } catch (\Exception $e) {
                flash('warning', 'Preset aplicado, pero error al generar archivos.');
            }
        } else {
            flash('error', 'Error al aplicar el preset.');
        }

        header('Location: /' . admin_path() . '/themes/appearance/' . $slug);
        exit;
    }

    /**
     * Elimina un preset.
     */
    public function deletePreset($slug, $presetSlug)
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            $this->jsonResponse(['success' => false, 'error' => 'Token inválido']);
        }

        $tenantId = tenant_id();

        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            DELETE FROM theme_presets
            WHERE tenant_id = :tenant_id AND theme_slug = :theme_slug AND preset_slug = :preset_slug
        ");
        $success = $stmt->execute([
            'tenant_id' => $tenantId,
            'theme_slug' => $slug,
            'preset_slug' => $presetSlug
        ]);

        $this->jsonResponse([
            'success' => $success,
            'message' => $success ? 'Preset eliminado' : 'Error al eliminar'
        ]);
    }

    /**
     * Exporta las opciones actuales como JSON.
     */
    public function exportPreset($slug)
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        $tenantId = tenant_id();
        $options = ThemeOption::getOptions($slug, $tenantId);

        if (empty($options)) {
            flash('error', 'No hay opciones para exportar.');
            header('Location: /' . admin_path() . '/themes/appearance/' . $slug);
            exit;
        }

        $exportData = [
            'theme_slug' => $slug,
            'exported_at' => date('Y-m-d H:i:s'),
            'version' => '1.0',
            'options' => $options
        ];

        $filename = "theme-preset-{$slug}-" . date('Y-m-d-His') . ".json";

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen(json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)));

        echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Importa opciones desde un archivo JSON.
     */
    public function importPreset($slug)
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            flash('error', 'Token de seguridad inválido.');
            header('Location: /' . admin_path() . '/themes/appearance/' . $slug);
            exit;
        }

        $tenantId = tenant_id();

        if (!isset($_FILES['preset_file']) || $_FILES['preset_file']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Error al subir el archivo.');
            header('Location: /' . admin_path() . '/themes/appearance/' . $slug);
            exit;
        }

        $content = file_get_contents($_FILES['preset_file']['tmp_name']);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['options'])) {
            flash('error', 'Archivo JSON inválido.');
            header('Location: /' . admin_path() . '/themes/appearance/' . $slug);
            exit;
        }

        // Verificar que el preset es para el mismo tema
        if (isset($data['theme_slug']) && $data['theme_slug'] !== $slug) {
            flash('warning', 'Este preset fue creado para otro tema. Se aplicará de todos modos.');
        }

        // Aplicar opciones importadas
        $success = ThemeOption::saveOptions($slug, $tenantId, $data['options']);

        if ($success) {
            try {
                $this->generateCustomCSS($slug, $data['options'], $tenantId);
                $this->generateCustomJS($slug, $data['options'], $tenantId);
                flash('success', 'Preset importado y aplicado correctamente.');
            } catch (\Exception $e) {
                flash('warning', 'Preset importado, pero error al generar archivos.');
            }
        } else {
            flash('error', 'Error al aplicar el preset importado.');
        }

        header('Location: /' . admin_path() . '/themes/appearance/' . $slug);
        exit;
    }

    // ==================== HELPERS ====================

    /**
     * Obtiene la ruta del tema.
     */
    private function getThemePath(string $slug, ?int $tenantId): ?string
    {
        // Primero intentar tema custom del tenant (si hay tenant)
        if ($tenantId !== null) {
            $customPath = APP_ROOT . "/storage/tenants/{$tenantId}/themes/{$slug}";
            if (is_dir($customPath)) {
                return $customPath;
            }
        }

        // Luego tema global
        $globalPath = APP_ROOT . "/themes/{$slug}";
        if (is_dir($globalPath)) {
            return $globalPath;
        }

        return null;
    }

    /**
     * Obtiene los presets del tenant para un tema.
     */
    private function getPresets(string $slug, int $tenantId): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT preset_slug, preset_name, created_at
            FROM theme_presets
            WHERE tenant_id = :tenant_id AND theme_slug = :theme_slug
            ORDER BY preset_name ASC
        ");
        $stmt->execute([
            'tenant_id' => $tenantId,
            'theme_slug' => $slug
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Genera slug para preset.
     */
    private function generatePresetSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug ?: 'preset-' . uniqid();
    }

    /**
     * Genera CSS personalizado para el tenant (o global si no hay tenant).
     */
    protected function generateCustomCSS(string $slug, array $options, ?int $tenantId): void
    {
        $themePath = $this->getThemePath($slug, $tenantId);
        if (!$themePath) {
            throw new \Exception("Tema no encontrado");
        }

        // Cargar plantilla CSS si existe
        $cssTemplatePath = $themePath . '/css/template.css';
        $cssTemplate = file_exists($cssTemplatePath) ? file_get_contents($cssTemplatePath) : ":root {}\n";

        // Generar variables CSS
        $cssVars = $this->buildCssVariables($options);

        // Construir bloque :root
        $cssVarsBlock = ":root {\n";
        foreach ($cssVars as $var => $value) {
            $cssVarsBlock .= "  {$var}: {$value};\n";
        }
        $cssVarsBlock .= "}\n";

        // Reemplazar o añadir :root
        $finalCss = preg_replace('/:root\s*\{[^}]*\}/s', $cssVarsBlock, $cssTemplate, 1);
        if (strpos($finalCss, ':root {') === false) {
            $finalCss = $cssVarsBlock . "\n" . $cssTemplate;
        }

        // Añadir CSS personalizado del usuario
        $customCSS = $this->getNestedValue($options, ['custom_code', 'custom_css']);
        if (!empty($customCSS)) {
            $finalCss .= "\n\n/* --- CSS Personalizado --- */\n" . $customCSS;
        }

        // Guardar archivo - usar ruta diferente para tenant vs global
        if ($tenantId !== null) {
            $customCssDir = APP_ROOT . "/public/assets/themes/tenant_{$tenantId}/{$slug}/css";
        } else {
            // Modo global (sin tenant) - guardar directamente en el tema
            $customCssDir = APP_ROOT . "/public/assets/themes/{$slug}/css";
        }

        if (!is_dir($customCssDir)) {
            mkdir($customCssDir, 0775, true);
        }

        file_put_contents("{$customCssDir}/custom.css", $finalCss);
        file_put_contents("{$customCssDir}/custom.css.timestamp", time());
    }

    /**
     * Genera JS personalizado para el tenant (o global si no hay tenant).
     */
    protected function generateCustomJS(string $slug, array $options, ?int $tenantId): void
    {
        $customJS = $this->getNestedValue($options, ['custom_code', 'custom_js']);

        if (empty($customJS)) {
            return;
        }

        // Ruta diferente para tenant vs global
        if ($tenantId !== null) {
            $customJsDir = APP_ROOT . "/public/assets/themes/tenant_{$tenantId}/{$slug}/js";
        } else {
            $customJsDir = APP_ROOT . "/public/assets/themes/{$slug}/js";
        }

        if (!is_dir($customJsDir)) {
            mkdir($customJsDir, 0775, true);
        }

        $finalJs = "(function() {\n/* --- JavaScript Personalizado --- */\n" . $customJS . "\n})();";

        file_put_contents("{$customJsDir}/custom.js", $finalJs);
        file_put_contents("{$customJsDir}/custom.js.timestamp", time());
    }

    /**
     * Elimina archivos CSS/JS personalizados.
     */
    protected function deleteCustomFiles(string $slug, ?int $tenantId): void
    {
        // Ruta diferente para tenant vs global
        if ($tenantId !== null) {
            $basePath = APP_ROOT . "/public/assets/themes/tenant_{$tenantId}/{$slug}";
        } else {
            $basePath = APP_ROOT . "/public/assets/themes/{$slug}";
        }

        $files = [
            "{$basePath}/css/custom.css",
            "{$basePath}/css/custom.css.timestamp",
            "{$basePath}/js/custom.js",
            "{$basePath}/js/custom.js.timestamp"
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Construye variables CSS desde las opciones.
     */
    protected function buildCssVariables(array $options): array
    {
        $mappings = [
            'topbar.topbar_bg_color' => '--topbar-bg-color',
            'topbar.topbar_text_color' => '--topbar-text-color',
            'header.header_bg_color' => '--header-bg-color',
            'header.header_cta_bg_color' => '--header-cta-bg-color',
            'header.header_cta_text_color' => '--header-cta-text-color',
            'footer.footer_bg_color' => '--footer-bg-color',
            'footer.footer_text_color' => '--footer-text-color',
            'footer.footer_link_color' => '--footer-link-color',
            'footer.footer_link_hover_color' => '--footer-link-hover-color',
        ];

        $cssVars = [];
        foreach ($mappings as $optionPath => $cssVar) {
            $value = $this->getNestedValue($options, explode('.', $optionPath));
            if ($value !== null && $value !== '') {
                $cssVars[$cssVar] = $value;
            }
        }

        return $cssVars;
    }

    /**
     * Obtiene valor anidado de un array.
     */
    protected function getNestedValue($array, $keys, $default = null)
    {
        if (!is_array($keys)) {
            $keys = explode('.', $keys);
        }
        $current = $array;
        foreach ($keys as $key) {
            if (!is_array($current) || !isset($current[$key])) {
                return $default;
            }
            $current = $current[$key];
        }
        return $current;
    }

    /**
     * Respuesta JSON.
     */
    private function jsonResponse(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
