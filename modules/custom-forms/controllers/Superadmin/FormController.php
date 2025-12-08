<?php

namespace CustomForms\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Traits\RequiresPermission;
use CustomForms\Models\Form;
use CustomForms\Models\FormField;
use CustomForms\Models\FormSetting;

/**
 * FormController - Superadmin
 *
 * Gestión de formularios globales desde el panel de superadmin
 */
class FormController
{
    use RequiresPermission;

    /**
     * Lista todos los formularios globales
     */
    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('custom_forms.view');

        $forms = Form::getByTenant(null, false);

        return View::renderModule('custom-forms', 'superadmin/forms/index', [
            'title' => __forms('form.forms'),
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

        return View::renderModule('custom-forms', 'superadmin/forms/create', [
            'title' => __forms('form.create'),
            'fieldTypes' => Form::getFieldTypes(),
            'settings' => FormSetting::getAll()
        ]);
    }

    /**
     * Almacena un nuevo formulario
     */
    public function store()
    {
        SessionSecurity::startSession();
        $this->checkPermission('custom_forms.create');

        // Validación
        $errors = $this->validateForm($_POST);

        if (!empty($errors)) {
            flash('error', implode('<br>', $errors));
            $_SESSION['_old_input'] = $_POST;
            header('Location: ' . route('custom-forms.create'));
            exit;
        }

        // Generar slug único
        $slug = !empty($_POST['slug'])
            ? $_POST['slug']
            : Form::generateUniqueSlug($_POST['name']);

        // Obtener configuraciones por defecto
        $defaultSettings = FormSetting::getAll();

        // Crear formulario
        $form = Form::create([
            'tenant_id' => null, // Formulario global
            'name' => trim($_POST['name']),
            'slug' => $slug,
            'description' => trim($_POST['description'] ?? ''),
            'submit_button_text' => $_POST['submit_button_text'] ?? 'Enviar',
            'success_message' => $_POST['success_message'] ?? $defaultSettings['default_success_message'] ?? '',
            'error_message' => $_POST['error_message'] ?? $defaultSettings['default_error_message'] ?? '',
            'redirect_url' => trim($_POST['redirect_url'] ?? ''),
            'email_to' => trim($_POST['email_to'] ?? ''),
            'email_subject' => $_POST['email_subject'] ?? '',
            'email_from_name' => $_POST['email_from_name'] ?? '',
            'email_from_email' => $_POST['email_from_email'] ?? '',
            'store_submissions' => isset($_POST['store_submissions']),
            'is_active' => isset($_POST['is_active'])
        ]);

        if ($form) {
            flash('success', __forms('form.created'));
            header('Location: ' . route('custom-forms.edit', ['id' => $form->id]));
        } else {
            flash('error', __forms('form.error_creating'));
            $_SESSION['_old_input'] = $_POST;
            header('Location: ' . route('custom-forms.create'));
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

        $form = Form::find((int) $id);

        if (!$form || $form->tenant_id !== null) {
            flash('error', __forms('form.not_found'));
            header('Location: ' . route('custom-forms.index'));
            exit;
        }

        $fields = $form->fields();

        return View::renderModule('custom-forms', 'superadmin/forms/edit', [
            'title' => __forms('form.edit') . ': ' . $form->name,
            'form' => $form,
            'fields' => $fields,
            'fieldTypes' => Form::getFieldTypes(),
            'settings' => FormSetting::getAll()
        ]);
    }

    /**
     * Actualiza un formulario
     */
    public function update($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('custom_forms.edit');

        $form = Form::find((int) $id);

        if (!$form || $form->tenant_id !== null) {
            flash('error', __forms('form.not_found'));
            header('Location: ' . route('custom-forms.index'));
            exit;
        }

        // Validación
        $errors = $this->validateForm($_POST, $form->id);

        if (!empty($errors)) {
            flash('error', implode('<br>', $errors));
            $_SESSION['_old_input'] = $_POST;
            header('Location: ' . route('custom-forms.edit', ['id' => $id]));
            exit;
        }

        // Actualizar
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
            'email_from_name' => $_POST['email_from_name'] ?? '',
            'email_from_email' => $_POST['email_from_email'] ?? '',
            'store_submissions' => isset($_POST['store_submissions']),
            'is_active' => isset($_POST['is_active'])
        ]);

        flash('success', __forms('form.updated'));
        header('Location: ' . route('custom-forms.edit', ['id' => $id]));
        exit;
    }

