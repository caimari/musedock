<?php
namespace Screenart\Musedock\Widgets\Types;

use Screenart\Musedock\Widgets\WidgetBase;
use Screenart\Musedock\Database;

class BlogTagsWidget extends WidgetBase
{
    public static string $slug = 'blog_tags';
    public static string $name = 'Etiquetas del Blog';
    public static string $description = 'Muestra las etiquetas del blog en formato nube o lista.';
    public static string $icon = 'bi-tags';

    /**
     * Renderiza el formulario de configuración del widget en el admin.
     */
    public function form(array $config = [], $instanceId = null): string
    {
        $title = $config['title'] ?? 'Etiquetas';
        $displayMode = $config['display_mode'] ?? 'cloud';
        $showCount = $config['show_count'] ?? false;
        $orderBy = $config['order_by'] ?? 'name';
        $orderDir = $config['order_dir'] ?? 'asc';
        $limit = $config['limit'] ?? 20;
        $hideEmpty = $config['hide_empty'] ?? true;

        $uniqueId = is_null($instanceId) ? uniqid('blogtag_') : 'blogtag_' . $instanceId;

        $output = '<div class="mb-3">
            <label for="' . $uniqueId . '_title" class="form-label">Título del Widget</label>
            <input type="text" id="' . $uniqueId . '_title" name="config[title]"
                   value="' . $this->e($title) . '" class="form-control form-control-sm"
                   placeholder="Ej: Etiquetas">
        </div>';

        $output .= '<div class="mb-3">
            <label for="' . $uniqueId . '_display_mode" class="form-label">Modo de visualización</label>
            <select id="' . $uniqueId . '_display_mode" name="config[display_mode]" class="form-select form-select-sm">
                <option value="cloud" ' . ($displayMode === 'cloud' ? 'selected' : '') . '>Nube de etiquetas</option>
                <option value="list" ' . ($displayMode === 'list' ? 'selected' : '') . '>Lista vertical</option>
            </select>
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
            <label for="' . $uniqueId . '_limit" class="form-label">Máximo de etiquetas</label>
            <input type="number" id="' . $uniqueId . '_limit" name="config[limit]"
                   value="' . (int)$limit . '" class="form-control form-control-sm" min="1" max="100">
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
                <small>Ocultar etiquetas vacías</small>
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
        $title = $config['title'] ?? 'Etiquetas';
        $displayMode = $config['display_mode'] ?? 'cloud';
        $showCount = $config['show_count'] ?? false;
        $orderBy = $config['order_by'] ?? 'name';
        $orderDir = $config['order_dir'] ?? 'asc';
        $limit = (int)($config['limit'] ?? 20);
        $hideEmpty = $config['hide_empty'] ?? true;

        // Obtener etiquetas de la base de datos
        $tags = $this->getTags($orderBy, $orderDir, $limit, $hideEmpty);

        if (empty($tags)) {
            return '';
        }

        // Estructura del widget con margen
        $output = '<div class="widget widget-blog-tags" style="margin-bottom: 30px;">';

        if (!empty($title)) {
            $output .= '<h5 style="font-size: 16px; font-weight: 600; margin-bottom: 12px; color: #333;">' . $this->e($title) . '</h5>';
        }

        $output .= '<div class="widget-content">';

        if ($displayMode === 'cloud') {
            $output .= $this->renderCloud($tags, $showCount);
        } else {
            $output .= $this->renderList($tags, $showCount);
        }

        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Renderiza las etiquetas como nube
     */
    private function renderCloud(array $tags, bool $showCount): string
    {
        // Calcular tamaños basados en el conteo
        $maxCount = max(array_column($tags, 'post_count'));
        $minCount = min(array_column($tags, 'post_count'));
        $range = max(1, $maxCount - $minCount);

        $output = '<div style="display: flex; flex-wrap: wrap; gap: 8px;">';

        foreach ($tags as $tag) {
            // Calcular tamaño de fuente (12px a 18px basado en popularidad)
            $size = 12 + (($tag['post_count'] - $minCount) / $range) * 6;
            $size = round($size);

            $countText = $showCount ? ' (' . (int)$tag['post_count'] . ')' : '';

            $output .= '<a href="/blog/tag/' . $this->e($tag['slug']) . '"
                style="display: inline-block; background: #f5f5f5; color: #555; padding: 4px 10px;
                       border-radius: 15px; font-size: ' . $size . 'px; text-decoration: none;
                       transition: all 0.2s ease; border: 1px solid #ddd;"
                onmouseover="this.style.background=\'#ff656a\';this.style.color=\'#fff\';this.style.borderColor=\'#ff656a\';"
                onmouseout="this.style.background=\'#f5f5f5\';this.style.color=\'#555\';this.style.borderColor=\'#ddd\';">
                <i class="bi bi-tag" style="margin-right: 4px;"></i>' . $this->e($tag['name']) . $countText . '
            </a>';
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Renderiza las etiquetas como lista
     */
    private function renderList(array $tags, bool $showCount): string
    {
        $output = '<ul style="list-style: none; margin: 0; padding: 0;">';

        foreach ($tags as $tag) {
            $count = $showCount ? ' <span style="color: #999; font-size: 12px;">(' . (int)$tag['post_count'] . ')</span>' : '';
            $output .= '<li style="padding: 6px 0; border-bottom: 1px solid #eee;">
                <a href="/blog/tag/' . $this->e($tag['slug']) . '"
                   style="color: #333; text-decoration: none; font-size: 14px; display: flex; justify-content: space-between; align-items: center;">
                    <span><i class="bi bi-tag" style="margin-right: 8px; color: #ff656a;"></i>' . $this->e($tag['name']) . '</span>
                    ' . $count . '
                </a>
            </li>';
        }

        $output .= '</ul>';

        return $output;
    }

    /**
     * Obtiene las etiquetas del blog
     */
    private function getTags(string $orderBy, string $orderDir, int $limit, bool $hideEmpty): array
    {
        try {
            $pdo = Database::connect();

            // Construir ORDER BY seguro
            $validOrderBy = ['name', 'post_count', 'created_at'];
            $orderBy = in_array($orderBy, $validOrderBy) ? $orderBy : 'name';
            $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';

            $sql = "SELECT
                        t.id,
                        t.name,
                        t.slug,
                        t.created_at,
                        COUNT(DISTINCT pt.post_id) as post_count
                    FROM blog_tags t
                    LEFT JOIN blog_post_tags pt ON t.id = pt.tag_id
                    LEFT JOIN blog_posts p ON pt.post_id = p.id AND p.status = 'published' AND p.deleted_at IS NULL
                    WHERE t.deleted_at IS NULL";

            if ($hideEmpty) {
                $sql .= " GROUP BY t.id, t.name, t.slug, t.created_at
                          HAVING post_count > 0";
            } else {
                $sql .= " GROUP BY t.id, t.name, t.slug, t.created_at";
            }

            $sql .= " ORDER BY {$orderBy} {$orderDir} LIMIT :limit";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();

            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Si hide_empty está activo pero no hay etiquetas con posts,
            // mostrar todas las etiquetas de todas formas
            if ($hideEmpty && empty($results)) {
                $sqlAll = "SELECT
                            t.id,
                            t.name,
                            t.slug,
                            t.created_at,
                            0 as post_count
                        FROM blog_tags t
                        WHERE t.deleted_at IS NULL
                        ORDER BY {$orderBy} {$orderDir} LIMIT :limit";

                $stmt = $pdo->prepare($sqlAll);
                $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
                $stmt->execute();
                $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }

            return $results;
        } catch (\Exception $e) {
            error_log("BlogTagsWidget error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Sanitiza la configuración
     */
    public function sanitizeConfig(array $config): array
    {
        return [
            'title' => isset($config['title']) ? strip_tags(trim($config['title'])) : 'Etiquetas',
            'display_mode' => in_array($config['display_mode'] ?? '', ['cloud', 'list']) ? $config['display_mode'] : 'cloud',
            'show_count' => !empty($config['show_count']),
            'order_by' => in_array($config['order_by'] ?? '', ['name', 'post_count', 'created_at']) ? $config['order_by'] : 'name',
            'order_dir' => in_array($config['order_dir'] ?? '', ['asc', 'desc']) ? $config['order_dir'] : 'asc',
            'limit' => max(1, min(100, (int)($config['limit'] ?? 20))),
            'hide_empty' => !empty($config['hide_empty']),
        ];
    }

    private function e($string)
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}
