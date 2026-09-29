<?php

namespace Screenart\Musedock\Services\Mcp;

use Screenart\Musedock\Services\ApiToolsRegistry;

/**
 * Matriz de permisos "sección × nivel" para conexiones MCP / API de un tenant.
 *
 * Niveles:
 *   read    → {section}.read
 *   write   → {section}.create + {section}.update   (siempre como borrador)
 *   publish → {section}.publish                      (poner contenido en vivo)
 *   delete  → {section}.delete                       (mover a papelera)
 *
 * Las secciones salen de ApiToolsRegistry: las del core más las de cualquier
 * plugin/módulo que registre tools con permisos "seccion.accion".
 */
class McpPermissions
{
    public const LEVELS = [
        'read'    => 'Leer',
        'write'   => 'Escribir',
        'publish' => 'Publicar',
        'delete'  => 'Borrar',
    ];

    /** Permisos que nunca puede tener una key de tenant. */
    private const SYSTEM_PERMISSIONS = ['tenants.read', 'cross-publish', '*'];

    private const SECTION_LABELS = [
        'pages'      => ['Páginas', 'bi-file-earmark-text'],
        'posts'      => ['Blog (posts)', 'bi-journal-richtext'],
        'categories' => ['Categorías', 'bi-folder'],
        'tags'       => ['Tags', 'bi-tags'],
        'media'      => ['Medios (imágenes)', 'bi-images'],
    ];

    /**
     * Permisos del CMS que necesita la persona que autoriza para conceder cada
     * nivel (todos los de la lista). El CMS no distingue "publicar": se exige el
     * permiso de edición más amplio. Secciones de plugins sin mapear → settings.edit.
     */
    private const CMS_REQUIREMENTS = [
        'pages' => [
            'read'    => ['pages.view'],
            'write'   => ['pages.create', 'pages.edit'],
            'publish' => ['pages.edit'],
            'delete'  => ['pages.delete'],
        ],
        'posts' => [
            'read'    => ['blog.view'],
            'write'   => ['blog.create', 'blog.edit'],
            'publish' => ['blog.edit.all'],
            'delete'  => ['blog.delete'],
        ],
        'categories' => [
            'read'    => ['blog.categories.view'],
            'write'   => ['blog.categories.create', 'blog.categories.edit'],
            'delete'  => ['blog.categories.delete'],
        ],
        'tags' => [
            'read'    => ['blog.tags.view'],
            'write'   => ['blog.tags.create', 'blog.tags.edit'],
            'delete'  => ['blog.tags.delete'],
        ],
        'media' => [
            'read'    => ['media.manage'],
            'write'   => ['media.manage'],
        ],
    ];

    /** Secciones con estado borrador/publicado. */
    private const PUBLISHABLE = ['pages', 'posts'];

    public const TEMPLATES = [
        'readonly' => ['label' => 'Solo lectura', 'levels' => ['read'], 'hint' => 'Consultar contenido. No modifica nada.'],
        'writer'   => ['label' => 'Redactor', 'levels' => ['read', 'write'], 'hint' => 'Crea y edita borradores. Tú publicas. Recomendado para IA.'],
        'editor'   => ['label' => 'Editor', 'levels' => ['read', 'write', 'publish'], 'hint' => 'Además puede publicar y editar contenido en vivo.'],
        'full'     => ['label' => 'Completo', 'levels' => ['read', 'write', 'publish', 'delete'], 'hint' => 'Todo, incluido mover a la papelera.'],
    ];

    /**
     * Secciones disponibles para este tenant.
     *
     * @return array<string, array{label:string, icon:string, levels:string[]}>
     */
    public static function sections(): array
    {
        $sections = [];

        foreach (ApiToolsRegistry::all() as $tool) {
            $permission = $tool['permission'] ?? '';
            if ($permission === '' || in_array($permission, self::SYSTEM_PERMISSIONS, true)) {
                continue;
            }

            [$section, $action] = array_pad(explode('.', $permission, 2), 2, '');
            if ($section === '' || $action === '') {
                continue;
            }

            $level = self::actionToLevel($action);
            if ($level === null) {
                continue;
            }

            if (!isset($sections[$section])) {
                [$label, $icon] = self::SECTION_LABELS[$section] ?? [ucfirst(str_replace(['-', '_'], ' ', $section)), 'bi-plug'];
                $sections[$section] = ['label' => $label, 'icon' => $icon, 'levels' => []];
            }
            $sections[$section]['levels'][$level] = true;

            if (in_array($section, self::PUBLISHABLE, true)) {
                $sections[$section]['levels']['publish'] = true;
            }
        }

        // Core primero, en orden fijo; plugins detrás
        $order = array_flip(array_keys(self::SECTION_LABELS));
        uksort($sections, fn($a, $b) => ($order[$a] ?? 100) <=> ($order[$b] ?? 100) ?: strcmp($a, $b));

        foreach ($sections as &$section) {
            $section['levels'] = array_values(array_filter(
                array_keys(self::LEVELS),
                fn($level) => isset($section['levels'][$level])
            ));
        }

        return $sections;
    }

