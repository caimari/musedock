<?php
namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Security\PermissionManager;
use Screenart\Musedock\Logger;
use Screenart\Musedock\Widgets\WidgetManager;
use Screenart\Musedock\Models\WidgetInstance;

use Screenart\Musedock\Traits\RequiresPermission;
class ThemeWidgetController
{
    use RequiresPermission;

    /**
     * Muestra la página de gestión de widgets para un tema y tenant (opcional).
     */
    public function index($slug, $tenantId = null)
{
    SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');
    // --- Permisos ---
    // ...
    
    // --- Cargar Configuración del Tema (theme.json) ---
    $themeConfig = $this->getThemeConfig($slug, $tenantId);
    if (!$themeConfig) {
        // getThemeConfig ya debería haber manejado el flash/redirect si no existe
        return;
    }
    
    // Obtener las áreas de widget definidas que soportan 'widget'
    $widgetAreas = [];
    foreach ($themeConfig['content_areas'] ?? ($themeConfig['widget_areas'] ?? []) as $area) {
         // Verificar si el área soporta widgets
         if (isset($area['supports']) && is_array($area['supports']) && in_array('widget', $area['supports'])) {
             $widgetAreas[$area['id']] = $area; // Usar el 'id' (slug) como clave
         }
         // Compatibilidad con formato anterior (si solo tenías widget_areas)
         elseif (!isset($area['supports']) && isset($themeConfig['widget_areas'])) {
              $widgetAreas[$area['slug']] = $area; // Usar slug como clave
         }
    }

    if (empty($widgetAreas)) {
        flash('warning', 'Este tema no tiene áreas de widgets definidas en su theme.json.');
        header('Location: ' . $this->safeRoute('themes.index')); // Usar método helper para rutas seguras
        exit;
    }
    // --- Fin Cargar Configuración ---

    // --- Cargar Widgets Disponibles ---
    WidgetManager::registerAvailableWidgets(); // Asegurar que todos los widgets estén registrados
    $availableWidgets = WidgetManager::getAvailableWidgets();
    // ----------------------------------

    // --- Cargar Widgets Asignados (Instancias) ---
    $assignedWidgets = [];
    
    // Log para debugging
    error_log("ThemeWidgetController::index - Cargando widgets para tema: {$slug}, tenant: " . ($tenantId ?? 'NULL'));
    
    try {
        // Consulta SQL directa para diagnóstico
        $sql = "SELECT * FROM widget_instances WHERE theme_slug = :theme_slug";
        $params = [':theme_slug' => $slug];
        
        if ($tenantId !== null) {
            $sql .= " AND tenant_id = :tenant_id";
            $params[':tenant_id'] = $tenantId;
        } else {
            $sql .= " AND tenant_id IS NULL";
        }
        
        $sql .= " ORDER BY area_slug, position";
        
        $pdo = Database::connect();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $instances = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        error_log("ThemeWidgetController::index - Encontradas " . count($instances) . " instancias de widgets");
        
        // Más debugging
        foreach ($instances as $instance) {
            error_log("Instancia de widget - ID: {$instance['id']}, Área: {$instance['area_slug']}, " . 
                     "Widget: {$instance['widget_slug']}, Config: " . $instance['config']);
            
            $decodedConfig = json_decode($instance['config'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Error decodificando config JSON: " . json_last_error_msg());
            }
            
            // Asegurarnos que se decodificó correctamente
            $config = is_array($decodedConfig) ? $decodedConfig : [];
            
            // Agrupar por área
            $assignedWidgets[$instance['area_slug']][] = [
                'id' => $instance['id'],
                'widget_slug' => $instance['widget_slug'],
                'config' => $config,
                // Añadir datos del widget base si están disponibles
                'widget_info' => $availableWidgets[$instance['widget_slug']] ?? ['name' => $instance['widget_slug'], 'description' => 'Tipo desconocido']
            ];
        }
    } catch (\Exception $e) {
        error_log("Error al cargar instancias de widgets: " . $e->getMessage());
    }
    // ---------------------------------------

    // --- Asegurar que el token CSRF esté generado ---
    if (!function_exists('csrf_token')) {
        require_once APP_ROOT . '/core/helpers.php';
    }
    // Forzar generación del token antes de renderizar la vista
    $csrfToken = csrf_token();
    error_log("CSRF token generado para widgets/index: " . substr($csrfToken, 0, 10) . "... (length: " . strlen($csrfToken) . ")");

    // --- Renderizar Vista ---
    return View::renderSuperadmin('widgets.index', [
        'title'            => 'Gestionar Widgets: ' . ($themeConfig['name'] ?? $slug) . ($tenantId ? " (Tenant #{$tenantId})" : " (Global)"),
        'themeSlug'        => $slug,
        'tenantId'         => $tenantId,
        'widgetAreas'      => $widgetAreas,       // Definiciones de las áreas desde theme.json
        'availableWidgets' => $availableWidgets,  // Tipos de widgets disponibles ['slug'=>['name', 'desc']]
        'assignedWidgets'  => $assignedWidgets    // Widgets asignados por área ['area_slug'=>[widget1, widget2]]
    ]);
}

  /**
 * Guarda la configuración de widgets recibida desde el formulario.
 * Versión mejorada que mantiene los IDs de widgets existentes.
 */
public function save($slug, $tenantId = null)
{
    SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

    // Verificar si es una petición AJAX
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
             strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    // --- Validación CSRF ---
    $token = $_POST['_token'] ?? '';
    $sessionToken = $_SESSION['_csrf_token'] ?? '';

    error_log("CSRF Debug - Token recibido: " . substr($token, 0, 10) . "... (length: " . strlen($token) . ")");
    error_log("CSRF Debug - Token en sesión: " . substr($sessionToken, 0, 10) . "... (length: " . strlen($sessionToken) . ")");
    error_log("CSRF Debug - Session ID: " . session_id());
    error_log("CSRF Debug - POST keys: " . implode(', ', array_keys($_POST)));

    if (!validate_csrf($token)) {
        error_log("CSRF token validation failed in ThemeWidgetController::save");
        error_log("CSRF Debug - Tokens match: " . (hash_equals($sessionToken, $token) ? 'YES' : 'NO'));

        if ($isAjax) {
            $this->returnJsonResponse(false, 'Token CSRF inválido. Por favor, recarga la página.');
            return;
        }
        flash('error', 'Token CSRF inválido. Por favor, recarga la página.');
        $this->redirectToWidgetIndex($slug, $tenantId);
        return;
    }

    error_log("CSRF token validated successfully");

    // --- Permisos ---
    // if (!PermissionManager::canManageThemes() && !PermissionManager::canManageWidgets()) { /* ... error ... */ }

    // --- Obtener datos del POST ---
    $areasData = $_POST['areas'] ?? [];

    // --- Validación Básica ---
    if (!is_array($areasData)) {
        if ($isAjax) {
            $this->returnJsonResponse(false, 'Formato de datos inválido.');
            return;
        }
        
        flash('error', 'Formato de datos inválido.');
        $this->redirectToWidgetIndex($slug, $tenantId);
        return;
    }

    // Log para debugging
    error_log("Guardando widgets para tema $slug" . ($tenantId ? ", tenant $tenantId" : " (global)"));
    error_log("POST areas data: " . json_encode($areasData));
    
    // --- Lógica de Guardado (Dentro de Transacción) ---
    $pdo = null;
    try {
        $pdo = Database::connect();
        $pdo->beginTransaction();

        // 1. Obtener widgets existentes para este tema/tenant
        $existingWidgetsQuery = "SELECT id, area_slug, position FROM widget_instances 
                                WHERE theme_slug = :slug AND " .
                                ($tenantId !== null ? "tenant_id = :tenantId" : "tenant_id IS NULL");
        
        $stmtExisting = $pdo->prepare($existingWidgetsQuery);
        $existingParams = [':slug' => $slug];
        if ($tenantId !== null) {
            $existingParams[':tenantId'] = $tenantId;
        }
        $stmtExisting->execute($existingParams);
        
        // Mapear widgets existentes por área y posición para búsqueda rápida
        $existingWidgets = [];
        while ($row = $stmtExisting->fetch(\PDO::FETCH_ASSOC)) {
            $key = $row['area_slug'] . '_' . $row['position'];
            $existingWidgets[$key] = $row['id'];
        }
        
        error_log("Widgets existentes encontrados: " . count($existingWidgets));
        
        // 2. Recopilar IDs de widgets a mantener
        $widgetsToKeep = [];
        $widgetsProcessed = 0;
        
        // 3. Sentencias SQL preparadas para actualizar e insertar
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
        
        // 4. Procesar cada área y sus widgets
        foreach ($areasData as $areaSlug => $widgetsInArea) {
            if (!is_array($widgetsInArea)) {
                continue;
            }
            
            error_log("Procesando área '$areaSlug': " . count($widgetsInArea) . " widgets");
            
            foreach ($widgetsInArea as $position => $widgetData) {
                if (!isset($widgetData['widget_slug']) || !is_string($widgetData['widget_slug'])) {
                    error_log("Widget en área '$areaSlug' posición $position: widget_slug no definido o no es string");
                    continue;
                }

                $widgetSlug = trim($widgetData['widget_slug']);

                // CRÍTICO: No guardar widgets con slug vacío
                if (empty($widgetSlug)) {
                    error_log("Widget en área '$areaSlug' posición $position: widget_slug está vacío, saltando");
                    continue;
                }
                $configData = $widgetData['config'] ?? [];
                $widgetsProcessed++;
                
                // Sanitizar config antes de guardar
                $widgetClassInstance = WidgetManager::getWidgetInstance($widgetSlug);
                if ($widgetClassInstance && method_exists($widgetClassInstance, 'sanitizeConfig')) {
                    $configData = $widgetClassInstance->sanitizeConfig($configData);
                }
                
                // Comprobar si ya existe un widget en esta posición
                $positionKey = $areaSlug . '_' . $position;
                
                if (isset($existingWidgets[$positionKey])) {
                    // Actualizar widget existente
                    $widgetId = $existingWidgets[$positionKey];
                    $widgetsToKeep[] = $widgetId;
                    
                    $updateParams = [
                        ':widgetSlug' => $widgetSlug,
                        ':config' => json_encode($configData),
                        ':id' => $widgetId
                    ];
                    
                    $updateStmt->execute($updateParams);
                    error_log("Widget actualizado: ID $widgetId, slug $widgetSlug");
                } else {
                    // Insertar nuevo widget
                    $insertParams = [
                        ':tenantId' => $tenantId,
                        ':themeSlug' => $slug,
                        ':areaSlug' => $areaSlug,
                        ':widgetSlug' => $widgetSlug,
                        ':position' => (int)$position,
                        ':config' => json_encode($configData)
                    ];
                    
                    $insertStmt->execute($insertParams);
                    $newId = $pdo->lastInsertId();
                    $widgetsToKeep[] = $newId;
                    error_log("Widget nuevo creado: ID $newId, slug $widgetSlug");
                }
            }
        }
        
        // 5. Eliminar widgets que ya no están presentes
       if (!empty($widgetsToKeep)) {
		// Si hay widgets a mantener, eliminar los que no están en la lista
		$placeholders = implode(',', array_fill(0, count($widgetsToKeep), '?'));
		$deleteQuery = "DELETE FROM widget_instances 
					   WHERE theme_slug = ? AND " . 
					   ($tenantId !== null ? "tenant_id = ?" : "tenant_id IS NULL") . 
					   " AND id NOT IN ($placeholders)";

		$deleteParams = [$slug];
		if ($tenantId !== null) {
			$deleteParams[] = $tenantId;
		}

		// Añadir IDs a mantener a los parámetros
		foreach ($widgetsToKeep as $id) {
			$deleteParams[] = $id;
		}

		$deleteStmt = $pdo->prepare($deleteQuery);
		$deleteStmt->execute($deleteParams);
		$deletedCount = $deleteStmt->rowCount();

		error_log("Widgets eliminados: $deletedCount");
	} else {
		// Si no hay widgets a mantener, eliminar TODOS para este tema/tenant
		error_log("No hay widgets para mantener - eliminando todos para el tema $slug" . ($tenantId ? " y tenant $tenantId" : " (global)"));

		$deleteAllQuery = "DELETE FROM widget_instances 
						   WHERE theme_slug = ? AND " . 
						   ($tenantId !== null ? "tenant_id = ?" : "tenant_id IS NULL");

		$deleteAllParams = [$slug];
		if ($tenantId !== null) {
			$deleteAllParams[] = $tenantId;
		}

		$deleteAllStmt = $pdo->prepare($deleteAllQuery);
		$deleteAllStmt->execute($deleteAllParams);
		$deletedCount = $deleteAllStmt->rowCount();

		error_log("Todos los widgets eliminados: $deletedCount");
	}
        // 6. Confirmar transacción
        $pdo->commit();
        
        error_log("Guardado completado: $widgetsProcessed widgets procesados, " . count($widgetsToKeep) . " mantenidos");
        
        if ($isAjax) {
            $this->returnJsonResponse(true, 'Configuración de widgets guardada correctamente.');
            return;
        }
        
        flash('success', 'Configuración de widgets guardada correctamente.');

    } catch (\Exception $e) {
        // Revertir en caso de error
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        $errorMsg = 'Error al guardar la configuración de widgets: ' . $e->getMessage();
        error_log("Error guardando widgets para {$slug}/{$tenantId}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        
        if ($isAjax) {
            $this->returnJsonResponse(false, $errorMsg);
            return;
        }
        
        flash('error', $errorMsg);
    }
    // --- Fin Transacción ---

    // Redirigir de vuelta (solo para solicitudes no-AJAX)
    $this->redirectToWidgetIndex($slug, $tenantId);
}
    /**
     * Helper para cargar y decodificar theme.json de forma segura.
     */
    protected function getThemeConfig($slug, $tenantId = null): ?array
    {
        $themePath = null;
        if ($tenantId) {
            $tryPath = realpath(APP_ROOT . "/themes/tenant_{$tenantId}/{$slug}");
            if ($tryPath && is_dir($tryPath)) { $themePath = $tryPath; }
        }
        if (!$themePath) {
             $tryPath = realpath(APP_ROOT . "/themes/{$slug}");
             if ($tryPath && is_dir($tryPath)) { $themePath = $tryPath; }
        }

        if (!$themePath) {
            flash('error', 'El tema no existe.');
            header('Location: ' . $this->safeRoute('themes.index'));
            exit;
            return null;
        }

        $configFile = $themePath . '/theme.json';
        if (!file_exists($configFile)) {
            flash('warning', 'El tema no tiene archivo de configuración (theme.json).');
            header('Location: ' . $this->safeRoute('themes.index'));
            exit;
            return null;
        }

        $config = json_decode(file_get_contents($configFile), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($config)) {
            flash('error', 'Error al leer o decodificar theme.json.');
            header('Location: ' . $this->safeRoute('themes.index'));
            exit;
            return null;
        }
        return $config;
    }
    
    /**
     * Helper para redireccionar a la página de gestión de widgets
     */
    protected function redirectToWidgetIndex($slug, $tenantId = null)
    {
        $redirectUrl = $this->safeRoute('widgets.index.global', ['slug' => $slug]);
        if ($tenantId !== null) {
            $redirectUrl .= '/' . $tenantId;
        }
        header("Location: {$redirectUrl}");
        exit;
    }
    
    /**
     * Helper para generar rutas de forma segura (evita problemas con preg_replace)
     */
    protected function safeRoute($name, $params = [])
    {
        // Asegurarse de que los parámetros están correctamente preparados
        foreach ($params as $key => $value) {
            if ($value === null) {
                unset($params[$key]);
            } else {
                $params[$key] = (string)$value;
            }
        }
        
        // Llamar a la función route con los parámetros sanitizados
        return route($name, $params);
    }
    
    /**
     * Helper para devolver respuestas JSON
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