    /**
     * Elimina un formulario
     */
    public function destroy($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('custom_forms.delete');

        $form = Form::find((int) $id);

        if (!$form || $form->tenant_id !== null) {
            flash('error', __forms('form.not_found'));
            header('Location: ' . route('custom-forms.index'));
            exit;
        }

        // Eliminar formulario (campos y submissions se eliminan por CASCADE)
        $form->delete();

        flash('success', __forms('form.deleted'));
        header('Location: ' . route('custom-forms.index'));
        exit;
    }

    /**
     * Duplica un formulario
     */
    public function duplicate($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('custom_forms.create');

        $original = Form::find((int) $id);

        if (!$original) {
            flash('error', __forms('form.not_found'));
            header('Location: ' . route('custom-forms.index'));
            exit;
        }

        // Crear nuevo slug
        $newSlug = Form::generateUniqueSlug($original->name . ' (copia)');

        // Duplicar formulario
        $newForm = Form::create([
            'tenant_id' => null,
            'name' => $original->name . ' (copia)',
            'slug' => $newSlug,
            'description' => $original->description,
            'submit_button_text' => $original->submit_button_text,
            'success_message' => $original->success_message,
            'error_message' => $original->error_message,
            'redirect_url' => $original->redirect_url,
            'email_to' => $original->email_to,
            'email_subject' => $original->email_subject,
            'email_from_name' => $original->email_from_name,
            'email_from_email' => $original->email_from_email,
            'store_submissions' => $original->store_submissions,
            'is_active' => false // Desactivado por defecto
        ]);

        if ($newForm) {
            // Duplicar campos
            $fields = $original->fields();
            foreach ($fields as $field) {
                FormField::create([
                    'form_id' => $newForm->id,
                    'field_type' => $field->field_type,
                    'field_name' => $field->field_name,
                    'field_label' => $field->field_label,
                    'placeholder' => $field->placeholder,
                    'default_value' => $field->default_value,
                    'help_text' => $field->help_text,
                    'options' => $field->options,
                    'validation_rules' => $field->validation_rules,
                    'is_required' => $field->is_required,
                    'min_length' => $field->min_length,
                    'max_length' => $field->max_length,
                    'error_message' => $field->error_message,
                    'field_class' => $field->field_class,
                    'width' => $field->width,
                    'sort_order' => $field->sort_order,
                    'is_active' => $field->is_active
                ]);
            }

            flash('success', __forms('form.duplicated'));
            header('Location: ' . route('custom-forms.edit', ['id' => $newForm->id]));
        } else {
            flash('error', __forms('form.error_duplicating'));
            header('Location: ' . route('custom-forms.index'));
        }
        exit;
    }

    /**
     * API: Añade un campo al formulario
     */
    public function addField($formId)
    {
        SessionSecurity::startSession();
        $this->checkPermission('custom_forms.edit');

        header('Content-Type: application/json');

        $form = Form::find((int) $formId);

        if (!$form) {
            echo json_encode(['success' => false, 'error' => __forms('form.not_found')]);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

        // Generar nombre de campo único
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
            'min_length' => $input['min_length'] ?? null,
            'max_length' => $input['max_length'] ?? null,
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

        header('Content-Type: application/json');

        $field = FormField::find((int) $fieldId);

        if (!$field) {
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
            'min_length' => $input['min_length'] ?? $field->min_length,
            'max_length' => $input['max_length'] ?? $field->max_length,
            'error_message' => $input['error_message'] ?? $field->error_message,
            'field_class' => $input['field_class'] ?? $field->field_class,
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

        header('Content-Type: application/json');

        $field = FormField::find((int) $fieldId);

        if (!$field) {
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

        header('Content-Type: application/json');

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
     * API: Obtiene los campos de un formulario
     */
    public function getFields($formId)
    {
        SessionSecurity::startSession();
        $this->checkPermission('custom_forms.view');

        header('Content-Type: application/json');

        $form = Form::find((int) $formId);

        if (!$form) {
            echo json_encode(['success' => false, 'error' => __forms('form.not_found')]);
            exit;
        }

        $fields = $form->fields();
        $fieldsArray = array_map(fn($f) => $f->toArray(), $fields);

        echo json_encode(['success' => true, 'fields' => $fieldsArray]);
        exit;
    }

    /**
     * Valida los datos del formulario
     */
    private function validateForm(array $data, ?int $excludeId = null): array
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
            } elseif (Form::slugExists($data['slug'], null, $excludeId)) {
                $errors[] = __forms('validation.slug_exists');
            }
        }

        if (!empty($data['email_to'])) {
            $emails = array_map('trim', explode(',', $data['email_to']));
            foreach ($emails as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = __forms('validation.email_invalid', ['email' => $email]);
                    break;
                }
            }
        }

        return $errors;
    }
}