    /**
     * Convierte la matriz enviada por el formulario (section => [levels]) en
     * la lista de permisos que se guarda en api_keys.permissions.
     */
    public static function toPermissions(array $matrix): array
    {
        $sections = self::sections();
        $permissions = [];

        foreach ($matrix as $section => $levels) {
            if (!isset($sections[$section]) || !is_array($levels)) {
                continue;
            }
            $levels = array_intersect($levels, $sections[$section]['levels']);

            // Escribir/publicar/borrar sin leer no tiene sentido para un agente
            if ($levels) {
                $levels[] = 'read';
            }

            foreach (array_unique($levels) as $level) {
                foreach (self::levelToActions($level) as $action) {
                    $permissions[] = $section . '.' . $action;
                }
            }
        }

        return array_values(array_unique($permissions));
    }

    /**
     * Matriz de una plantilla aplicada a todas las secciones.
     */
    public static function templateMatrix(string $template): array
    {
        $levels = self::TEMPLATES[$template]['levels'] ?? ['read'];
        $matrix = [];
        foreach (self::sections() as $key => $section) {
            $matrix[$key] = array_values(array_intersect($levels, $section['levels']));
        }
        return $matrix;
    }

    /**
     * Resumen legible de una lista de permisos: section => [levels].
     */
    public static function summarize(array $permissions): array
    {
        $summary = [];
        foreach ($permissions as $permission) {
            [$section, $action] = array_pad(explode('.', (string)$permission, 2), 2, '');
            $level = self::actionToLevel($action);
            if ($section === '' || $level === null) {
                continue;
            }
            $summary[$section][$level] = true;
        }

        foreach ($summary as $section => $levels) {
            $summary[$section] = array_values(array_filter(array_keys(self::LEVELS), fn($l) => isset($levels[$l])));
        }

        return $summary;
    }

    /**
     * Nombre de plantilla que coincide exactamente con los permisos, o null.
     */
    public static function detectTemplate(array $permissions): ?string
    {
        $sorted = $permissions;
        sort($sorted);
        foreach (array_keys(self::TEMPLATES) as $template) {
            $candidate = self::toPermissions(self::templateMatrix($template));
            sort($candidate);
            if ($candidate === $sorted) {
                return $template;
            }
        }
        return null;
    }

    // =========================================================================
    // Límite por los permisos de la persona que autoriza
    // =========================================================================

    /**
     * ¿Puede este usuario del panel conceder ese nivel en esa sección?
     *
     * @param int $userId positivo = admins, negativo = users (como en api_keys.user_id)
     */
    public static function userCanGrant(int $userId, int $tenantId, string $section, string $level): bool
    {
        static $cache = [];
        $cacheKey = "{$userId}:{$tenantId}:{$section}:{$level}";
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $realId = abs($userId);
        if ($userId > 0 && \Screenart\Musedock\Helpers\PermissionHelper::isTenantRootAdmin($realId, $tenantId)) {
            return $cache[$cacheKey] = true;
        }

        $required = self::CMS_REQUIREMENTS[$section][$level] ?? ['settings.edit'];
        foreach ($required as $slug) {
            if (!\Screenart\Musedock\Helpers\PermissionHelper::userCan($realId, $slug, $tenantId)) {
                return $cache[$cacheKey] = false;
            }
        }
        return $cache[$cacheKey] = true;
    }

    /**
     * Matriz (section => [levels]) que este usuario puede conceder.
     */
    public static function grantableMatrix(int $userId, int $tenantId): array
    {
        $matrix = [];
        foreach (self::sections() as $key => $section) {
            $matrix[$key] = array_values(array_filter(
                $section['levels'],
                fn($level) => self::userCanGrant($userId, $tenantId, $key, $level)
            ));
        }
        return $matrix;
    }

    /**
     * Recorta una lista de permisos a lo que el usuario puede conceder.
     * Si pierde "leer" en una sección, pierde la sección entera.
     */
    public static function capToUser(array $permissions, int $userId, int $tenantId): array
    {
        $summary = self::summarize($permissions);
        $capped = [];
        foreach ($summary as $section => $levels) {
            $allowed = array_values(array_filter($levels, fn($l) => self::userCanGrant($userId, $tenantId, $section, $l)));
            if (in_array('read', $allowed, true)) {
                $capped[$section] = $allowed;
            }
        }
        return self::toPermissions($capped);
    }

    public static function sectionLabel(string $section): string
    {
        return self::SECTION_LABELS[$section][0] ?? ucfirst(str_replace(['-', '_'], ' ', $section));
    }

    private static function actionToLevel(string $action): ?string
    {
        return match ($action) {
            'read', 'list', 'get', 'view' => 'read',
            'create', 'update', 'write', 'edit' => 'write',
            'publish' => 'publish',
            'delete', 'destroy' => 'delete',
            default => null,
        };
    }

    private static function levelToActions(string $level): array
    {
        return match ($level) {
            'read' => ['read'],
            'write' => ['create', 'update'],
            'publish' => ['publish'],
            'delete' => ['delete'],
            default => [],
        };
    }
}
