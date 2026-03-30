<?php
namespace Screenart\Musedock\Widgets\Types;

use Screenart\Musedock\Widgets\WidgetBase;
use Screenart\Musedock\Database;

class BlogCategoriesWidget extends WidgetBase
{
    public static string $slug = 'blog_categories';
    public static string $name = 'Categorías del Blog';
    public static string $description = 'Muestra las categorías del blog con contador de posts.';
    public static string $icon = 'bi-folder';

    /**
     * Renderiza el formulario de configuración del widget en el admin.
     */
    public function form(array $config = [], $instanceId = null): string
    {
        $title = $config['title'] ?? 'Categorías';
        $showCount = $config['show_count'] ?? true;
        $orderBy = $config['order_by'] ?? 'name';
        $orderDir = $config['order_dir'] ?? 'asc';
        $limit = $config['limit'] ?? 10;
        $hideEmpty = $config['hide_empty'] ?? true;

        $uniqueId = is_null($instanceId) ? uniqid('blogcat_') : 'blogcat_' . $instanceId;

        $output = '<div class="mb-3">
            <label for="' . $uniqueId . '_title" class="form-label">Título del Widget</label>
            <input type="text" id="' . $uniqueId . '_title" name="config[title]"
                   value="' . $this->e($title) . '" class="form-control form-control-sm"
                   placeholder="Ej: Categorías">
        </div>';

        $output .= '<div class="mb-3">
            <label for="' . $uniqueId . '_order_by" class="form-label">Ordenar por</label>
            <select id="' . $uniqueId . '_order_by" name="config[order_by]" class="form-select form-select-sm">
                <option value="name" ' . ($orderBy === 'name' ? 'selected' : '') . '>Nombre</option>
                <option value="post_count" ' . ($orderBy === 'post_count' ? 'selected' : '') . '>Número de posts</option>
                <option value="created_at" ' . ($orderBy === 'created_at' ? 'selected' : '') . '>Fecha de creación</option>
            </select>
        </div>';

        $output .= '<div class="mb-3">
            <label for="' . $uniqueId . '_order_dir" class="form-label">Dirección</label>
            <select id="' . $uniqueId . '_order_dir" name="config[order_dir]" class="form-select form-select-sm">
                <option value="asc" ' . ($orderDir === 'asc' ? 'selected' : '') . '>Ascendente (A-Z, menor a mayor)</option>
                <option value="desc" ' . ($orderDir === 'desc' ? 'selected' : '') . '>Descendente (Z-A, mayor a menor)</option>
            </select>
        </div>';

        $output .= '<div class="mb-3">
            <label for="' . $uniqueId . '_limit" class="form-label">Máximo de categorías</label>
            <input type="number" id="' . $uniqueId . '_limit" name="config[limit]"
                   value="' . (int)$limit . '" class="form-control form-control-sm" min="1" max="50">
        </div>';

        $output .= '<div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="config[show_count]"
                   value="1" id="' . $uniqueId . '_show_count" ' . ($showCount ? 'checked' : '') . '>
            <label class="form-check-label" for="' . $uniqueId . '_show_count">
                <small>Mostrar contador de posts</small>
            </label>
        </div>';

        $output .= '<div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="config[hide_empty]"
                   value="1" id="' . $uniqueId . '_hide_empty" ' . ($hideEmpty ? 'checked' : '') . '>
            <label class="form-check-label" for="' . $uniqueId . '_hide_empty">
                <small>Ocultar categorías vacías</small>
            </label>
        </div>';

        $output .= '<div class="d-flex justify-content-end mt-3">
            <button type="button" class="btn btn-sm btn-outline-secondary widget-form-close">
                <i class="bi bi-check-circle me-1"></i> Aceptar
            </button>
        </div>';

        return $output;
    }

