<?php

namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Widgets\WidgetManager;
use Screenart\Musedock\Traits\RequiresPermission;

class ThemeWidgetController
{
    use RequiresPermission;

    /**
     * Muestra la página de gestión de widgets para el tema activo del tenant.
     */
    public function index($slug)
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        $tenantId = tenant_id();

        // Cargar configuración del tema
        $themeConfig = $this->getThemeConfig($slug, $tenantId);
        if (!$themeConfig) {
            return;
        }

        // Obtener las áreas de widget que soportan 'widget'
        $widgetAreas = [];
        foreach ($themeConfig['content_areas'] ?? ($themeConfig['widget_areas'] ?? []) as $area) {
            if (isset($area['supports']) && is_array($area['supports']) && in_array('widget', $area['supports'])) {
                $widgetAreas[$area['id']] = $area;
            } elseif (!isset($area['supports']) && isset($themeConfig['widget_areas'])) {
                $widgetAreas[$area['slug'] ?? $area['id']] = $area;
            }
        }

        if (empty($widgetAreas)) {
            flash('warning', 'Este tema no tiene áreas de widgets definidas.');
            header('Location: /' . admin_path() . '/themes');
            exit;
        }

        // Cargar widgets disponibles
        WidgetManager::registerAvailableWidgets();
        $availableWidgets = WidgetManager::getAvailableWidgets();

        // Cargar widgets asignados (instancias) para este tenant
        $assignedWidgets = [];

        try {
            $sql = "SELECT * FROM widget_instances WHERE theme_slug = :theme_slug AND tenant_id = :tenant_id ORDER BY area_slug, position";
            $params = [
                ':theme_slug' => $slug,
                ':tenant_id' => $tenantId
            ];

            $pdo = Database::connect();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $instances = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($instances as $instance) {
                $config = json_decode($instance['config'], true);
                $config = is_array($config) ? $config : [];

                $assignedWidgets[$instance['area_slug']][] = [
                    'id' => $instance['id'],
                    'widget_slug' => $instance['widget_slug'],
                    'config' => $config,
                    'widget_info' => $availableWidgets[$instance['widget_slug']] ?? ['name' => $instance['widget_slug'], 'description' => 'Tipo desconocido']
                ];
            }
        } catch (\Exception $e) {
            error_log("Error al cargar instancias de widgets para tenant {$tenantId}: " . $e->getMessage());
        }

        // Asegurar token CSRF
        if (!function_exists('csrf_token')) {
            require_once APP_ROOT . '/core/helpers.php';
        }
        csrf_token();

