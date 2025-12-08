<?php

namespace Screenart\Musedock\Helpers;

use Screenart\Musedock\Database;
use Screenart\Musedock\Env;

/**
 * Escáner de permisos del código fuente
 *
 * Detecta automáticamente los permisos usados en checkPermission()
 * dentro de los controladores y permite sincronizarlos con la BD.
 *
 * Cuando multi-tenant está activo, también escanea:
 * - core/Controllers/Admin/ (panel admin de tenant)
 * - core/Controllers/Tenant/ (controladores de tenant)
 * - modules/{modulo}/Controllers/ (módulos)
 */
class PermissionScanner
{
    /**
     * Directorios base de controladores
     */
    private static string $coreControllersPath = __DIR__ . '/../Controllers';
    private static string $modulesPath = __DIR__ . '/../../modules';

    /**
     * Verifica si multi-tenant está habilitado
     */
    private static function isMultiTenantEnabled(): bool
    {
        $envValue = Env::get('MULTI_TENANT_ENABLED', null);
        if ($envValue !== null) {
            return filter_var($envValue, FILTER_VALIDATE_BOOLEAN);
        }
        // Fallback a setting() si existe la función
        if (function_exists('setting')) {
            return (bool) setting('multi_tenant_enabled', false);
        }
        return false;
    }

    /**
     * Obtiene los directorios a escanear según la configuración
     *
     * @return array Lista de directorios con su tipo
     */
    private static function getDirectoriesToScan(): array
    {
        $directories = [];

        // Siempre escanear Superadmin
        $superadminPath = realpath(self::$coreControllersPath . '/Superadmin');
        if ($superadminPath && is_dir($superadminPath)) {
            $directories[] = [
                'path' => $superadminPath,
                'type' => 'superadmin',
                'label' => 'Superadmin'
            ];
        }

        // SIEMPRE escanear Módulos (son parte del sistema principal)
        $modulesPath = realpath(self::$modulesPath);
        if ($modulesPath && is_dir($modulesPath)) {
            // Buscar en controllers y Controllers (ambas variantes)
            $modulesDirs = array_merge(
                glob($modulesPath . '/*/Controllers', GLOB_ONLYDIR) ?: [],
                glob($modulesPath . '/*/controllers', GLOB_ONLYDIR) ?: []
            );
            foreach ($modulesDirs as $moduleDir) {
                $moduleName = basename(dirname($moduleDir));
                $directories[] = [
                    'path' => $moduleDir,
                    'type' => 'module',
                    'label' => "Módulo: {$moduleName}"
                ];
            }
        }

        // Si multi-tenant está activo, escanear también Admin y Tenant
        if (self::isMultiTenantEnabled()) {
            // Panel Admin de Tenant
            $adminPath = realpath(self::$coreControllersPath . '/Admin');
            if ($adminPath && is_dir($adminPath)) {
                $directories[] = [
                    'path' => $adminPath,
                    'type' => 'admin',
                    'label' => 'Admin (Tenant)'
                ];
            }

            // Controladores de Tenant
            $tenantPath = realpath(self::$coreControllersPath . '/Tenant');
            if ($tenantPath && is_dir($tenantPath)) {
                $directories[] = [
                    'path' => $tenantPath,
                    'type' => 'tenant',
                    'label' => 'Tenant'
                ];
            }
        }

        return $directories;
    }

    /**
     * Escanea todos los controladores y extrae los slugs de permisos
     *
     * @param bool $includeDetails Si true, incluye información del archivo fuente
     * @return array Lista de slugs únicos encontrados
     */
    public static function scanCodePermissions(bool $includeDetails = true): array
    {
        $permissions = [];
        $directories = self::getDirectoriesToScan();

        foreach ($directories as $dirInfo) {
            $path = $dirInfo['path'];
            $type = $dirInfo['type'];
            $label = $dirInfo['label'];

            if (!is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $content = file_get_contents($file->getPathname());

                    // Buscar checkPermission('slug') o checkPermission("slug")
                    if (preg_match_all("/checkPermission\(['\"]([^'\"]+)['\"]\)/", $content, $matches)) {
                        foreach ($matches[1] as $slug) {
                            if (!isset($permissions[$slug])) {
                                $permissions[$slug] = [
                                    'slug' => $slug,
                                    'files' => [],
                                    'types' => [],
                                ];
                            }

                            $relativePath = str_replace($path, '', $file->getPathname());
                            $permissions[$slug]['files'][] = $label . $relativePath;
                            if (!in_array($type, $permissions[$slug]['types'])) {
                                $permissions[$slug]['types'][] = $type;
                            }
                        }
                    }
                }
            }
        }

