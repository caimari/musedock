<?php

namespace CustomForms\Controllers;

use CustomForms\Models\Form;
use CustomForms\Models\FormField;
use CustomForms\Models\FormSubmission;
use CustomForms\Models\FormSetting;

/**
 * PublicController
 *
 * Maneja los envíos de formularios desde el frontend
 */
class PublicController
{
    /**
     * Procesa el envío de un formulario
     */
    public function submit($formId)
    {
        // Permitir tanto JSON como form-data
        $isJson = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;

        if ($isJson) {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
        } else {
            $data = $_POST;
        }

        $form = Form::find((int) $formId);

        if (!$form || !$form->is_active) {
            return $this->response(false, __forms('form.not_found'), null, $isJson);
        }

        // Verificar honeypot (anti-spam)
        if (FormSetting::get('honeypot_enabled', $form->tenant_id, true)) {
            if (!empty($data['_hp_' . $form->id])) {
                // Es spam, pero respondemos como si fuera exitoso
                return $this->response(true, $form->success_message, $form->redirect_url, $isJson);
            }
            unset($data['_hp_' . $form->id]);
        }

        // Eliminar tokens y campos internos
        unset($data['_token'], $data['_form_id']);

        // Obtener campos del formulario
        $fields = $form->activeFields();
        $inputFields = array_filter($fields, fn($f) => $f->isInputField());

        // Validar datos
        $errors = $this->validateSubmission($data, $inputFields);

        if (!empty($errors)) {
            return $this->response(false, $form->error_message ?: __forms('form.validation_error'), null, $isJson, $errors);
        }

        // Procesar archivos si hay
        if (!empty($_FILES)) {
            $fileData = $this->processFiles($_FILES, $form);
            $data = array_merge($data, $fileData);
        }

        // Filtrar solo campos del formulario
        $filteredData = [];
        foreach ($inputFields as $field) {
            if (isset($data[$field->field_name])) {
                $filteredData[$field->field_name] = $data[$field->field_name];
            } elseif ($field->field_type === 'checkbox') {
                $filteredData[$field->field_name] = false;
            }
        }

        // Guardar en BD si está habilitado
        $submission = null;
        if ($form->store_submissions) {
            $submission = FormSubmission::create([
                'form_id' => $form->id,
                'data' => $filteredData,
                'ip_address' => $this->getClientIp(),
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                'referrer_url' => $_SERVER['HTTP_REFERER'] ?? null,
                'page_url' => $data['_page_url'] ?? null,
                'user_id' => function_exists('user') && user() ? user()->id : null
            ]);

            // Incrementar contador
            $form->incrementSubmissions();
        }

        // Enviar email de notificación
        $emailSent = false;
        if (!empty($form->email_to)) {
            $emailSent = $this->sendNotificationEmail($form, $filteredData, $inputFields);

            if ($submission && $emailSent) {
                $submission->update([
                    'email_sent' => true,
                    'email_sent_at' => date('Y-m-d H:i:s')
                ]);
            }
        }

        // Enviar email de confirmación al usuario
        if ($form->send_confirmation_email && $submission) {
            $submitterEmail = $submission->getSubmitterEmail();
            if ($submitterEmail) {
                $this->sendConfirmationEmail($form, $submitterEmail, $filteredData);
                $submission->update(['confirmation_sent' => true]);
            }
        }

        return $this->response(
            true,
            $form->success_message ?: __forms('form.success_default'),
            $form->redirect_url,
            $isJson
        );
    }

    /**
     * Valida los datos del envío
     */
    private function validateSubmission(array $data, array $fields): array
    {
        $errors = [];

        foreach ($fields as $field) {
            $value = $data[$field->field_name] ?? null;
            $fieldErrors = $field->validate($value);

            if (!empty($fieldErrors)) {
                $errors[$field->field_name] = $fieldErrors;
            }
        }

        return $errors;
    }

    /**
     * Procesa archivos subidos
     */
    private function processFiles(array $files, Form $form): array
    {
        $data = [];
        $maxSize = (int) (FormSetting::get('max_file_size_mb', $form->tenant_id, 5)) * 1024 * 1024;
        $allowedTypes = explode(',', FormSetting::get('allowed_file_types', $form->tenant_id, 'pdf,doc,docx,jpg,jpeg,png,gif'));

        foreach ($files as $fieldName => $file) {
            if ($file['error'] !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
                continue;
            }

            // Validar tamaño
            if ($file['size'] > $maxSize) {
                continue;
            }

            // Validar extensión
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedTypes)) {
                continue;
            }

