<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Traits\RequiresPermission;

class LanguagesController
{
    use RequiresPermission;

    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('languages.manage');

        $languages = Database::table('languages')
            ->select('languages.*, tenants.name as tenant_name, tenants.domain as tenant_domain')
            ->leftJoin('tenants', 'languages.tenant_id', '=', 'tenants.id')
            ->orderBy('languages.code')
            ->get();

        return View::renderSuperadmin('languages.index', [
            'title' => 'Gestión de Idiomas',
            'languages' => $languages
        ]);
    }

    public function create()
    {
        SessionSecurity::startSession();
        $this->checkPermission('languages.manage');

        // Obtener todos los tenants
        $tenants = Database::table('tenants')->get();

        return View::renderSuperadmin('languages.create', [
            'title' => 'Añadir Idioma',
            'tenants' => $tenants
        ]);
    }

    public function store()
    {
        SessionSecurity::startSession();
        $this->checkPermission('languages.manage');

        $data = $_POST;
        unset($data['_token'], $data['_csrf']);

        // Convertir tenant_id vacío o "global" a NULL
        if (empty($data['tenant_id']) || $data['tenant_id'] === 'global') {
            $data['tenant_id'] = null;
        }

        Database::table('languages')->insert($data);
        flash('success', 'Idioma añadido correctamente.');
        header('Location: /musedock/languages');
        exit;
    }

    public function edit($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('languages.manage');

        $language = Database::table('languages')->where('id', $id)->first();
        $tenants = Database::table('tenants')->get();

        return View::renderSuperadmin('languages.edit', [
            'title' => 'Editar idioma',
            'language' => $language,
            'tenants' => $tenants
        ]);
    }

    public function update($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('languages.manage');

        $data = $_POST;
        unset($data['_token'], $data['_csrf']);

        // Manejar checkbox 'active'
        if (!isset($data['active'])) {
            $data['active'] = 0;
        }

        // Convertir tenant_id vacío o "global" a NULL
        if (empty($data['tenant_id']) || $data['tenant_id'] === 'global') {
            $data['tenant_id'] = null;
        }

        Database::table('languages')
            ->where('id', $id)
            ->update($data);

        flash('success', 'Idioma actualizado.');
        header('Location: /musedock/languages');
        exit;
    }

    public function delete($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('languages.manage');

        Database::table('languages')->where('id', $id)->delete();
        flash('success', 'Idioma eliminado.');
        header('Location: /musedock/languages');
        exit;
    }

    public function toggle($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('languages.manage');

        $lang = Database::table('languages')->where('id', $id)->first();
        $newStatus = ($lang->active ?? 0) ? 0 : 1;

        Database::table('languages')
            ->where('id', $id)
            ->update(['active' => $newStatus]);

        flash('success', 'Idioma actualizado.');
        header('Location: /musedock/languages');
        exit;
    }
}
