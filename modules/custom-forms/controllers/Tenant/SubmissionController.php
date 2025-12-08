<?php

namespace CustomForms\Controllers\Tenant;

use Screenart\Musedock\View;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Services\TenantManager;
use CustomForms\Models\Form;
use CustomForms\Models\FormSubmission;
use CustomForms\Models\FormSetting;

/**
 * SubmissionController - Tenant
 *
 * Gestión de envíos de formularios del tenant
 */
class SubmissionController
{
    /**
     * Lista general de submissions
     */
    public function index()
    {
        SessionSecurity::startSession();
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __forms('form.tenant_required'));
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }

        $forms = Form::getByTenant($tenantId, false);

        return View::renderModule('custom-forms', 'tenant/submissions/index', [
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
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __forms('form.tenant_required'));
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }

        $form = Form::find((int) $formId);

        if (!$form || $form->tenant_id !== $tenantId) {
            flash('error', __forms('form.not_found'));
            header('Location: ' . route('tenant.custom-forms.submissions.index'));
            exit;
        }

        $page = (int) ($_GET['page'] ?? 1);
        $perPage = (int) (FormSetting::get('submissions_per_page', $tenantId, 25));

        $filters = [
            'is_read' => isset($_GET['unread']) ? false : null,
            'is_starred' => isset($_GET['starred']) ? true : null,
            'is_spam' => isset($_GET['spam']) ? true : false,
            'search' => $_GET['search'] ?? null,
        ];

        $pagination = FormSubmission::paginate($form->id, $perPage, $page, $filters);
        $counts = FormSubmission::countByStatus($form->id);

        return View::renderModule('custom-forms', 'tenant/submissions/list', [
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
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __forms('form.tenant_required'));
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }

        $submission = FormSubmission::find((int) $submissionId);

        if (!$submission) {
            flash('error', __forms('submission.not_found'));
            header('Location: ' . route('tenant.custom-forms.submissions.index'));
            exit;
        }

        $form = $submission->form();

        if (!$form || $form->tenant_id !== $tenantId) {
            flash('error', __forms('submission.not_found'));
            header('Location: ' . route('tenant.custom-forms.submissions.index'));
            exit;
        }

        if (!$submission->is_read) {
            $submission->markAsRead();
        }

        return View::renderModule('custom-forms', 'tenant/submissions/view', [
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
        $tenantId = TenantManager::currentTenantId();

        header('Content-Type: application/json');

        $submission = FormSubmission::find((int) $submissionId);

        if (!$submission || !$this->canAccess($submission, $tenantId)) {
            echo json_encode(['success' => false, 'error' => __forms('submission.not_found')]);
            exit;
        }

        $submission->is_read ? $submission->markAsUnread() : $submission->markAsRead();

        echo json_encode(['success' => true, 'is_read' => $submission->is_read]);
        exit;
    }

    /**
     * API: Alternar destacado
     */
    public function toggleStar($submissionId)
    {
        SessionSecurity::startSession();
        $tenantId = TenantManager::currentTenantId();

        header('Content-Type: application/json');

        $submission = FormSubmission::find((int) $submissionId);

        if (!$submission || !$this->canAccess($submission, $tenantId)) {
            echo json_encode(['success' => false, 'error' => __forms('submission.not_found')]);
            exit;
        }

        $submission->toggleStar();

        echo json_encode(['success' => true, 'is_starred' => $submission->is_starred]);
        exit;
    }

    /**
     * Elimina una submission
     */
    public function destroy($submissionId)
    {
        SessionSecurity::startSession();
        $tenantId = TenantManager::currentTenantId();

        $submission = FormSubmission::find((int) $submissionId);

        if (!$submission || !$this->canAccess($submission, $tenantId)) {
            if ($this->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => __forms('submission.not_found')]);
                exit;
            }
            flash('error', __forms('submission.not_found'));
            header('Location: ' . route('tenant.custom-forms.submissions.index'));
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
        header('Location: ' . route('tenant.custom-forms.submissions.list', ['form_id' => $formId]));
        exit;
    }

    /**
     * Exporta submissions a CSV
     */
    public function export($formId)
    {
        SessionSecurity::startSession();
        $tenantId = TenantManager::currentTenantId();

        $form = Form::find((int) $formId);

        if (!$form || $form->tenant_id !== $tenantId) {
            flash('error', __forms('form.not_found'));
            header('Location: ' . route('tenant.custom-forms.submissions.index'));
            exit;
        }

        $submissions = FormSubmission::getByForm($form->id, ['is_spam' => false]);

        if (empty($submissions)) {
            flash('error', __forms('submission.no_submissions'));
            header('Location: ' . route('tenant.custom-forms.submissions.list', ['form_id' => $formId]));
            exit;
        }

        $fields = $form->inputFields();
        $headers = ['ID', 'Fecha', 'IP'];

        foreach ($fields as $field) {
            $headers[] = $field->field_label;
        }

        $filename = 'submissions-' . $form->slug . '-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, $headers);

        foreach ($submissions as $submission) {
            $row = [$submission->id, $submission->created_at, $submission->ip_address];
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
     * Verifica si puede acceder a la submission
     */
    private function canAccess(FormSubmission $submission, ?int $tenantId): bool
    {
        if ($tenantId === null) {
            return false;
        }

        $form = $submission->form();
        return $form && $form->tenant_id === $tenantId;
    }

    /**
     * Verifica si es AJAX
     */
    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
