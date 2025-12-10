<?php

namespace CustomForms\Controllers\Tenant;

use Screenart\Musedock\View;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Services\TenantManager;
use Screenart\Musedock\Traits\RequiresPermission;
use CustomForms\Models\Form;
use CustomForms\Models\FormField;
use CustomForms\Models\FormSetting;

/**
 * FormController - Tenant
 *
 * Gestión de formularios específicos del tenant
 */
class FormController
{
    use RequiresPermission;

    /**
     * Lista los formularios del tenant
     */
    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('custom_forms.view');
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __forms('form.tenant_required'));
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }

        $forms = Form::getByTenant($tenantId, true);

        return View::renderModule('custom-forms', 'tenant/forms/index', [
            'title' => __forms('form.my_forms'),
            'forms' => $forms
        ]);
    }

    /**
     * Formulario de creación
     */
    public function create()
    {
        SessionSecurity::startSession();
        $this->checkPermission('custom_forms.create');
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __forms('form.tenant_required'));
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }

        return View::renderModule('custom-forms', 'tenant/forms/create', [
            'title' => __forms('form.create'),
            'fieldTypes' => Form::getFieldTypes(),
            'settings' => FormSetting::getAll($tenantId)
        ]);
    }

    /**
     * Almacena un nuevo formulario
     */
    public function store()
    {
        SessionSecurity::startSession();
        $this->checkPermission('custom_forms.create');
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __forms('form.tenant_required'));
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }

        $errors = $this->validateForm($_POST, $tenantId);

        if (!empty($errors)) {
            flash('error', implode('<br>', $errors));
            $_SESSION['_old_input'] = $_POST;
            header('Location: ' . route('tenant.custom-forms.create'));
            exit;
        }

        $slug = !empty($_POST['slug'])
            ? $_POST['slug']
            : Form::generateUniqueSlug($_POST['name'], $tenantId);

        $defaultSettings = FormSetting::getAll($tenantId);

        $form = Form::create([
            'tenant_id' => $tenantId,
            'name' => trim($_POST['name']),
            'slug' => $slug,
            'description' => trim($_POST['description'] ?? ''),
            'submit_button_text' => $_POST['submit_button_text'] ?? 'Enviar',
            'success_message' => $_POST['success_message'] ?? $defaultSettings['default_success_message'] ?? '',
            'error_message' => $_POST['error_message'] ?? $defaultSettings['default_error_message'] ?? '',
            'redirect_url' => trim($_POST['redirect_url'] ?? ''),
            'email_to' => trim($_POST['email_to'] ?? ''),
            'email_subject' => $_POST['email_subject'] ?? '',
            'store_submissions' => isset($_POST['store_submissions']),
            'is_active' => isset($_POST['is_active'])
        ]);

        if ($form) {
            flash('success', __forms('form.created'));
            header('Location: ' . route('tenant.custom-forms.edit', ['id' => $form->id]));
        } else {
            flash('error', __forms('form.error_creating'));
            $_SESSION['_old_input'] = $_POST;
            header('Location: ' . route('tenant.custom-forms.create'));
        }
        exit;
    }

    /**
     * Formulario de edición con Form Builder
     */
    public function edit($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('custom_forms.edit');
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __forms('form.tenant_required'));
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }

        $form = Form::find((int) $id);

        if (!$form || ($form->tenant_id !== null && $form->tenant_id !== $tenantId)) {
            flash('error', __forms('form.not_found'));
            header('Location: ' . route('tenant.custom-forms.index'));
            exit;
        }

        $fields = $form->fields();
        $isOwner = $form->tenant_id === $tenantId;

        return View::renderModule('custom-forms', 'tenant/forms/edit', [
            'title' => __forms('form.edit') . ': ' . $form->name,
            'form' => $form,
            'fields' => $fields,
            'fieldTypes' => Form::getFieldTypes(),
            'settings' => FormSetting::getAll($tenantId),
            'isOwner' => $isOwner,
            'canEdit' => $isOwner
        ]);
    }

    /**
     * Actualiza un formulario
     */
    public function update($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('custom_forms.edit');
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __forms('form.tenant_required'));
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }

        $form = Form::find((int) $id);

        if (!$form || $form->tenant_id !== $tenantId) {
            flash('error', __forms('form.not_found'));
            header('Location: ' . route('tenant.custom-forms.index'));
            exit;
        }

        $errors = $this->validateForm($_POST, $tenantId, $form->id);

        if (!empty($errors)) {
            flash('error', implode('<br>', $errors));
            $_SESSION['_old_input'] = $_POST;
            header('Location: ' . route('tenant.custom-forms.edit', ['id' => $id]));
            exit;
        }

        $form->update([
            'name' => trim($_POST['name']),
            'slug' => $_POST['slug'] ?? $form->slug,
            'description' => trim($_POST['description'] ?? ''),
            'submit_button_text' => $_POST['submit_button_text'] ?? 'Enviar',
            'success_message' => $_POST['success_message'] ?? '',
            'error_message' => $_POST['error_message'] ?? '',
            'redirect_url' => trim($_POST['redirect_url'] ?? ''),
            'email_to' => trim($_POST['email_to'] ?? ''),
            'email_subject' => $_POST['email_subject'] ?? '',
            'store_submissions' => isset($_POST['store_submissions']),
            'is_active' => isset($_POST['is_active'])
        ]);

        flash('success', __forms('form.updated'));
        header('Location: ' . route('tenant.custom-forms.edit', ['id' => $id]));
        exit;
    }

    /**
     * Elimina un formulario
     */
    public function destroy($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('custom_forms.delete');
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __forms('form.tenant_required'));
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }

        $form = Form::find((int) $id);

        if (!$form || $form->tenant_id !== $tenantId) {
            flash('error', __forms('form.not_found'));
            header('Location: ' . route('tenant.custom-forms.index'));
            exit;
        }

        $form->delete();

        flash('success', __forms('form.deleted'));
        header('Location: ' . route('tenant.custom-forms.index'));
        exit;
    }

    /**
     * Selector de formularios para el editor (AJAX)
     */
    public function selector()
    {
        SessionSecurity::startSession();
        $this->checkPermission('custom_forms.view');
        $tenantId = TenantManager::currentTenantId();

        header('Content-Type: application/json');

        $forms = Form::getActive($tenantId);

        $data = array_map(function ($form) {
            return [
                'id' => $form->id,
                'name' => $form->name,
                'slug' => $form->slug,
                'field_count' => $form->fieldCount(),
                'shortcode' => '[custom-form id=' . $form->id . ']',
                'shortcode_slug' => '[custom-form slug="' . $form->slug . '"]'
            ];
        }, $forms);

        echo json_encode(['success' => true, 'forms' => $data]);
        exit;
    }

    /**
     * API: Añade un campo al formulario
     */
    public function addField($formId)
    {
        SessionSecurity::startSession();
        $this->checkPermission('custom_forms.edit');
        $tenantId = TenantManager::currentTenantId();

        header('Content-Type: application/json');

        $form = Form::find((int) $formId);

        if (!$form || $form->tenant_id !== $tenantId) {
            echo json_encode(['success' => false, 'error' => __forms('form.not_found')]);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

        $fieldName = FormField::generateFieldName(
            $input['field_label'] ?? 'Campo',
            $form->id
        );

        $field = FormField::create([
            'form_id' => $form->id,
            'field_type' => $input['field_type'] ?? 'text',
            'field_name' => $fieldName,
            'field_label' => $input['field_label'] ?? 'Nuevo campo',
            'placeholder' => $input['placeholder'] ?? '',
            'default_value' => $input['default_value'] ?? '',
            'help_text' => $input['help_text'] ?? '',
            'options' => $input['options'] ?? null,
            'is_required' => $input['is_required'] ?? false,
            'width' => $input['width'] ?? 'full',
            'sort_order' => FormField::getNextSortOrder($form->id),
            'is_active' => true
        ]);

        if ($field) {
            echo json_encode(['success' => true, 'field' => $field->toArray()]);
        } else {
            echo json_encode(['success' => false, 'error' => __forms('field.error_creating')]);
        }
        exit;
    }

    /**
     * API: Actualiza un campo
     */
    public function updateField($fieldId)
    {
        SessionSecurity::startSession();
        $this->checkPermission('custom_forms.edit');
        $tenantId = TenantManager::currentTenantId();

        header('Content-Type: application/json');

        $field = FormField::find((int) $fieldId);

        if (!$field) {
            echo json_encode(['success' => false, 'error' => __forms('field.not_found')]);
            exit;
        }

        // Verificar propiedad
        $form = $field->form();
        if (!$form || $form->tenant_id !== $tenantId) {
            echo json_encode(['success' => false, 'error' => __forms('field.not_found')]);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

        $field->update([
            'field_type' => $input['field_type'] ?? $field->field_type,
            'field_label' => $input['field_label'] ?? $field->field_label,
            'placeholder' => $input['placeholder'] ?? $field->placeholder,
            'default_value' => $input['default_value'] ?? $field->default_value,
            'help_text' => $input['help_text'] ?? $field->help_text,
            'options' => isset($input['options']) ? $input['options'] : $field->options,
            'is_required' => $input['is_required'] ?? $field->is_required,
            'width' => $input['width'] ?? $field->width,
            'is_active' => $input['is_active'] ?? $field->is_active
        ]);

        echo json_encode(['success' => true, 'field' => $field->toArray()]);
        exit;
    }

    /**
     * API: Elimina un campo
     */
    public function deleteField($fieldId)
    {
        SessionSecurity::startSession();
        $this->checkPermission('custom_forms.edit');
        $tenantId = TenantManager::currentTenantId();

        header('Content-Type: application/json');

        $field = FormField::find((int) $fieldId);

        if (!$field) {
            echo json_encode(['success' => false, 'error' => __forms('field.not_found')]);
            exit;
        }

        $form = $field->form();
        if (!$form || $form->tenant_id !== $tenantId) {
            echo json_encode(['success' => false, 'error' => __forms('field.not_found')]);
            exit;
        }

        $field->delete();

        echo json_encode(['success' => true, 'message' => __forms('field.deleted')]);
        exit;
    }

    /**
     * API: Reordena los campos
     */
    public function reorderFields($formId)
    {
        SessionSecurity::startSession();
        $this->checkPermission('custom_forms.edit');
        $tenantId = TenantManager::currentTenantId();

        header('Content-Type: application/json');

        $form = Form::find((int) $formId);

        if (!$form || $form->tenant_id !== $tenantId) {
            echo json_encode(['success' => false, 'error' => __forms('form.not_found')]);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['order']) || !is_array($input['order'])) {
            echo json_encode(['success' => false, 'error' => __forms('field.invalid_order')]);
            exit;
        }

        $success = FormField::reorder((int) $formId, $input['order']);

        echo json_encode([
            'success' => $success,
            'message' => $success ? __forms('field.reordered') : __forms('field.reorder_error')
        ]);
        exit;
    }

    /**
     * Valida los datos del formulario
     */
    private function validateForm(array $data, int $tenantId, ?int $excludeId = null): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors[] = __forms('validation.name_required');
        } elseif (strlen($data['name']) > 255) {
            $errors[] = __forms('validation.name_too_long');
        }

        if (!empty($data['slug'])) {
            if (!preg_match('/^[a-z0-9\-]+$/', $data['slug'])) {
                $errors[] = __forms('validation.slug_invalid');
            } elseif (Form::slugExists($data['slug'], $tenantId, $excludeId)) {
                $errors[] = __forms('validation.slug_exists');
            }
        }

        return $errors;
    }
}