    /**
     * Renderiza el widget en el frontend.
     */
    public function render(array $config = []): string
    {
        $title = $config['title'] ?? 'Categorías';
        $showCount = $config['show_count'] ?? true;
        $orderBy = $config['order_by'] ?? 'name';
        $orderDir = $config['order_dir'] ?? 'asc';
        $limit = (int)($config['limit'] ?? 10);
        $hideEmpty = $config['hide_empty'] ?? true;

        // Obtener categorías de la base de datos
        $categories = $this->getCategories($orderBy, $orderDir, $limit, $hideEmpty);

        if (empty($categories)) {
            return '';
        }

        // Estructura del widget con margen
        $output = '<div class="widget widget-blog-categories" style="margin-bottom: 15px;">';

        if (!empty($title)) {
            $output .= '<h5 style="font-size: 14px !important; font-weight: 600; margin-bottom: 12px; color: #000 !important;">' . $this->e($title) . '</h5>';
        }

        $output .= '<div class="widget-content">
            <ul style="list-style: none; margin: 0; padding: 0;">';

        foreach ($categories as $category) {
            $count = $showCount ? ' <span style="color: #666 !important; font-size: 12px;">(' . (int)$category['post_count'] . ')</span>' : '';
            $output .= '<li style="padding: 6px 0; border-bottom: 1px solid #eee;">
                <a href="/blog/category/' . $this->e($category['slug']) . '"
                   style="color: #000 !important; text-decoration: none; font-size: 14px; display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #000 !important;"><i class="bi bi-folder" style="margin-right: 8px; color: #ff656a;"></i>' . $this->e($category['name']) . '</span>
                    ' . $count . '
                </a>
            </li>';
        }

        $output .= '</ul>
        </div>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Obtiene las categorías del blog
     */
    private function getCategories(string $orderBy, string $orderDir, int $limit, bool $hideEmpty): array
    {
        try {
            $pdo = Database::connect();

            // Construir ORDER BY seguro
            $validOrderBy = ['name', 'post_count', 'created_at'];
            $orderBy = in_array($orderBy, $validOrderBy) ? $orderBy : 'name';
            $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';

            $sql = "SELECT
                        c.id,
                        c.name,
                        c.slug,
                        c.created_at,
                        COUNT(DISTINCT pc.post_id) as post_count
                    FROM blog_categories c
                    LEFT JOIN blog_post_categories pc ON c.id = pc.category_id
                    LEFT JOIN blog_posts p ON pc.post_id = p.id AND p.status = 'published' AND p.deleted_at IS NULL
                    WHERE c.deleted_at IS NULL";

            if ($hideEmpty) {
                $sql .= " GROUP BY c.id, c.name, c.slug, c.created_at
                          HAVING post_count > 0";
            } else {
                $sql .= " GROUP BY c.id, c.name, c.slug, c.created_at";
            }

            $sql .= " ORDER BY {$orderBy} {$orderDir} LIMIT :limit";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();

            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Si hide_empty está activo pero no hay categorías con posts,
            // mostrar todas las categorías de todas formas
            if ($hideEmpty && empty($results)) {
                $sqlAll = "SELECT
                            c.id,
                            c.name,
                            c.slug,
                            c.created_at,
                            0 as post_count
                        FROM blog_categories c
                        WHERE c.deleted_at IS NULL
                        ORDER BY {$orderBy} {$orderDir} LIMIT :limit";

                $stmt = $pdo->prepare($sqlAll);
                $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
                $stmt->execute();
                $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }

            return $results;
        } catch (\Exception $e) {
            error_log("BlogCategoriesWidget error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Sanitiza la configuración
     */
    public function sanitizeConfig(array $config): array
    {
        return [
            'title' => isset($config['title']) ? strip_tags(trim($config['title'])) : 'Categorías',
            'show_count' => !empty($config['show_count']),
            'order_by' => in_array($config['order_by'] ?? '', ['name', 'post_count', 'created_at']) ? $config['order_by'] : 'name',
            'order_dir' => in_array($config['order_dir'] ?? '', ['asc', 'desc']) ? $config['order_dir'] : 'asc',
            'limit' => max(1, min(50, (int)($config['limit'] ?? 10))),
            'hide_empty' => !empty($config['hide_empty']),
        ];
    }

    private function e($string)
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}
