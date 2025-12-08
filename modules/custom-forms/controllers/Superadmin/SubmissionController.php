<?php

namespace CustomForms\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Traits\RequiresPermission;
use CustomForms\Models\Form;
use CustomForms\Models\FormSubmission;
use CustomForms\Models\FormSetting;

/**
 * SubmissionController - Superadmin
 *
 * Gestión de envíos de formularios
 */
class SubmissionController
{
    use RequiresPermission;

    /**
     * Lista general de submissions de todos los formularios
     */
    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('custom_forms.submissions.view');

        // Obtener todos los formularios globales con conteo de submissions
        $forms = Form::getByTenant(null, false);

        return View::renderModule('custom-forms', 'superadmin/submissions/index', [
            'title' => __forms('submission.submissions'),
            'forms' => $forms
        ]);
    }

    /**
     * Lista submissions de un formulario específico
     */
    public function listByForm($formId)
    {
        SessionSecurity::startSession();
        $this->checkPermission('custom_forms.submissions.view');

        $form = Form::find((int) $formId);

        if (!$form || $form->tenant_id !== null) {
            flash('error', __forms('form.not_found'));
            header('Location: ' . route('custom-forms.submissions.index'));
            exit;
        }

        $page = (int) ($_GET['page'] ?? 1);
        $perPage = (int) (FormSetting::get('submissions_per_page', null, 25));

        $filters = [
            'is_read' => isset($_GET['unread']) ? false : null,
            'is_starred' => isset($_GET['starred']) ? true : null,
            'is_spam' => isset($_GET['spam']) ? true : false,
            'search' => $_GET['search'] ?? null,
        ];

        $pagination = FormSubmission::paginate($form->id, $perPage, $page, $filters);
        $counts = FormSubmission::countByStatus($form->id);

        return View::renderModule('custom-forms', 'superadmin/submissions/list', [
            'title' => __forms('submission.submissions_for', ['form' => $form->name]),
            'form' => $form,
            'submissions' => $pagination['items'],
            'pagination' => $pagination,
            'counts' => $counts,
            'filters' => $filters
        ]);
    }

    /**
     * Ver una submission individual
     */
    public function view($submissionId)
    {
        SessionSecurity::startSession();
        $this->checkPermission('custom_forms.submissions.view');

        $submission = FormSubmission::find((int) $submissionId);

        if (!$submission) {
            flash('error', __forms('submission.not_found'));
            header('Location: ' . route('custom-forms.submissions.index'));
            exit;
        }

        // Marcar como leída
        if (!$submission->is_read) {
            $submission->markAsRead();
        }

        $form = $submission->form();

        return View::renderModule('custom-forms', 'superadmin/submissions/view', [
            'title' => __forms('submission.view'),
            'form' => $form,
            'submission' => $submission
        ]);
    }

    /**
     * API: Marcar como leído/no leído
     */
    public function toggleRead($submissionId)
    {
        SessionSecurity::startSession();
        $this->checkPermission('custom_forms.submissions.view');

        header('Content-Type: application/json');

        $submission = FormSubmission::find((int) $submissionId);

        if (!$submission) {
            echo json_encode(['success' => false, 'error' => __forms('submission.not_found')]);
            exit;
        }

        if ($submission->is_read) {
            $submission->markAsUnread();
        } else {
            $submission->markAsRead();
        }

        echo json_encode([
            'success' => true,
            'is_read' => $submission->is_read
        ]);
        exit;
    }

    /**
     * API: Alternar destacado
     */
    public function toggleStar($submissionId)
    {
        SessionSecurity::startSession();
        $this->checkPermission('custom_forms.submissions.view');

        header('Content-Type: application/json');

        $submission = FormSubmission::find((int) $submissionId);

        if (!$submission) {
            echo json_encode(['success' => false, 'error' => __forms('submission.not_found')]);
            exit;
        }

        $submission->toggleStar();

        echo json_encode([
            'success' => true,
            'is_starred' => $submission->is_starred
        ]);
        exit;
    }

    /**
     * API: Marcar como spam
     */
    public function markSpam($submissionId)
    {
        SessionSecurity::startSession();
        $this->checkPermission('custom_forms.submissions.delete');

        header('Content-Type: application/json');

        $submission = FormSubmission::find((int) $submissionId);

        if (!$submission) {
            echo json_encode(['success' => false, 'error' => __forms('submission.not_found')]);
            exit;
        }

        $submission->markAsSpam();

        echo json_encode(['success' => true]);
        exit;
    }

    /**
     * API: Marcar como no spam
     */
    public function markNotSpam($submissionId)
    {
        SessionSecurity::startSession();
        $this->checkPermission('custom_forms.submissions.view');

        header('Content-Type: application/json');

        $submission = FormSubmission::find((int) $submissionId);

        if (!$submission) {
            echo json_encode(['success' => false, 'error' => __forms('submission.not_found')]);
            exit;
        }

        $submission->markAsNotSpam();

        echo json_encode(['success' => true]);
        exit;
    }

    /**
     * Elimina una submission
     */
    public function destroy($submissionId)
    {
        SessionSecurity::startSession();
        $this->checkPermission('custom_forms.submissions.delete');

        $submission = FormSubmission::find((int) $submissionId);

        if (!$submission) {
            if ($this->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => __forms('submission.not_found')]);
                exit;
            }
            flash('error', __forms('submission.not_found'));
            header('Location: ' . route('custom-forms.submissions.index'));
            exit;
        }

        $formId = $submission->form_id;
        $submission->delete();

        if ($this->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }

        flash('success', __forms('submission.deleted'));
        header('Location: ' . route('custom-forms.submissions.list', ['form_id' => $formId]));
        exit;
    }

    /**
     * Elimina múltiples submissions
     */
    public function bulkDelete()
    {
        SessionSecurity::startSession();
        $this->checkPermission('custom_forms.submissions.delete');

        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true);
        $ids = $input['ids'] ?? [];

        if (empty($ids)) {
            echo json_encode(['success' => false, 'error' => __forms('submission.no_selection')]);
            exit;
        }

        $deleted = 0;
        foreach ($ids as $id) {
            $submission = FormSubmission::find((int) $id);
            if ($submission) {
                $submission->delete();
                $deleted++;
            }
        }

        echo json_encode([
            'success' => true,
            'deleted' => $deleted,
            'message' => __forms('submission.bulk_deleted', ['count' => $deleted])
        ]);
        exit;
    }

    /**
     * Exporta submissions a CSV
     */
    public function export($formId)
    {
        SessionSecurity::startSession();
        $this->checkPermission('custom_forms.submissions.export');

        $form = Form::find((int) $formId);

        if (!$form) {
            flash('error', __forms('form.not_found'));
            header('Location: ' . route('custom-forms.submissions.index'));
            exit;
        }

        $submissions = FormSubmission::getByForm($form->id, ['is_spam' => false]);

        if (empty($submissions)) {
            flash('error', __forms('submission.no_submissions'));
            header('Location: ' . route('custom-forms.submissions.list', ['form_id' => $formId]));
            exit;
        }

        // Obtener campos del formulario para headers
        $fields = $form->inputFields();
        $headers = ['ID', 'Fecha', 'IP', 'Leído', 'Destacado'];

        foreach ($fields as $field) {
            $headers[] = $field->field_label;
        }

        // Generar CSV
        $filename = 'submissions-' . $form->slug . '-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // BOM para Excel
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Headers
        fputcsv($output, $headers);

        // Data
        foreach ($submissions as $submission) {
            $row = [
                $submission->id,
                $submission->created_at,
                $submission->ip_address,
                $submission->is_read ? 'Sí' : 'No',
                $submission->is_starred ? 'Sí' : 'No'
            ];

            $data = $submission->data ?? [];
            foreach ($fields as $field) {
                $value = $data[$field->field_name] ?? '';
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                $row[] = $value;
            }

            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }

    /**
     * API: Guardar notas de una submission
     */
    public function saveNotes($submissionId)
    {
        SessionSecurity::startSession();
        $this->checkPermission('custom_forms.submissions.view');

        header('Content-Type: application/json');

        $submission = FormSubmission::find((int) $submissionId);

        if (!$submission) {
            echo json_encode(['success' => false, 'error' => __forms('submission.not_found')]);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        $submission->update([
            'notes' => $input['notes'] ?? ''
        ]);

        echo json_encode(['success' => true]);
        exit;
    }

    /**
     * Verifica si es una petición AJAX
     */
    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
