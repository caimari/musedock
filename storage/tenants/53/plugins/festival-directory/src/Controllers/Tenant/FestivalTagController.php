<?php

namespace FestivalDirectory\Controllers\Tenant;

use FestivalDirectory\Models\FestivalTag;
use Screenart\Musedock\Services\TenantManager;

class FestivalTagController
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

        $tags = FestivalTag::where('tenant_id', $tenantId)->orderBy('name', 'ASC')->get();

        echo festival_render_admin('tenant.tags.index', [
            'title' => 'Tags',
            'tags'  => $tags,
        ]);
    }

    public function create()
    {
        echo festival_render_admin('tenant.tags.create', [
            'title' => 'Crear Tag',
            'tag'   => new FestivalTag(),
            'isNew' => true,
        ]);
    }

    public function store()
    {
        $tenantId = $this->getTenantId();
        $data = $_POST;

        if (empty(trim($data['name'] ?? ''))) {
            flash('error', 'El nombre del tag es obligatorio.');
            header('Location: ' . festival_admin_url('tags/create'));
            exit;
        }

        $data['tenant_id'] = $tenantId;
        if (empty($data['slug'])) {
            $data['slug'] = festival_slugify($data['name']);
        }

        $existing = FestivalTag::where('tenant_id', $tenantId)->where('slug', $data['slug'])->first();
        if ($existing) {
            $data['slug'] = $data['slug'] . '-' . time();
        }

        unset($data['_token'], $data['_method']);
        FestivalTag::create($data);

        flash('success', 'Tag creado correctamente.');
        header('Location: ' . festival_admin_url('tags'));
        exit;
    }

    public function edit($id)
    {
        $tenantId = $this->getTenantId();
        $tag = FestivalTag::where('id', $id)->where('tenant_id', $tenantId)->first();

        if (!$tag) {
            flash('error', 'No encontrado.');
            header('Location: ' . festival_admin_url('tags'));
            exit;
        }

        if (is_array($tag) || $tag instanceof \stdClass) {
            $tag = new FestivalTag($tag);
        }

        echo festival_render_admin('tenant.tags.edit', [
            'title' => 'Editar Tag',
            'tag'   => $tag,
            'isNew' => false,
        ]);
    }

    public function update($id)
    {
        $tenantId = $this->getTenantId();
        $tag = FestivalTag::where('id', $id)->where('tenant_id', $tenantId)->first();

        if (!$tag) {
            flash('error', 'No encontrado.');
            header('Location: ' . festival_admin_url('tags'));
            exit;
        }

        if (is_array($tag) || $tag instanceof \stdClass) {
            $tag = new FestivalTag($tag);
        }

        $data = $_POST;
        if (empty(trim($data['name'] ?? ''))) {
            flash('error', 'El nombre del tag es obligatorio.');
            header('Location: ' . festival_admin_url('tags/' . $id . '/edit'));
            exit;
        }

        unset($data['_token'], $data['_method']);
        $tag->fill($data);
        $tag->save();

        flash('success', 'Tag actualizado correctamente.');
        header('Location: ' . festival_admin_url('tags'));
        exit;
    }

    public function destroy($id)
    {
        $tenantId = $this->getTenantId();
        $tag = FestivalTag::where('id', $id)->where('tenant_id', $tenantId)->first();

        if ($tag) {
            if (is_array($tag) || $tag instanceof \stdClass) {
                $tag = new FestivalTag($tag);
            }
            $tag->delete();
        }

        flash('success', 'Tag eliminado correctamente.');
        header('Location: ' . festival_admin_url('tags'));
        exit;
    }
}