        ksort($permissions);
        return $permissions;
    }

    /**
     * Obtiene estadísticas del escaneo
     *
     * @return array Estadísticas por tipo de controlador
     */
    public static function getScanStats(): array
    {
        $permissions = self::scanCodePermissions();
        $stats = [
            'total' => count($permissions),
            'by_type' => [
                'superadmin' => 0,
                'admin' => 0,
                'tenant' => 0,
                'module' => 0,
            ],
            'multi_tenant_enabled' => self::isMultiTenantEnabled(),
            'directories_scanned' => count(self::getDirectoriesToScan()),
        ];

        foreach ($permissions as $perm) {
            foreach ($perm['types'] as $type) {
                if (isset($stats['by_type'][$type])) {
                    $stats['by_type'][$type]++;
                }
            }
        }

        return $stats;
    }

    /**
     * Obtiene los permisos que existen en la BD
     *
     * @param bool $includeAll Si true, incluye permisos de tenant también
     * @return array Lista de slugs en la BD
     */
    public static function getDatabasePermissions(bool $includeAll = false): array
    {
        try {
            $query = Database::table('permissions');

            if (!$includeAll) {
                $query->whereNull('tenant_id');
            }

            $permissions = $query->get();

            $result = [];
            foreach ($permissions as $perm) {
                $perm = (array) $perm;
                if (!empty($perm['slug'])) {
                    $result[$perm['slug']] = $perm;
                }
            }

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Compara permisos del código vs BD
     *
     * @return array [
     *   'in_code_only' => permisos en código pero no en BD,
     *   'in_db_only' => permisos en BD pero no en código,
     *   'synced' => permisos sincronizados
     * ]
     */
    public static function comparePermissions(): array
    {
        $codePermissions = self::scanCodePermissions();
        $dbPermissions = self::getDatabasePermissions(true);

        $codeSlugs = array_keys($codePermissions);
        $dbSlugs = array_keys($dbPermissions);

        return [
            'in_code_only' => array_diff($codeSlugs, $dbSlugs),
            'in_db_only' => array_diff($dbSlugs, $codeSlugs),
            'synced' => array_intersect($codeSlugs, $dbSlugs),
            'code_details' => $codePermissions,
            'db_details' => $dbPermissions,
            'stats' => self::getScanStats(),
        ];
    }

    /**
     * Sincroniza permisos: crea en BD los que faltan del código
     *
     * @return array Resultado de la sincronización
     */
    public static function syncPermissions(): array
    {
        $comparison = self::comparePermissions();
        $created = [];
        $errors = [];

        foreach ($comparison['in_code_only'] as $slug) {
            try {
                // Generar nombre legible desde el slug
                $name = self::generateNameFromSlug($slug);
                $category = self::guessCategoryFromSlug($slug);

                // Determinar scope basado en el tipo de controlador
                $codeDetails = $comparison['code_details'][$slug] ?? [];
                $scope = 'global';
                if (!empty($codeDetails['types'])) {
                    // Si solo está en admin/tenant, marcarlo como tenant scope
                    $types = $codeDetails['types'];
                    if (!in_array('superadmin', $types) && (in_array('admin', $types) || in_array('tenant', $types))) {
                        $scope = 'tenant';
                    }
                }

                Database::table('permissions')->insert([
                    'slug' => $slug,
                    'name' => $name,
                    'description' => "Permiso detectado automáticamente desde el código",
                    'category' => $category,
                    'tenant_id' => null,
                    'scope' => $scope,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                $created[] = $slug;
            } catch (\Exception $e) {
                $errors[$slug] = $e->getMessage();
            }
        }

        return [
            'created' => $created,
            'errors' => $errors,
            'already_synced' => $comparison['synced'],
            'orphaned_in_db' => $comparison['in_db_only'],
            'stats' => $comparison['stats'],
        ];
    }

    /**
     * Genera un nombre legible desde el slug
     *
     * @param string $slug Ej: "pages.create"
     * @return string Ej: "Crear páginas"
     */
    private static function generateNameFromSlug(string $slug): string
    {
        $parts = explode('.', $slug);

        // Traducciones comunes de acciones
        $actions = [
            'view' => 'Ver',
            'create' => 'Crear',
            'edit' => 'Editar',
            'delete' => 'Eliminar',
            'manage' => 'Gestionar',
            'reply' => 'Responder',
            'update' => 'Actualizar',
            'list' => 'Listar',
            'export' => 'Exportar',
            'import' => 'Importar',
        ];

        // Traducciones comunes de recursos
        $resources = [
            'users' => 'usuarios',
            'pages' => 'páginas',
            'posts' => 'posts',
            'media' => 'archivos multimedia',
            'settings' => 'configuración',
            'tickets' => 'tickets',
            'logs' => 'logs',
            'modules' => 'módulos',
            'languages' => 'idiomas',
            'themes' => 'temas',
            'menus' => 'menús',
            'appearance' => 'apariencia',
            'advanced' => 'avanzado',
            'dashboard' => 'panel',
            'reports' => 'reportes',
            'analytics' => 'analíticas',
            'orders' => 'pedidos',
            'products' => 'productos',
            'customers' => 'clientes',
            'invoices' => 'facturas',
        ];

        if (count($parts) === 2) {
            $resource = $resources[$parts[0]] ?? ucfirst($parts[0]);
            $action = $actions[$parts[1]] ?? ucfirst($parts[1]);
            return "{$action} {$resource}";
        }

        // Si no tiene formato recurso.accion, capitalizar
        return ucfirst(str_replace('.', ' ', $slug));
    }

    /**
     * Adivina la categoría basándose en el slug
     *
     * @param string $slug
     * @return string
     */
    private static function guessCategoryFromSlug(string $slug): string
    {
        $categoryMap = [
            'users' => 'Usuarios',
            'pages' => 'Contenido',
            'posts' => 'Blog',
            'media' => 'Media',
            'settings' => 'Configuración',
            'appearance' => 'Apariencia',
            'themes' => 'Apariencia',
            'menus' => 'Apariencia',
            'modules' => 'Sistema',
            'logs' => 'Sistema',
            'advanced' => 'Sistema',
            'languages' => 'Configuración',
            'tickets' => 'Soporte',
            'dashboard' => 'General',
            'reports' => 'Reportes',
            'analytics' => 'Reportes',
            'orders' => 'Comercio',
            'products' => 'Comercio',
            'customers' => 'Comercio',
            'invoices' => 'Comercio',
        ];

        $firstPart = explode('.', $slug)[0];
        return $categoryMap[$firstPart] ?? 'General';
    }

    /**
     * Obtiene lista simple de slugs para usar en datalist
     *
     * @return array
     */
    public static function getSuggestedSlugs(): array
    {
        $codePermissions = self::scanCodePermissions(false);
        return array_keys($codePermissions);
    }

    /**
     * Obtiene permisos agrupados por tipo de controlador
     *
     * @return array
     */
    public static function getPermissionsByType(): array
    {
        $permissions = self::scanCodePermissions();
        $grouped = [
            'superadmin' => [],
            'admin' => [],
            'tenant' => [],
            'module' => [],
        ];

        foreach ($permissions as $slug => $info) {
            foreach ($info['types'] as $type) {
                if (isset($grouped[$type])) {
                    $grouped[$type][$slug] = $info;
                }
            }
        }

        return $grouped;
    }
}
