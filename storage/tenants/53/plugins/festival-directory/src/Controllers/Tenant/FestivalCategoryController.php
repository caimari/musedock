<?php

namespace FestivalDirectory\Controllers\Tenant;

use FestivalDirectory\Models\FestivalCategory;
use Screenart\Musedock\Services\TenantManager;
use Screenart\Musedock\Services\AuditLogger;

class FestivalCategoryController
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

        $categories = FestivalCategory::where('tenant_id', $tenantId)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->get();

        echo festival_render_admin('tenant.categories.index', [
            'title'      => 'Categorías',
            'categories' => $categories,
        ]);
    }

    public function create()
    {
        $tenantId = $this->getTenantId();

        echo festival_render_admin('tenant.categories.create', [
            'title'    => 'Crear Categoría',
            'category' => new FestivalCategory(),
            'isNew'    => true,
        ]);
    }

    public function store()
    {
        $tenantId = $this->getTenantId();
        $data = $_POST;

        if (empty(trim($data['name'] ?? ''))) {
            flash('error', 'El nombre de la categoría es obligatorio.');
            header('Location: ' . festival_admin_url('categories/create'));
            exit;
        }

        $data['tenant_id'] = $tenantId;
        if (empty($data['slug'])) {
            $data['slug'] = festival_slugify($data['name']);
        }

        // Check uniqueness
        $existing = FestivalCategory::where('tenant_id', $tenantId)->where('slug', $data['slug'])->first();
        if ($existing) {
            $data['slug'] = $data['slug'] . '-' . time();
        }

        unset($data['_token'], $data['_method']);

        FestivalCategory::create($data);
        flash('success', 'Categoría creada correctamente.');
        header('Location: ' . festival_admin_url('categories'));
        exit;
    }

    public function edit($id)
    {
        $tenantId = $this->getTenantId();
        $category = FestivalCategory::where('id', $id)->where('tenant_id', $tenantId)->first();

        if (!$category) {
            flash('error', 'No encontrado.');
            header('Location: ' . festival_admin_url('categories'));
            exit;
        }

        if (is_array($category) || $category instanceof \stdClass) {
            $category = new FestivalCategory($category);
        }

        echo festival_render_admin('tenant.categories.edit', [
            'title'    => 'Editar Categoría',
            'category' => $category,
            'isNew'    => false,
        ]);
    }

    public function update($id)
    {
        $tenantId = $this->getTenantId();
        $category = FestivalCategory::where('id', $id)->where('tenant_id', $tenantId)->first();

        if (!$category) {
            flash('error', 'No encontrado.');
            header('Location: ' . festival_admin_url('categories'));
            exit;
        }

        if (is_array($category) || $category instanceof \stdClass) {
            $category = new FestivalCategory($category);
        }

        $data = $_POST;
        if (empty(trim($data['name'] ?? ''))) {
            flash('error', 'El nombre de la categoría es obligatorio.');
            header('Location: ' . festival_admin_url('categories/' . $id . '/edit'));
            exit;
        }

        unset($data['_token'], $data['_method']);
        $data['updated_at'] = date('Y-m-d H:i:s');

        $category->fill($data);
        $category->save();

        flash('success', 'Categoría actualizada correctamente.');
        header('Location: ' . festival_admin_url('categories'));
        exit;
    }

    public function destroy($id)
    {
        $tenantId = $this->getTenantId();
        $category = FestivalCategory::where('id', $id)->where('tenant_id', $tenantId)->first();

        if ($category) {
            if (is_array($category) || $category instanceof \stdClass) {
                $category = new FestivalCategory($category);
            }
            $category->delete();
        }

        flash('success', 'Categoría eliminada correctamente.');
        header('Location: ' . festival_admin_url('categories'));
        exit;
    }
}