            // Generar nombre único
            $newName = uniqid() . '-' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
            $uploadDir = '/uploads/forms/' . $form->id . '/';
            $fullDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $uploadDir;

            if (!is_dir($fullDir)) {
                mkdir($fullDir, 0755, true);
            }

            $destPath = $fullDir . $newName;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $baseUrl = rtrim(getenv('APP_URL') ?: '', '/');
                $data[$fieldName] = $baseUrl . $uploadDir . $newName;
            }
        }

        return $data;
    }

    /**
     * Envía email de notificación al administrador
     */
    private function sendNotificationEmail(Form $form, array $data, array $fields): bool
    {
        $to = $form->email_to;
        $subject = $form->email_subject ?: __forms('email.new_submission', ['form' => $form->name]);

        $fromName = $form->email_from_name ?: FormSetting::get('default_from_name', $form->tenant_id, 'MuseDock');
        $fromEmail = $form->email_from_email ?: FormSetting::get('default_from_email', $form->tenant_id, 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));

        // Construir cuerpo del email
        $body = "<h2>" . __forms('email.submission_title', ['form' => $form->name]) . "</h2>";
        $body .= "<table style='width:100%; border-collapse: collapse;'>";

        foreach ($fields as $field) {
            if (!$field->isInputField()) continue;

            $value = $data[$field->field_name] ?? '-';
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            if ($field->field_type === 'checkbox') {
                $value = $value ? __forms('email.yes') : __forms('email.no');
            }

            $body .= "<tr style='border-bottom: 1px solid #eee;'>";
            $body .= "<td style='padding: 10px; font-weight: bold; width: 30%;'>" . htmlspecialchars($field->field_label) . "</td>";
            $body .= "<td style='padding: 10px;'>" . htmlspecialchars($value) . "</td>";
            $body .= "</tr>";
        }

        $body .= "</table>";
        $body .= "<p style='margin-top: 20px; color: #666; font-size: 12px;'>";
        $body .= __forms('email.sent_at') . ": " . date('d/m/Y H:i:s') . "<br>";
        $body .= "IP: " . $this->getClientIp();
        $body .= "</p>";

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $fromName . ' <' . $fromEmail . '>',
        ];

        if ($form->email_reply_to) {
            $headers[] = 'Reply-To: ' . $form->email_reply_to;
        }

        return @mail($to, $subject, $body, implode("\r\n", $headers));
    }

    /**
     * Envía email de confirmación al usuario
     */
    private function sendConfirmationEmail(Form $form, string $to, array $data): bool
    {
        $subject = $form->confirmation_email_subject ?: __forms('email.confirmation_subject');
        $body = $form->confirmation_email_message ?: __forms('email.confirmation_default');

        // Reemplazar variables en el mensaje
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $body = str_replace('{{' . $key . '}}', $value, $body);
        }

        $fromName = $form->email_from_name ?: FormSetting::get('default_from_name', $form->tenant_id, 'MuseDock');
        $fromEmail = $form->email_from_email ?: FormSetting::get('default_from_email', $form->tenant_id, 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $fromName . ' <' . $fromEmail . '>',
        ];

        return @mail($to, $subject, nl2br($body), implode("\r\n", $headers));
    }

    /**
     * Genera la respuesta
     */
    private function response(bool $success, string $message, ?string $redirect, bool $isJson, array $errors = [])
    {
        if ($isJson || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $message,
                'redirect' => $redirect,
                'errors' => $errors
            ]);
            exit;
        }

        // Respuesta tradicional
        if ($success) {
            if ($redirect) {
                header('Location: ' . $redirect);
            } else {
                flash('success', $message);
                header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
            }
        } else {
            flash('error', $message);
            $_SESSION['_form_errors'] = $errors;
            $_SESSION['_old_input'] = $_POST;
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
        }
        exit;
    }

    /**
     * Obtiene la IP del cliente
     */
    private function getClientIp(): string
    {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];

        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}
