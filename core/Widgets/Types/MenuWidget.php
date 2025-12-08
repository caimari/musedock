<?php
namespace Screenart\Musedock\Widgets\Types;

use Screenart\Musedock\Models\Menu; // Asumiendo que tienes un modelo Menu
use Screenart\Musedock\Widgets\WidgetBase;

class MenuWidget extends WidgetBase
{
    public static string $slug = 'menu';
    public static string $name = 'Menú de Navegación';
    public static string $description = 'Muestra un menú creado previamente.';

    public function form(array $config = [], ?int $instanceId = null): string
    {
        $title = $config['title'] ?? '';
        $selectedMenuId = $config['menu_id'] ?? null;

        // Obtener menús disponibles con sus traducciones
        $availableMenus = [];
        try {
            // Obtener el tenant_id actual si existe
            $tenantId = function_exists('tenant_id') ? tenant_id() : null;
            $currentLocale = function_exists('current_locale') ? current_locale() : 'es';

            // Query base para obtener menús
            $query = "
                SELECT m.id, mt.title
                FROM site_menus m
                LEFT JOIN site_menu_translations mt ON m.id = mt.menu_id AND mt.locale = :locale
            ";

            // Agregar filtro de tenant si aplica
            if ($tenantId !== null) {
                $query .= " WHERE m.tenant_id = :tenant_id";
            } else {
                $query .= " WHERE m.tenant_id IS NULL";
            }

            $query .= " ORDER BY mt.title ASC";

            // Ejecutar query
            $pdo = \Screenart\Musedock\Database::connect();
            $stmt = $pdo->prepare($query);
            $stmt->bindValue(':locale', $currentLocale);
            if ($tenantId !== null) {
                $stmt->bindValue(':tenant_id', $tenantId);
            }
            $stmt->execute();
            $availableMenus = $stmt->fetchAll(\PDO::FETCH_OBJ);

            error_log("MenuWidget: Encontrados " . count($availableMenus) . " menús para tenant: " . ($tenantId ?? 'global') . ", locale: " . $currentLocale);
        } catch (\Exception $e) {
            $availableMenus = [];
            error_log("Error obteniendo menús para widget: " . $e->getMessage());
        }

        $output = '<div class="mb-3">';
        $output .= '<label class="form-label">Título (opcional)</label>';
        $output .= '<input type="text" name="config[title]" value="' . e($title) . '" class="form-control form-control-sm">';
        $output .= '</div>';

        $output .= '<div class="mb-3">';
        $output .= '<label class="form-label">Selecciona un Menú</label>';
        $output .= '<select name="config[menu_id]" class="form-select form-select-sm">';
        $output .= '<option value="">-- Ninguno --</option>';
        if (!empty($availableMenus)) {
            foreach ($availableMenus as $menu) {
                $menuTitle = $menu->title ?? 'Menú #' . $menu->id;
                $output .= '<option value="' . $menu->id . '" ' . ($selectedMenuId == $menu->id ? 'selected' : '') . '>' . e($menuTitle) . '</option>';
            }
        } else {
            $output .= '<option value="" disabled>No hay menús disponibles</option>';
        }
        $output .= '</select>';
        $output .= '</div>';

        return $output;
    }

    public function render(array $config = []): string
    {
        $title = $config['title'] ?? '';
        $menuId = $config['menu_id'] ?? null;

        if (!$menuId) {
            return '<!-- Widget Menú: No se seleccionó menú -->';
        }

        $output = '<div class="widget widget_nav_menu">';
        if (!empty($title)) {
            $output .= '<h4 class="widget-title">' . e($title) . '</h4>';
        }
        // Renderizar el menú (necesitas tu función render_menu o similar)
        // Asegúrate que render_menu puede aceptar un ID de menú
        if (function_exists('render_menu_by_id')) { // O el nombre de tu función
             $output .= render_menu_by_id((int)$menuId);
        } else {
             $output .= '<!-- Error: Función para renderizar menú por ID no encontrada -->';
        }
        $output .= '</div>';

        return $output;
    }

    public function sanitizeConfig(array $config): array
    {
        $sanitized = [];
        $sanitized['title'] = isset($config['title']) ? strip_tags(trim($config['title'])) : '';
        $sanitized['menu_id'] = isset($config['menu_id']) && is_numeric($config['menu_id']) ? (int)$config['menu_id'] : null;
        return $sanitized;
    }
}