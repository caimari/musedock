<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\Helpers\PermissionScanner;
use Screenart\Musedock\Middlewares\EnforcePermissionMiddleware;

class PermissionsController
{
    use RequiresPermission;

    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.manage');

        // Obtener permisos con información de tenant
        $permissions = Database::table('permissions')
            ->leftJoin('tenants', 'permissions.tenant_id', '=', 'tenants.id')
            ->select([
                'permissions.*',
                'tenants.name as tenant_name',
                'tenants.domain as tenant_domain'
            ])
            ->orderBy('permissions.category')
            ->get();

        // Convertir objetos a arrays para compatibilidad con la vista
        $permissions = array_map(function($perm) {
            return (array) $perm;
        }, $permissions);

        // Comparar permisos del código vs BD
        $comparison = PermissionScanner::comparePermissions();

        // Obtener métodos sin protección (auditoría de seguridad)
        $unprotectedMethods = EnforcePermissionMiddleware::getUnprotectedMethods();

        return View::renderSuperadmin('permissions.index', [
            'title' => 'Permisos del sistema',
            'permissions' => $permissions,
            'missingInDb' => $comparison['in_code_only'],
            'orphanedInDb' => $comparison['in_db_only'],
            'unprotectedMethods' => $unprotectedMethods,
        ]);
    }

    public function create()
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.manage');

        // Obtener lista de tenants para el selector
        $tenants = Database::table('tenants')->get();

        // Convertir a arrays
        $tenants = array_map(function($tenant) {
            return (array) $tenant;
        }, $tenants);

        // Obtener slugs sugeridos del código
        $suggestedSlugs = PermissionScanner::getSuggestedSlugs();

        // Obtener slugs ya existentes en BD para marcarlos
        $existingPerms = Database::table('permissions')->select(['slug'])->get();
        $existingSlugs = [];
        foreach ($existingPerms as $perm) {
            $perm = (array) $perm;
            if (!empty($perm['slug'])) {
                $existingSlugs[] = $perm['slug'];
            }
        }

        return View::renderSuperadmin('permissions.create', [
            'title' => 'Nuevo Permiso',
            'tenants' => $tenants,
            'suggestedSlugs' => $suggestedSlugs,
            'existingSlugs' => $existingSlugs,
        ]);
    }

    public function store()
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.manage');

        $slug = trim($_POST['slug'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $tenant_id = $_POST['tenant_id'] ?? null;

        // Validar campos obligatorios
        if (!$slug || !$name) {
            flash('error', 'El slug y el nombre son obligatorios.');
            header('Location: /musedock/permissions/create');
            exit;
        }

        // Validar formato del slug (solo minúsculas, números y puntos)
        if (!preg_match('/^[a-z0-9]+(\.[a-z0-9]+)*$/', $slug)) {
            flash('error', 'El slug debe usar formato recurso.acción (solo minúsculas, números y puntos).');
            header('Location: /musedock/permissions/create');
            exit;
        }

        // Verificar que el slug no esté repetido (es el identificador único)
        $exists = Database::table('permissions')->where('slug', $slug)->first();
        if ($exists) {
            flash('error', 'Ya existe un permiso con ese slug.');
            header('Location: /musedock/permissions/create');
            exit;
        }

        // Asegurar que tenant_id es NULL si está vacío
        $tenant_id = ($tenant_id === '' || $tenant_id === 'global') ? null : $tenant_id;

        Database::table('permissions')->insert([
            'slug' => $slug,
            'name' => $name,
            'description' => $description ?: null,
            'category' => $category ?: null,
            'tenant_id' => $tenant_id,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'scope' => $tenant_id ? 'tenant' : 'global'
        ]);

        flash('success', 'Permiso creado correctamente.');
        header('Location: /musedock/permissions');
        exit;
    }

    public function edit($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.manage');

        $permission = Database::table('permissions')->where('id', $id)->first();

        if (!$permission) {
            flash('error', 'Permiso no encontrado.');
            header('Location: /musedock/permissions');
            exit;
        }
        
        // Obtener lista de tenants para el selector
        $tenants = Database::table('tenants')->get();
        
        // Convertir a arrays
        $permission = (array) $permission;
        $tenants = array_map(function($tenant) {
            return (array) $tenant;
        }, $tenants);

        return View::renderSuperadmin('permissions.edit', [
            'title' => 'Editar Permiso',
            'permission' => $permission,
            'tenants' => $tenants
        ]);
    }

    public function update($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.manage');

        $permission = Database::table('permissions')->where('id', $id)->first();

        if (!$permission) {
            flash('error', 'Permiso no encontrado.');
            header('Location: /musedock/permissions');
            exit;
        }

        $slug = trim($_POST['slug'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $tenant_id = $_POST['tenant_id'] ?? null;

        // Validar campos obligatorios
        if (!$slug || !$name) {
            flash('error', 'El slug y el nombre son obligatorios.');
            header("Location: /musedock/permissions/{$id}/edit");
            exit;
        }

        // Validar formato del slug (solo minúsculas, números y puntos)
        if (!preg_match('/^[a-z0-9]+(\.[a-z0-9]+)*$/', $slug)) {
            flash('error', 'El slug debe usar formato recurso.acción (solo minúsculas, números y puntos).');
            header("Location: /musedock/permissions/{$id}/edit");
            exit;
        }

        // Verificar que el slug no esté repetido (excluyendo el registro actual)
        $exists = Database::table('permissions')
            ->where('slug', $slug)
            ->where('id', '!=', $id)
            ->first();
        if ($exists) {
            flash('error', 'Ya existe otro permiso con ese slug.');
            header("Location: /musedock/permissions/{$id}/edit");
            exit;
        }

        // Asegurar que tenant_id es NULL si está vacío
        $tenant_id = ($tenant_id === '' || $tenant_id === 'global') ? null : $tenant_id;

        Database::table('permissions')->where('id', $id)->update([
            'slug' => $slug,
            'name' => $name,
            'description' => $description ?: null,
            'category' => $category ?: null,
            'tenant_id' => $tenant_id,
            'updated_at' => date('Y-m-d H:i:s'),
            'scope' => $tenant_id ? 'tenant' : 'global'
        ]);

        flash('success', 'Permiso actualizado correctamente.');
        header('Location: /musedock/permissions');
        exit;
    }
    
    public function destroy($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.manage');

        $permission = Database::table('permissions')->where('id', $id)->first();

        if (!$permission) {
            flash('error', 'Permiso no encontrado.');
            header('Location: /musedock/permissions');
            exit;
        }

        try {
            // Eliminar relaciones del permiso con roles
            Database::table('role_permissions')->where('permission_id', $id)->delete();

            // Eliminar el permiso
            Database::table('permissions')->where('id', $id)->delete();

            flash('success', 'Permiso eliminado correctamente.');
        } catch (\Exception $e) {
            \Screenart\Musedock\Logger::error("Error al eliminar permiso: " . $e->getMessage());
            flash('error', 'Error al eliminar el permiso.');
        }

        header('Location: /musedock/permissions');
        exit;
    }

    /**
     * Sincroniza permisos del código con la BD
     * Crea automáticamente los permisos que faltan
     */
    public function sync()
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.manage');

        $result = PermissionScanner::syncPermissions();

        if (!empty($result['created'])) {
            $count = count($result['created']);
            $slugs = implode(', ', $result['created']);
            flash('success', "Se crearon {$count} permiso(s): {$slugs}");
        } else {
            flash('info', 'Todos los permisos del código ya están en la base de datos.');
        }

        if (!empty($result['errors'])) {
            $errorMsg = implode(', ', array_keys($result['errors']));
            flash('warning', "Error al crear: {$errorMsg}");
        }

        header('Location: /musedock/permissions');
        exit;
    }

    /**
     * Limpia permisos duplicados en la BD
     * Mantiene solo el más reciente de cada slug
     */
    public function cleanupDuplicates()
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.manage');

        try {
            // Obtener todos los permisos ordenados por ID desc (más reciente primero)
            $permissions = Database::table('permissions')
                ->orderBy('id', 'desc')
                ->get();

            $seenSlugs = [];
            $deletedCount = 0;

            foreach ($permissions as $perm) {
                $perm = (array) $perm;
                $slug = $perm['slug'] ?? '';

                if (empty($slug)) {
                    continue;
                }

                if (isset($seenSlugs[$slug])) {
                    // Este es un duplicado, eliminarlo
                    Database::table('role_permissions')
                        ->where('permission_id', $perm['id'])
                        ->delete();
                    Database::table('permissions')
                        ->where('id', $perm['id'])
                        ->delete();
                    $deletedCount++;
                } else {
                    $seenSlugs[$slug] = $perm['id'];
                }
            }

            if ($deletedCount > 0) {
                flash('success', "Se eliminaron {$deletedCount} permiso(s) duplicado(s).");
            } else {
                flash('info', 'No se encontraron permisos duplicados.');
            }
        } catch (\Exception $e) {
            \Screenart\Musedock\Logger::error("Error al limpiar duplicados: " . $e->getMessage());
            flash('error', 'Error al limpiar permisos duplicados.');
        }

        header('Location: /musedock/permissions');
        exit;
    }

    /**
     * Limpia permisos huérfanos (en BD pero no en código)
     */
    public function cleanupOrphans()
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.manage');

        try {
            // Obtener slugs del código
            $codePermissions = PermissionScanner::scanCodePermissions();
            $codeSlugs = array_keys($codePermissions);

            // Obtener permisos de la BD
            $dbPermissions = Database::table('permissions')->get();

            $deletedCount = 0;
            $deletedSlugs = [];

            foreach ($dbPermissions as $perm) {
                $perm = (array) $perm;
                $slug = $perm['slug'] ?? '';

                if (empty($slug) || in_array($slug, $codeSlugs)) {
                    continue;
                }

                // Este permiso no está en el código, eliminarlo
                Database::table('role_permissions')
                    ->where('permission_id', $perm['id'])
                    ->delete();
                Database::table('permissions')
                    ->where('id', $perm['id'])
                    ->delete();
                $deletedCount++;
                if (!in_array($slug, $deletedSlugs)) {
                    $deletedSlugs[] = $slug;
                }
            }

            if ($deletedCount > 0) {
                $slugList = implode(', ', array_slice($deletedSlugs, 0, 10));
                if (count($deletedSlugs) > 10) {
                    $slugList .= '...';
                }
                flash('success', "Se eliminaron {$deletedCount} permiso(s) huérfano(s): {$slugList}");
            } else {
                flash('info', 'No se encontraron permisos huérfanos.');
            }
        } catch (\Exception $e) {
            \Screenart\Musedock\Logger::error("Error al limpiar huérfanos: " . $e->getMessage());
            flash('error', 'Error al limpiar permisos huérfanos.');
        }

        header('Location: /musedock/permissions');
        exit;
    }

    /**
     * REGENERA todos los permisos desde cero
     *
     * Elimina todos los permisos globales y los recrea escaneando el código.
     * Esto corrige el scope (superadmin/tenant) basado en los controladores.
     *
     * ADVERTENCIA: Elimina asignaciones de permisos a roles.
     */
    public function regenerate()
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.manage');

        try {
            $result = PermissionScanner::regenerateAllPermissions();

            $createdCount = count($result['created']);
            $deletedCount = $result['deleted'];
            $superadminCount = $result['by_scope']['superadmin'] ?? 0;
            $tenantCount = $result['by_scope']['tenant'] ?? 0;

            if (!empty($result['errors'])) {
                $errorCount = count($result['errors']);
                flash('warning', "Regeneración completada con {$errorCount} error(es). Eliminados: {$deletedCount}, Creados: {$createdCount} (Superadmin: {$superadminCount}, Tenant: {$tenantCount})");
            } else {
                flash('success', "Permisos regenerados correctamente. Eliminados: {$deletedCount}, Creados: {$createdCount} (Superadmin: {$superadminCount}, Tenant: {$tenantCount})");
            }

        } catch (\Exception $e) {
            \Screenart\Musedock\Logger::error("Error al regenerar permisos: " . $e->getMessage());
            flash('error', 'Error al regenerar permisos: ' . $e->getMessage());
        }

        header('Location: /musedock/permissions');
        exit;
    }
}
