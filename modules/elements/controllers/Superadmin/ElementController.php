<?php

namespace Elements\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Security\SessionSecurity;
use Elements\Models\Element;
use Elements\Models\ElementSetting;

/**
 * ElementController - Superadmin
 *
 * Manages global elements available to all tenants
 */
class ElementController
{
    /**
     * List global elements
     */
    public function index()
    {
        SessionSecurity::startSession();

        $elements = Element::getByTenant(null, false); // Only global elements

        return View::renderModule('elements', 'superadmin/elements/index', [
            'title' => __element('element.elements'),
            'elements' => $elements,
            'types' => Element::getAvailableTypes()
        ]);
    }

    /**
     * Create form
     */
    public function create()
    {
        SessionSecurity::startSession();

        return View::renderModule('elements', 'superadmin/elements/create', [
            'title' => __element('element.create'),
            'types' => Element::getAvailableTypes(),
            'heroLayouts' => Element::getHeroLayouts(),
            'faqLayouts' => Element::getFaqLayouts(),
            'ctaLayouts' => Element::getCtaLayouts()
        ]);
    }

    /**
     * Store new global element
     */
    public function store()
    {
        SessionSecurity::startSession();

        // Validation
        $errors = $this->validateElement($_POST, null);

        if (!empty($errors)) {
            flash('error', implode('<br>', $errors));
            $_SESSION['_old_input'] = $_POST;
            header('Location: ' . route('elements.create'));
            exit;
        }

        // Generate unique slug
        $baseSlugInput = trim($_POST['slug'] ?? '');
        $slugSource = $baseSlugInput !== '' ? $baseSlugInput : ($_POST['name'] ?? '');
        $slug = Element::generateUniqueSlug($slugSource, null);

        // Parse data
        $data = [];
        if (!empty($_POST['data'])) {
            $data = is_array($_POST['data'])
                ? $_POST['data']
                : json_decode($_POST['data'], true) ?? [];
        }

        // Parse settings
        $settings = [];
        if (!empty($_POST['settings'])) {
            $settings = is_array($_POST['settings'])
                ? $_POST['settings']
                : json_decode($_POST['settings'], true) ?? [];
        }

        // Create global element (tenant_id = NULL)
        $element = Element::create([
            'tenant_id' => null,
            'name' => trim($_POST['name']),
            'slug' => $slug,
            'description' => trim($_POST['description'] ?? ''),
            'type' => trim($_POST['type']),
            'layout_type' => trim($_POST['layout_type'] ?? ''),
            'data' => $data,
            'settings' => $settings,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'featured' => isset($_POST['featured']) ? 1 : 0,
            'sort_order' => (int)($_POST['sort_order'] ?? 0)
        ]);

        if ($element) {
            flash('success', __element('element.created'));
            header('Location: ' . route('elements.edit', ['id' => $element->id]));
            exit;
        }

        flash('error', __element('element.error_creating'));
        header('Location: ' . route('elements.create'));
        exit;
    }

    /**
     * Edit form
     */
    public function edit(int $id)
    {
        SessionSecurity::startSession();

        $element = Element::find($id);

        if (!$element) {
            flash('error', __element('element.not_found'));
            header('Location: ' . route('elements.index'));
            exit;
        }

        return View::renderModule('elements', 'superadmin/elements/edit', [
            'title' => __element('element.edit'),
            'element' => $element,
            'types' => Element::getAvailableTypes(),
            'heroLayouts' => Element::getHeroLayouts(),
            'faqLayouts' => Element::getFaqLayouts(),
            'ctaLayouts' => Element::getCtaLayouts()
        ]);
    }

    /**
     * Update element
     */
    public function update(int $id)
    {
        SessionSecurity::startSession();

        $element = Element::find($id);

        if (!$element) {
            flash('error', __element('element.not_found'));
            header('Location: ' . route('elements.index'));
            exit;
        }

        // Validation
        $errors = $this->validateElement($_POST, null, $id);

        if (!empty($errors)) {
            flash('error', implode('<br>', $errors));
            $_SESSION['_old_input'] = $_POST;
            header('Location: ' . route('elements.edit', ['id' => $id]));
            exit;
        }

        // Generate slug if changed
        $baseSlugInput = trim($_POST['slug'] ?? '');
        $slugSource = $baseSlugInput !== '' ? $baseSlugInput : ($_POST['name'] ?? '');
        $slug = ($slugSource !== $element->slug)
            ? Element::generateUniqueSlug($slugSource, null, $id)
            : $element->slug;

        // Parse data
        $data = [];
        if (!empty($_POST['data'])) {
            $data = is_array($_POST['data'])
                ? $_POST['data']
                : json_decode($_POST['data'], true) ?? [];
        }

        // Parse settings
        $settings = [];
        if (!empty($_POST['settings'])) {
            $settings = is_array($_POST['settings'])
                ? $_POST['settings']
                : json_decode($_POST['settings'], true) ?? [];
        }

        // Update
        $updated = Element::query()->where('id', $id)->update([
            'name' => trim($_POST['name']),
            'slug' => $slug,
            'description' => trim($_POST['description'] ?? ''),
            'type' => trim($_POST['type']),
            'layout_type' => trim($_POST['layout_type'] ?? ''),
            'data' => json_encode($data),
            'settings' => json_encode($settings),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'featured' => isset($_POST['featured']) ? 1 : 0,
            'sort_order' => (int)($_POST['sort_order'] ?? 0)
        ]);

        if ($updated) {
            flash('success', __element('element.updated'));
        } else {
            flash('error', __element('element.error'));
        }

        header('Location: ' . route('elements.edit', ['id' => $id]));
        exit;
    }

    /**
     * Delete element
     */
    public function destroy(int $id)
    {
        SessionSecurity::startSession();

        $element = Element::find($id);

        if (!$element) {
            flash('error', __element('element.not_found'));
            header('Location: ' . route('elements.index'));
            exit;
        }

        if (Element::query()->where('id', $id)->delete()) {
            flash('success', __element('element.deleted'));
        } else {
            flash('error', __element('element.error'));
        }

        header('Location: ' . route('elements.index'));
        exit;
    }

    /**
     * Validate element data
     */
    private function validateElement(array $data, ?int $tenantId = null, ?int $excludeId = null): array
    {
        $errors = [];

        // Name
        if (empty($data['name'])) {
            $errors[] = __element('validation.name_required');
        } elseif (mb_strlen($data['name']) > 255) {
            $errors[] = __element('validation.name_too_long');
        }

        // Type
        if (empty($data['type'])) {
            $errors[] = __element('validation.type_required');
        } elseif (!in_array($data['type'], array_keys(Element::getAvailableTypes()))) {
            $errors[] = __element('validation.type_invalid');
        }

        // Slug (if provided manually)
        if (!empty($data['slug'])) {
            if (!preg_match('/^[a-z0-9\-]+$/', $data['slug'])) {
                $errors[] = __element('validation.slug_invalid');
            }
        }

        return $errors;
    }
}
