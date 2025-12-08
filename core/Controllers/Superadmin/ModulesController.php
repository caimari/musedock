<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\Database;
use Screenart\Musedock\View;
use Screenart\Musedock\Middlewares\DynamicPermissionMiddleware;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\Security\SessionSecurity;

class ModulesController
{
    use RequiresPermission;

    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

        // Protección dinámica según permisos
        DynamicPermissionMiddleware::handle();

        $modules = Database::query("SELECT * FROM modules ORDER BY name")->fetchAll();
        $tenants = Database::query("SELECT id, name FROM tenants")->fetchAll();

        foreach ($modules as &$module) {
            $module['tenants'] = [];

            $assigned = Database::query("
                SELECT tenant_id FROM tenant_modules
                WHERE module_id = :id AND enabled = 1
            ", ['id' => $module['id']])->fetchAll();

            $module['tenants'] = array_column($assigned, 'tenant_id');
        }

        return View::renderSuperadmin('modules.index', [
            'modules' => $modules,
            'tenants' => $tenants,
            'title'   => 'Gestión de módulos'
        ]);
    }

    public function toggle($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

        // Protección dinámica
        DynamicPermissionMiddleware::handle();

        $module = Database::table('modules')->where('id', $id)->first();

        if (!$module) {
            http_response_code(404);
            echo "Módulo no encontrado";
            exit;
        }

        $newState = $module->active ? 0 : 1;

        Database::table('modules')->where('id', $id)->update([
            'active' => $newState
        ]);

        header("Location: /musedock/modules");
        exit;
    }

    public function toggleTenant($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

        // Protección dinámica
        DynamicPermissionMiddleware::handle();

        $tenantId = (int)($_POST['tenant_id'] ?? 0);

        if (!$tenantId) {
            http_response_code(400);
            echo "Tenant inválido";
            exit;
        }

        $exists = Database::table('tenant_modules')
            ->where('tenant_id', $tenantId)
            ->where('module_id', $id)
            ->first();

        if ($exists) {
            Database::table('tenant_modules')
                ->where('tenant_id', $tenantId)
                ->where('module_id', $id)
                ->delete();
        } else {
            Database::table('tenant_modules')->insert([
                'tenant_id' => $tenantId,
                'module_id' => $id,
                'enabled'   => 1
            ]);
        }

        header("Location: /musedock/modules");
        exit;
    }

    public function toggleCms($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('modules.manage');

        // Protección dinámica
        DynamicPermissionMiddleware::handle();

        $module = Database::table('modules')->where('id', $id)->first();

        if (!$module) {
            http_response_code(404);
            echo "Módulo no encontrado";
            exit;
        }

        $newState = $module->cms_enabled ? 0 : 1;


        Database::table('modules')->where('id', $id)->update([
            'cms_enabled' => $newState
        ]);

        header("Location: /musedock/modules");
        exit;
    }
}
