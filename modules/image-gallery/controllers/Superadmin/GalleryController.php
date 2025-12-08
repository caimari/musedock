<?php

namespace ImageGallery\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Traits\RequiresPermission;
use ImageGallery\Models\Gallery;
use ImageGallery\Models\GalleryImage;
use ImageGallery\Models\GallerySetting;

/**
 * GalleryController - Superadmin
 *
 * Gestión de galerías globales desde el panel de superadmin
 */
class GalleryController
{
    use RequiresPermission;

    /**
     * Lista todas las galerías globales
     */
    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('image_gallery.view');

        $galleries = Gallery::getByTenant(null, false);

        return View::renderModule('image-gallery', 'superadmin/galleries/index', [
            'title' => __gallery('gallery.galleries'),
            'galleries' => $galleries,
            'layouts' => Gallery::getAvailableLayouts()
        ]);
    }

    /**
     * Formulario de creación de galería
     */
    public function create()
    {
        SessionSecurity::startSession();
        $this->checkPermission('image_gallery.create');

        return View::renderModule('image-gallery', 'superadmin/galleries/create', [
            'title' => __gallery('gallery.create'),
            'layouts' => Gallery::getAvailableLayouts(),
            'settings' => GallerySetting::getAll()
        ]);
    }

    /**
     * Almacena una nueva galería
     */
    public function store()
    {
        SessionSecurity::startSession();
        $this->checkPermission('image_gallery.create');

        // Validación
        $errors = $this->validateGallery($_POST);

        if (!empty($errors)) {
            flash('error', implode('<br>', $errors));
            $_SESSION['_old_input'] = $_POST;
            header('Location: ' . route('image-gallery.create'));
            exit;
        }

        // Generar slug único (aunque el usuario ingrese uno manualmente)
        $baseSlugInput = trim($_POST['slug'] ?? '');
        $slugSource = $baseSlugInput !== '' ? $baseSlugInput : ($_POST['name'] ?? '');
        $slug = Gallery::generateUniqueSlug($slugSource, null);

        // Parsear settings
        $settings = [];
        if (!empty($_POST['settings'])) {
            $settings = is_array($_POST['settings'])
                ? $_POST['settings']
                : json_decode($_POST['settings'], true) ?? [];
        }

        // Crear galería
        $gallery = Gallery::create([
            'tenant_id' => null, // Galería global
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
            header('Location: ' . route('image-gallery.edit', ['id' => $gallery->id]));
        } else {
            flash('error', __gallery('gallery.error_creating'));
            $_SESSION['_old_input'] = $_POST;
            header('Location: ' . route('image-gallery.create'));
        }
        exit;
    }

    /**
     * Formulario de edición de galería
     */
    public function edit($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('image_gallery.edit');

        $gallery = Gallery::find((int) $id);

        // Verificar que es una galería global (tenant_id = null o 0)
        if (!$gallery || ($gallery->tenant_id !== null && $gallery->tenant_id !== 0)) {
            flash('error', __gallery('gallery.not_found'));
            header('Location: ' . route('image-gallery.index'));
            exit;
        }

        $images = $gallery->images();

        return View::renderModule('image-gallery', 'superadmin/galleries/edit', [
            'title' => __gallery('gallery.edit') . ': ' . $gallery->name,
            'gallery' => $gallery,
            'images' => $images,
            'layouts' => Gallery::getAvailableLayouts(),
            'settings' => GallerySetting::getAll()
        ]);
    }

    /**
     * Actualiza una galería
     */
    public function update($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('image_gallery.edit');

        $gallery = Gallery::find((int) $id);

        // Verificar que es una galería global (tenant_id = null o 0)
        if (!$gallery || ($gallery->tenant_id !== null && $gallery->tenant_id !== 0)) {
            flash('error', __gallery('gallery.not_found'));
            header('Location: ' . route('image-gallery.index'));
            exit;
        }

        // Validación
        $errors = $this->validateGallery($_POST, $gallery->id);

        if (!empty($errors)) {
            flash('error', implode('<br>', $errors));
            $_SESSION['_old_input'] = $_POST;
            header('Location: ' . route('image-gallery.edit', ['id' => $id]));
            exit;
        }

        // Parsear settings
        $settings = [];
        if (!empty($_POST['settings'])) {
            $settings = is_array($_POST['settings'])
                ? $_POST['settings']
                : json_decode($_POST['settings'], true) ?? [];
        }

        // Determinar slug final verificando duplicados
        $incomingSlug = trim($_POST['slug'] ?? '');
        $slug = $incomingSlug !== ''
            ? Gallery::generateUniqueSlug($incomingSlug, null, $gallery->id)
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
        header('Location: ' . route('image-gallery.edit', ['id' => $id]));
        exit;
    }

    /**
     * Elimina una galería
     */
    public function destroy($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('image_gallery.delete');

        $gallery = Gallery::find((int) $id);

        // Verificar que es una galería global (tenant_id = null o 0)
        if (!$gallery || ($gallery->tenant_id !== null && $gallery->tenant_id !== 0)) {
            flash('error', __gallery('gallery.not_found'));
            header('Location: ' . route('image-gallery.index'));
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
        header('Location: ' . route('image-gallery.index'));
        exit;
    }

    /**
     * Valida los datos de la galería
     */
    private function validateGallery(array $data, ?int $excludeId = null): array
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
            } elseif (Gallery::slugExists($data['slug'], null, $excludeId)) {
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
