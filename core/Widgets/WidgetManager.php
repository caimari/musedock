<?php
namespace Screenart\Musedock\Widgets;

use Screenart\Musedock\Models\WidgetInstance;
use Screenart\Musedock\Database; // Si necesitas conexión directa

class WidgetManager
{
    /** @var array Almacena las clases de widgets registrados ['slug' => ClassName] */
    protected static $registeredWidgets = [];

    /** @var array Cache para instancias de widgets ['slug' => WidgetBase] */
    protected static $widgetInstances = [];

    /**
     * Registra todos los widgets disponibles encontrados en el directorio Types.
     * Debería llamarse una vez durante el bootstrap de la aplicación.
     */
    public static function registerAvailableWidgets()
    {
        // Reiniciar por si se llama múltiples veces (aunque no debería)
        self::$registeredWidgets = [];
        self::$widgetInstances = [];

        $widgetDir = __DIR__ . '/Types'; // Directorio donde están las clases de Widget
        $namespace = __NAMESPACE__ . '\\Types\\';

        if (!is_dir($widgetDir)) {
            error_log("Directorio de Widgets no encontrado: " . $widgetDir);
            return;
        }

        $files = glob($widgetDir . '/*.php');
        if ($files) {
            foreach ($files as $file) {
                $className = $namespace . basename($file, '.php');
                // Verificar que la clase existe y es subclase de WidgetBase
                if (class_exists($className) && is_subclass_of($className, WidgetBase::class)) {
                    // Usar reflexión para obtener propiedades estáticas
                    if (property_exists($className, 'slug') && !empty($className::$slug)) {
                        self::$registeredWidgets[$className::$slug] = $className;
                        // Debug log
                        error_log("Widget registrado: [{$className::$slug}] => {$className}");
                    } else {
                        error_log("Widget sin slug definido: {$className}");
                    }
                }
            }
        }
        error_log("Total Widgets Registrados: " . count(self::$registeredWidgets));
    }

    /**
     * Devuelve la lista de widgets registrados.
     * Asegura que se hayan registrado antes de devolver.
     *
     * @return array ['slug' => ['name' => 'Nombre', 'description' => 'Desc', 'icon' => 'icon-class', 'class' => ClassName]]
     */
    public static function getAvailableWidgets(): array
    {
        // Si aún no se han registrado, hacerlo ahora
        if (empty(self::$registeredWidgets)) {
            self::registerAvailableWidgets();
        }

        $available = [];
        foreach (self::$registeredWidgets as $slug => $className) {
            // Asegurarse de que las propiedades estáticas existan
            if (property_exists($className, 'name') && property_exists($className, 'description')) {
                $available[$slug] = [
                    'name' => $className::$name,
                    'description' => $className::$description,
                    'icon' => property_exists($className, 'icon') ? $className::$icon : 'bi-puzzle',
                    'class' => $className // Guardar el nombre de la clase para instanciar después
                ];
            }
        }
        
        // Log para depuración
        error_log("Widgets disponibles: " . json_encode(array_keys($available)));
        
        return $available;
    }

    /**
     * Obtiene una instancia (singleton) de una clase de widget específica.
     *
     * @param string $slug El slug del widget.
     * @return WidgetBase|null Una instancia del widget o null si no está registrado.
     */
    public static function getWidgetInstance(string $slug): ?WidgetBase
    {
        // Devolver instancia cacheada si existe
        if (isset(self::$widgetInstances[$slug])) {
            return self::$widgetInstances[$slug];
        }

        // Asegurar que los widgets están registrados
        if (empty(self::$registeredWidgets)) {
            self::registerAvailableWidgets();
        }

        // Verificar si el slug solicitado está registrado
        if (!isset(self::$registeredWidgets[$slug])) {
            error_log("Intento de instanciar widget no registrado: {$slug}");
            error_log("Widgets disponibles: " . implode(", ", array_keys(self::$registeredWidgets)));
            return null;
        }

        $className = self::$registeredWidgets[$slug];
        if (class_exists($className)) {
            try {
                self::$widgetInstances[$slug] = new $className(); // Crear y cachear instancia
                return self::$widgetInstances[$slug];
            } catch (\Exception $e) {
                error_log("Error al crear instancia del widget {$slug}: " . $e->getMessage());
                return null;
            }
        }

        error_log("Clase no encontrada para widget registrado: {$className}");
        return null;
    }

    /**
     * Renderiza todos los widgets asignados a un área específica para un tenant/tema.
     *
     * @param string $areaSlug Slug del área de widgets.
     * @param int|null $tenantId ID del tenant (NULL para global).
     * @param string|null $themeSlug Slug del tema (si no se pasa, intenta obtener el activo).
     * @return string HTML combinado de los widgets del área.
     */
    public static function renderArea(string $areaSlug, ?int $tenantId = null, ?string $themeSlug = null): string
    {
        if ($themeSlug === null) {
            $themeSlug = setting('default_theme', 'default'); // Obtener tema activo
        }

        // Log para depuración
        error_log("WidgetManager::renderArea - Renderizando área: {$areaSlug}, Tema: {$themeSlug}, Tenant: " . ($tenantId ?? 'NULL'));

        // Obtener instancias de la BD para esta área, tema y tenant (o global)
        // Priorizar tenant específico, luego global
        $widgetInstances = WidgetInstance::getInstancesForArea($themeSlug, $areaSlug, $tenantId);

        $output = '';
        if (!empty($widgetInstances)) {
            foreach ($widgetInstances as $instance) {
                // Obtener la instancia de la clase del widget
                $widget = self::getWidgetInstance($instance->widget_slug);
                if ($widget instanceof WidgetBase) {
                    try {
                        // Renderizar el widget con su configuración
                        $output .= $widget->render($instance->config ?? []);
                    } catch (\Exception $e) {
                        error_log("Error renderizando widget '{$instance->widget_slug}' (ID: {$instance->id}) en área '{$areaSlug}': " . $e->getMessage());
                        $output .= "<!-- Error Widget {$instance->widget_slug}: " . htmlspecialchars($e->getMessage()) . " -->";
                    }
                } else {
                    $output .= "<!-- Widget Desconocido: {$instance->widget_slug} -->";
                }
            }
        } else {
            // Mensaje opcional si el área está vacía
            $output = "<!-- Área de Widgets '{$areaSlug}' vacía -->";
        }

        // Envolver el área (opcional)
        return '<div class="widget-area widget-area-' . htmlspecialchars($areaSlug) . '">' . $output . '</div>';
    }
}