        return View::renderTenantAdmin('widgets.index', [
            'title'            => 'Gestionar Widgets: ' . ($themeConfig['name'] ?? $slug),
            'themeSlug'        => $slug,
            'themeConfig'      => $themeConfig,
            'tenantId'         => $tenantId,
            'widgetAreas'      => $widgetAreas,
            'availableWidgets' => $availableWidgets,
            'assignedWidgets'  => $assignedWidgets
        ]);
    }

    /**
     * Guarda la configuración de widgets.
     */
    public function save($slug)
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        $tenantId = tenant_id();

        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                 strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        // Validación CSRF
        $token = $_POST['_token'] ?? '';
        if (!validate_csrf($token)) {
            if ($isAjax) {
                $this->returnJsonResponse(false, 'Token CSRF inválido. Por favor, recarga la página.');
                return;
            }
            flash('error', 'Token CSRF inválido. Por favor, recarga la página.');
            header('Location: /' . admin_path() . '/widgets/' . $slug);
            exit;
        }

        $areasData = $_POST['areas'] ?? [];

        if (!is_array($areasData)) {
            if ($isAjax) {
                $this->returnJsonResponse(false, 'Formato de datos inválido.');
                return;
            }
            flash('error', 'Formato de datos inválido.');
            header('Location: /' . admin_path() . '/widgets/' . $slug);
            exit;
        }

        $pdo = null;
        try {
            $pdo = Database::connect();
            $pdo->beginTransaction();

            // Obtener widgets existentes para este tema/tenant
            $existingWidgetsQuery = "SELECT id, area_slug, position FROM widget_instances
                                    WHERE theme_slug = :slug AND tenant_id = :tenantId";

            $stmtExisting = $pdo->prepare($existingWidgetsQuery);
            $stmtExisting->execute([':slug' => $slug, ':tenantId' => $tenantId]);

            $existingWidgets = [];
            while ($row = $stmtExisting->fetch(\PDO::FETCH_ASSOC)) {
                $key = $row['area_slug'] . '_' . $row['position'];
                $existingWidgets[$key] = $row['id'];
            }

            $widgetsToKeep = [];
            $widgetsProcessed = 0;

            // Preparar sentencias
            $updateSql = "UPDATE widget_instances
                          SET widget_slug = :widgetSlug,
                              config = :config,
                              updated_at = NOW()
                          WHERE id = :id";
            $updateStmt = $pdo->prepare($updateSql);

            $insertSql = "INSERT INTO widget_instances
                          (tenant_id, theme_slug, area_slug, widget_slug, position, config, created_at, updated_at)
                          VALUES
                          (:tenantId, :themeSlug, :areaSlug, :widgetSlug, :position, :config, NOW(), NOW())";
            $insertStmt = $pdo->prepare($insertSql);

            // Procesar cada área y sus widgets
            foreach ($areasData as $areaSlug => $widgetsInArea) {
                if (!is_array($widgetsInArea)) {
                    continue;
                }

                foreach ($widgetsInArea as $position => $widgetData) {
                    if (!isset($widgetData['widget_slug']) || !is_string($widgetData['widget_slug'])) {
                        continue;
                    }

                    $widgetSlug = trim($widgetData['widget_slug']);
                    if (empty($widgetSlug)) {
                        continue;
                    }

                    $configData = $widgetData['config'] ?? [];
                    $widgetsProcessed++;

                    // Sanitizar config
                    $widgetClassInstance = WidgetManager::getWidgetInstance($widgetSlug);
                    if ($widgetClassInstance && method_exists($widgetClassInstance, 'sanitizeConfig')) {
                        $configData = $widgetClassInstance->sanitizeConfig($configData);
                    }

                    $positionKey = $areaSlug . '_' . $position;

                    if (isset($existingWidgets[$positionKey])) {
                        // Actualizar
                        $widgetId = $existingWidgets[$positionKey];
                        $widgetsToKeep[] = $widgetId;

                        $updateStmt->execute([
                            ':widgetSlug' => $widgetSlug,
                            ':config' => json_encode($configData),
                            ':id' => $widgetId
                        ]);
                    } else {
                        // Insertar
                        $insertStmt->execute([
                            ':tenantId' => $tenantId,
                            ':themeSlug' => $slug,
                            ':areaSlug' => $areaSlug,
                            ':widgetSlug' => $widgetSlug,
                            ':position' => (int)$position,
                            ':config' => json_encode($configData)
                        ]);

                        // Obtener ID del nuevo widget (compatible con PostgreSQL)
                        $driver = Database::getDriver()->getDriverName();
                        if ($driver === 'pgsql') {
                            // Para PostgreSQL, hacer una consulta adicional
                            $lastIdStmt = $pdo->query("SELECT lastval()");
                            $newId = $lastIdStmt->fetchColumn();
                        } else {
                            $newId = $pdo->lastInsertId();
                        }
                        $widgetsToKeep[] = $newId;
                    }
                }
            }

            // Eliminar widgets que ya no están presentes
            if (!empty($widgetsToKeep)) {
                $placeholders = implode(',', array_fill(0, count($widgetsToKeep), '?'));
                $deleteQuery = "DELETE FROM widget_instances
                               WHERE theme_slug = ? AND tenant_id = ? AND id NOT IN ($placeholders)";

                $deleteParams = [$slug, $tenantId];
                foreach ($widgetsToKeep as $id) {
                    $deleteParams[] = $id;
                }

                $deleteStmt = $pdo->prepare($deleteQuery);
                $deleteStmt->execute($deleteParams);
            } else {
                // Eliminar todos los widgets del tema/tenant
                $deleteAllQuery = "DELETE FROM widget_instances WHERE theme_slug = ? AND tenant_id = ?";
                $deleteAllStmt = $pdo->prepare($deleteAllQuery);
                $deleteAllStmt->execute([$slug, $tenantId]);
            }

            $pdo->commit();

            if ($isAjax) {
                $this->returnJsonResponse(true, 'Configuración de widgets guardada correctamente.');
                return;
            }

            flash('success', 'Configuración de widgets guardada correctamente.');

        } catch (\Exception $e) {
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errorMsg = 'Error al guardar la configuración de widgets: ' . $e->getMessage();
            error_log("Error guardando widgets para tenant {$tenantId}, tema {$slug}: " . $e->getMessage());

            if ($isAjax) {
                $this->returnJsonResponse(false, $errorMsg);
                return;
            }

            flash('error', $errorMsg);
        }

        header('Location: /' . admin_path() . '/widgets/' . $slug);
        exit;
    }

    /**
     * Helper para cargar theme.json.
     */
    protected function getThemeConfig($slug, $tenantId): ?array
    {
        $themePath = null;

        // Buscar primero en temas personalizados del tenant
        $tryPath = realpath(APP_ROOT . "/themes/tenant_{$tenantId}/{$slug}");
        if ($tryPath && is_dir($tryPath)) {
            $themePath = $tryPath;
        }

        // Si no existe, buscar en temas globales
        if (!$themePath) {
            $tryPath = realpath(APP_ROOT . "/themes/{$slug}");
            if ($tryPath && is_dir($tryPath)) {
                $themePath = $tryPath;
            }
        }

        if (!$themePath) {
            flash('error', 'El tema no existe.');
            header('Location: /' . admin_path() . '/themes');
            exit;
        }

        $configFile = $themePath . '/theme.json';
        if (!file_exists($configFile)) {
            flash('warning', 'El tema no tiene archivo de configuración (theme.json).');
            header('Location: /' . admin_path() . '/themes');
            exit;
        }

        $config = json_decode(file_get_contents($configFile), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($config)) {
            flash('error', 'Error al leer o decodificar theme.json.');
            header('Location: /' . admin_path() . '/themes');
            exit;
        }

        return $config;
    }

    /**
     * Helper para respuestas JSON.
     */
    protected function returnJsonResponse($success, $message, $data = [])
    {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }
}
