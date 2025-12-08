<?php

namespace ImageGallery\Controllers\Tenant;

use Screenart\Musedock\View;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Services\TenantManager;
use ImageGallery\Models\Gallery;
use ImageGallery\Models\GalleryImage;
use ImageGallery\Models\GallerySetting;

/**
 * GalleryController - Tenant
 *
 * Gestión de galerías específicas del tenant
 */
class GalleryController
{
    /**
     * Lista las galerías del tenant
     */
    public function index()
    {
        SessionSecurity::startSession();
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __gallery('gallery.tenant_required'));
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }

        $galleries = Gallery::getByTenant($tenantId, true);

        return View::renderModule('image-gallery', 'tenant/galleries/index', [
            'title' => __gallery('gallery.my_galleries'),
            'galleries' => $galleries,
            'layouts' => Gallery::getAvailableLayouts()
        ]);
    }

    /**
     * Formulario de creación
     */
    public function create()
    {
        SessionSecurity::startSession();
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __gallery('gallery.tenant_required'));
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }

        return View::renderModule('image-gallery', 'tenant/galleries/create', [
            'title' => __gallery('gallery.create'),
            'layouts' => Gallery::getAvailableLayouts(),
            'settings' => GallerySetting::getAll($tenantId)
        ]);
    }

    /**
     * Almacena una nueva galería
     */
    public function store()
    {
        SessionSecurity::startSession();
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __gallery('gallery.tenant_required'));
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }

        // Validación
        $errors = $this->validateGallery($_POST, $tenantId);

        if (!empty($errors)) {
            flash('error', implode('<br>', $errors));
            $_SESSION['_old_input'] = $_POST;
            header('Location: ' . route('tenant.image-gallery.create'));
            exit;
        }

        // Generar slug único aunque se proporcione manualmente
        $baseSlugInput = trim($_POST['slug'] ?? '');
        $slugSource = $baseSlugInput !== '' ? $baseSlugInput : ($_POST['name'] ?? '');
        $slug = Gallery::generateUniqueSlug($slugSource, $tenantId);

        // Parsear settings
        $settings = [];
        if (!empty($_POST['settings'])) {
            $settings = is_array($_POST['settings'])
                ? $_POST['settings']
                : json_decode($_POST['settings'], true) ?? [];
        }

        // Crear galería
        $gallery = Gallery::create([
            'tenant_id' => $tenantId,
            'name' => trim($_POST['name']),
            'slug' => $slug,
            'description' => trim($_POST['description'] ?? ''),
            'layout_type' => $_POST['layout_type'] ?? 'grid',
            'columns' => (int) ($_POST['columns'] ?? 3),
            'gap' => (int) ($_POST['gap'] ?? 10),
            'settings' => $settings,
            'is_active' => isset($_POST['is_active']),
            'featured' => isset($_POST['featured']),
            'sort_order' => (int) ($_POST['sort_order'] ?? 0)
        ]);

        if ($gallery) {
            flash('success', __gallery('gallery.created'));
            header('Location: ' . route('tenant.image-gallery.edit', ['id' => $gallery->id]));
        } else {
            flash('error', __gallery('gallery.error_creating'));
            $_SESSION['_old_input'] = $_POST;
            header('Location: ' . route('tenant.image-gallery.create'));
        }
        exit;
    }

    /**
     * Formulario de edición
     */
    public function edit($id)
    {
        SessionSecurity::startSession();
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __gallery('gallery.tenant_required'));
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }

        $gallery = Gallery::find((int) $id);

        // Verificar que la galería pertenece al tenant o es global
        if (!$gallery || ($gallery->tenant_id !== null && $gallery->tenant_id !== $tenantId)) {
            flash('error', __gallery('gallery.not_found'));
            header('Location: ' . route('tenant.image-gallery.index'));
            exit;
        }

        $images = $gallery->images();
        $isOwner = $gallery->tenant_id === $tenantId;

        return View::renderModule('image-gallery', 'tenant/galleries/edit', [
            'title' => __gallery('gallery.edit') . ': ' . $gallery->name,
            'gallery' => $gallery,
            'images' => $images,
            'layouts' => Gallery::getAvailableLayouts(),
            'settings' => GallerySetting::getAll($tenantId),
            'isOwner' => $isOwner,
            'canEdit' => $isOwner
        ]);
    }

    /**
     * Actualiza una galería
     */
    public function update($id)
    {
        SessionSecurity::startSession();
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __gallery('gallery.tenant_required'));
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }

        $gallery = Gallery::find((int) $id);

        // Solo puede editar galerías propias (no globales)
        if (!$gallery || $gallery->tenant_id !== $tenantId) {
            flash('error', __gallery('gallery.not_found'));
            header('Location: ' . route('tenant.image-gallery.index'));
            exit;
        }

        // Validación
        $errors = $this->validateGallery($_POST, $tenantId, $gallery->id);

        if (!empty($errors)) {
            flash('error', implode('<br>', $errors));
            $_SESSION['_old_input'] = $_POST;
            header('Location: ' . route('tenant.image-gallery.edit', ['id' => $id]));
            exit;
        }

        // Parsear settings
        $settings = [];
        if (!empty($_POST['settings'])) {
            $settings = is_array($_POST['settings'])
                ? $_POST['settings']
                : json_decode($_POST['settings'], true) ?? [];
        }

        // Determinar slug final evitando duplicados
        $incomingSlug = trim($_POST['slug'] ?? '');
        $slug = $incomingSlug !== ''
            ? Gallery::generateUniqueSlug($incomingSlug, $tenantId, $gallery->id)
            : $gallery->slug;

        // Actualizar
        $gallery->update([
            'name' => trim($_POST['name']),
            'slug' => $slug,
            'description' => trim($_POST['description'] ?? ''),
            'layout_type' => $_POST['layout_type'] ?? 'grid',
            'columns' => (int) ($_POST['columns'] ?? 3),
            'gap' => (int) ($_POST['gap'] ?? 10),
            'settings' => $settings,
            'is_active' => isset($_POST['is_active']),
            'featured' => isset($_POST['featured']),
            'sort_order' => (int) ($_POST['sort_order'] ?? 0)
        ]);

        flash('success', __gallery('gallery.updated'));
        header('Location: ' . route('tenant.image-gallery.edit', ['id' => $id]));
        exit;
    }

    /**
     * Elimina una galería
     */
    public function destroy($id)
    {
        SessionSecurity::startSession();
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __gallery('gallery.tenant_required'));
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }

        $gallery = Gallery::find((int) $id);

        // Solo puede eliminar galerías propias
        if (!$gallery || $gallery->tenant_id !== $tenantId) {
            flash('error', __gallery('gallery.not_found'));
            header('Location: ' . route('tenant.image-gallery.index'));
            exit;
        }

        // Eliminar imágenes físicas
        $images = $gallery->images();
        foreach ($images as $image) {
            $image->deleteWithFile();
        }

        // Eliminar galería
        $gallery->delete();

        flash('success', __gallery('gallery.deleted'));
        header('Location: ' . route('tenant.image-gallery.index'));
        exit;
    }

    /**
     * Selector de galerías para el editor (AJAX)
     */
    public function selector()
    {
        SessionSecurity::startSession();
        $tenantId = TenantManager::currentTenantId();

        header('Content-Type: application/json');

        $galleries = Gallery::getActive($tenantId);

        $data = array_map(function ($gallery) {
            return [
                'id' => $gallery->id,
                'name' => $gallery->name,
                'slug' => $gallery->slug,
                'thumbnail' => $gallery->thumbnail_url,
                'image_count' => $gallery->imageCount(),
                'shortcode' => '[gallery id=' . $gallery->id . ']',
                'shortcode_slug' => '[gallery slug="' . $gallery->slug . '"]'
            ];
        }, $galleries);

        echo json_encode([
            'success' => true,
            'galleries' => $data
        ]);
        exit;
    }

    /**
     * Valida los datos de la galería
     */
    private function validateGallery(array $data, int $tenantId, ?int $excludeId = null): array
    {
        $errors = [];

        // Nombre requerido
        if (empty($data['name'])) {
            $errors[] = __gallery('validation.name_required');
        } elseif (strlen($data['name']) > 255) {
            $errors[] = __gallery('validation.name_too_long');
        }

        // Validar slug si se proporciona
        if (!empty($data['slug'])) {
            if (!preg_match('/^[a-z0-9\-]+$/', $data['slug'])) {
                $errors[] = __gallery('validation.slug_invalid');
            } elseif (Gallery::slugExists($data['slug'], $tenantId, $excludeId)) {
                $errors[] = __gallery('validation.slug_exists');
            }
        }

        // Validar layout
        $validLayouts = array_keys(Gallery::getAvailableLayouts());
        if (!empty($data['layout_type']) && !in_array($data['layout_type'], $validLayouts)) {
            $errors[] = __gallery('validation.layout_invalid');
        }

        // Validar columnas
        if (isset($data['columns'])) {
            $columns = (int) $data['columns'];
            if ($columns < 1 || $columns > 6) {
                $errors[] = __gallery('validation.columns_range');
            }
        }

        return $errors;
    }
}
