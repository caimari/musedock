<?php

namespace FestivalDirectory\Controllers\Tenant;

use FestivalDirectory\Models\FestivalType;
use Screenart\Musedock\Services\TenantManager;

class FestivalTypeController
{
    private function getTenantId(): int
    {
        $tenantId = TenantManager::currentTenantId();
        if ($tenantId === null) {
            flash('error', 'Tenant no identificado.');
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }
        return $tenantId;
    }

    public function index()
    {
        $tenantId = $this->getTenantId();

        $types = FestivalType::where('tenant_id', $tenantId)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->get();

        echo festival_render_admin('tenant.types.index', [
            'title' => 'Tipos de Festival',
            'types' => $types,
        ]);
    }

    public function create()
    {
        echo festival_render_admin('tenant.types.create', [
            'title' => 'Crear Tipo',
            'type'  => new FestivalType(),
            'isNew' => true,
        ]);
    }

    public function store()
    {
        $tenantId = $this->getTenantId();
        $data = $_POST;

        if (empty(trim($data['name'] ?? ''))) {
            flash('error', 'El nombre del tipo es obligatorio.');
            header('Location: ' . festival_admin_url('types/create'));
            exit;
        }

        $data['tenant_id'] = $tenantId;
        if (empty($data['slug'])) {
            $data['slug'] = festival_slugify($data['name']);
        }

        $existing = FestivalType::where('tenant_id', $tenantId)->where('slug', $data['slug'])->first();
        if ($existing) {
            $data['slug'] = $data['slug'] . '-' . time();
        }

        unset($data['_token'], $data['_method']);
        FestivalType::create($data);

        flash('success', 'Tipo creado correctamente.');
        header('Location: ' . festival_admin_url('types'));
        exit;
    }

    public function edit($id)
    {
        $tenantId = $this->getTenantId();
        $type = FestivalType::where('id', $id)->where('tenant_id', $tenantId)->first();

        if (!$type) {
            flash('error', 'Tipo no encontrado.');
            header('Location: ' . festival_admin_url('types'));
            exit;
        }

        if (is_array($type) || $type instanceof \stdClass) {
            $type = new FestivalType($type);
        }

        echo festival_render_admin('tenant.types.edit', [
            'title' => 'Editar Tipo',
            'type'  => $type,
            'isNew' => false,
        ]);
    }

    public function update($id)
    {
        $tenantId = $this->getTenantId();
        $type = FestivalType::where('id', $id)->where('tenant_id', $tenantId)->first();

        if (!$type) {
            flash('error', 'Tipo no encontrado.');
            header('Location: ' . festival_admin_url('types'));
            exit;
        }

        if (is_array($type) || $type instanceof \stdClass) {
            $type = new FestivalType($type);
        }

        $data = $_POST;
        if (empty(trim($data['name'] ?? ''))) {
            flash('error', 'El nombre del tipo es obligatorio.');
            header('Location: ' . festival_admin_url('types/' . $id . '/edit'));
            exit;
        }

        unset($data['_token'], $data['_method']);
        $type->fill($data);
        $type->save();

        flash('success', 'Tipo actualizado correctamente.');
        header('Location: ' . festival_admin_url('types'));
        exit;
    }

    public function destroy($id)
    {
        $tenantId = $this->getTenantId();
        $type = FestivalType::where('id', $id)->where('tenant_id', $tenantId)->first();

        if ($type) {
            if (is_array($type) || $type instanceof \stdClass) {
                $type = new FestivalType($type);
            }
            $type->delete();
        }

        flash('success', 'Tipo eliminado correctamente.');
        header('Location: ' . festival_admin_url('types'));
        exit;
    }
}
