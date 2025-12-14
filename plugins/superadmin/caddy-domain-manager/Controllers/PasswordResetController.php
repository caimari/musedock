<?php

namespace CaddyDomainManager\Controllers;

use CaddyDomainManager\Models\Customer;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Logger;
use Screenart\Musedock\Database;

/**
 * PasswordResetController - Recuperación de contraseña para customers
 *
 * Flujo completo:
 * 1. Customer solicita reset desde /customer/forgot-password
 * 2. Sistema envía email con token de 1 hora
 * 3. Customer hace clic en link y accede a /customer/reset-password?token=xxx
 * 4. Customer ingresa nueva contraseña
 * 5. Sistema valida token y actualiza contraseña
 */
class PasswordResetController
{
    /**
     * Mostrar formulario de "olvidé mi contraseña"
     *
     * GET /customer/forgot-password
     */
    public function showForgotForm(): void
    {
        SessionSecurity::startSession();

        // Si ya está logueado, redirigir a dashboard
        if (isset($_SESSION['customer'])) {
            header('Location: /customer/dashboard');
            exit;
        }

        $data = [
            'page_title' => 'Recuperar contraseña - MuseDock',
            'csrf_token' => csrf_token()
        ];

        $this->render('Customer.auth.forgot-password', $data);
    }

    /**
     * Procesar solicitud de reset (enviar email)
     *
     * POST /customer/forgot-password
     */
    public function sendResetLink(): void
    {
        SessionSecurity::startSession();

        // Validar CSRF
        if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'error' => 'Token CSRF inválido'], 403);
            return;
        }

        $email = trim(strtolower($_POST['email'] ?? ''));

        // Validación básica
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->jsonResponse(['success' => false, 'error' => 'Email inválido'], 400);
            return;
        }

        // Buscar customer
        $customer = Customer::findByEmail($email);

        // IMPORTANTE: Por seguridad, SIEMPRE responder "Email enviado" aunque no exista
        // Esto previene enumeration attacks
        if (!$customer) {
            Logger::warning("[PasswordReset] Reset requested for non-existent email: {$email}");
            // Esperar un poco para simular procesamiento
            usleep(500000); // 0.5 segundos
            $this->jsonResponse([
                'success' => true,
                'message' => 'Si el email existe, recibirás un enlace de recuperación en breve.'
            ]);
            return;
        }

        // Verificar estado de la cuenta
        if ($customer['status'] === 'suspended') {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Tu cuenta ha sido suspendida. Contacta con soporte.'
            ], 403);
            return;
        }

        // Generar token seguro
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hora

        // Guardar token en BD
        try {
            $pdo = Database::connect();

            // Invalidar tokens anteriores del mismo customer
            $stmt = $pdo->prepare("UPDATE customer_password_resets SET used_at = NOW() WHERE customer_id = ? AND used_at IS NULL");
            $stmt->execute([$customer['id']]);

            // Crear nuevo token
            $stmt = $pdo->prepare("
                INSERT INTO customer_password_resets (customer_id, token, email, expires_at, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $customer['id'],
                hash('sha256', $token), // Guardar hash del token
                $email,
                $expiresAt,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);

        } catch (\Exception $e) {
            Logger::error("[PasswordReset] Failed to create reset token: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Error al procesar la solicitud. Intenta de nuevo.'
            ], 500);
            return;
        }

        // Enviar email
        $resetUrl = \Screenart\Musedock\Env::get('APP_URL', 'https://musedock.net') . "/customer/reset-password?token={$token}";

        $emailSent = $this->sendResetEmail($customer['name'], $email, $resetUrl);

        if (!$emailSent) {
            Logger::error("[PasswordReset] Failed to send reset email to: {$email}");
            $this->jsonResponse([
                'success' => false,
                'error' => 'Error al enviar el email. Por favor, contacta con soporte.'
            ], 500);
            return;
        }

        Logger::info("[PasswordReset] Reset link sent to: {$email}");

        $this->jsonResponse([
            'success' => true,
            'message' => 'Se ha enviado un enlace de recuperación a tu email. Revisa tu bandeja de entrada.'
        ]);
    }

    /**
     * Mostrar formulario de reset de contraseña
     *
     * GET /customer/reset-password?token=xxx
     */
    public function showResetForm(): void
    {
        SessionSecurity::startSession();

        // Si ya está logueado, redirigir a dashboard
        if (isset($_SESSION['customer'])) {
            header('Location: /customer/dashboard');
            exit;
        }

        $token = $_GET['token'] ?? '';

        if (empty($token)) {
            $data = [
                'page_title' => 'Error - MuseDock',
                'error' => 'Token inválido o expirado'
            ];
            $this->render('Customer.auth.reset-password-error', $data);
            return;
        }

        // Verificar que el token exista y no haya expirado
        $tokenHash = hash('sha256', $token);
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT * FROM customer_password_resets
            WHERE token = ? AND used_at IS NULL AND expires_at > NOW()
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$tokenHash]);
        $resetRecord = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$resetRecord) {
            $data = [
                'page_title' => 'Error - MuseDock',
                'error' => 'El enlace de recuperación es inválido o ha expirado.'
            ];
            $this->render('Customer.auth.reset-password-error', $data);
            return;
        }

        $data = [
            'page_title' => 'Restablecer contraseña - MuseDock',
            'csrf_token' => csrf_token(),
            'token' => $token,
            'email' => $resetRecord['email']
        ];

        $this->render('Customer.auth.reset-password', $data);
    }

    /**
     * Procesar cambio de contraseña
     *
     * POST /customer/reset-password
     */
    public function resetPassword(): void
    {
        SessionSecurity::startSession();

        // Validar CSRF
        if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'error' => 'Token CSRF inválido'], 403);
            return;
        }

        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $passwordConfirmation = $_POST['password_confirmation'] ?? '';

        // Validaciones
        if (empty($token)) {
            $this->jsonResponse(['success' => false, 'error' => 'Token no proporcionado'], 400);
            return;
        }

        if (empty($password) || strlen($password) < 8) {
            $this->jsonResponse(['success' => false, 'error' => 'La contraseña debe tener al menos 8 caracteres'], 400);
            return;
        }

        if ($password !== $passwordConfirmation) {
            $this->jsonResponse(['success' => false, 'error' => 'Las contraseñas no coinciden'], 400);
            return;
        }

        // Verificar token en BD
        $tokenHash = hash('sha256', $token);
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            SELECT * FROM customer_password_resets
            WHERE token = ? AND used_at IS NULL AND expires_at > NOW()
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$tokenHash]);
        $resetRecord = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$resetRecord) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'El enlace de recuperación es inválido o ha expirado.'
            ], 400);
            return;
        }

        // Obtener customer
        $customer = Customer::find($resetRecord['customer_id']);
        if (!$customer) {
            $this->jsonResponse(['success' => false, 'error' => 'Usuario no encontrado'], 404);
            return;
        }

        // Actualizar contraseña
        try {
            $pdo->beginTransaction();

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE customers SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$passwordHash, $customer['id']]);

            // Marcar token como usado
            $stmt = $pdo->prepare("UPDATE customer_password_resets SET used_at = NOW() WHERE id = ?");
            $stmt->execute([$resetRecord['id']]);

            // Resetear intentos fallidos
            $stmt = $pdo->prepare("UPDATE customers SET failed_login_attempts = 0, last_failed_login_at = NULL WHERE id = ?");
            $stmt->execute([$customer['id']]);

            $pdo->commit();

            Logger::info("[PasswordReset] Password reset successful for customer ID: {$customer['id']}");

            $this->jsonResponse([
                'success' => true,
                'message' => '¡Contraseña actualizada! Redirigiendo al login...',
                'redirect' => '/customer/login'
            ]);

        } catch (\Exception $e) {
            $pdo->rollBack();
            Logger::error("[PasswordReset] Failed to reset password: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => 'Error al actualizar la contraseña. Intenta de nuevo.'
            ], 500);
        }
    }

    /**
     * Enviar email de recuperación
     *
     * @param string $name
     * @param string $email
     * @param string $resetUrl
     * @return bool
     */
    private function sendResetEmail(string $name, string $email, string $resetUrl): bool
    {
        $subject = 'Recupera tu contraseña - MuseDock';

        $body = "
Hola {$name},

Recibimos una solicitud para restablecer tu contraseña de MuseDock.

Haz clic en el siguiente enlace para crear una nueva contraseña:
{$resetUrl}

Este enlace expirará en 1 hora por seguridad.

Si no solicitaste este cambio, puedes ignorar este email de forma segura.

---
MuseDock - Tu plataforma de sitios web
https://musedock.net
        ";

        try {
            // Intentar enviar con SMTP si está configurado
            if (\Screenart\Musedock\Env::get('MAIL_MAILER') === 'smtp') {
                return \Screenart\Musedock\Mail\Mailer::send($email, $subject, $body);
            }

            // Fallback a mail() de PHP
            $headers = "From: MuseDock <noreply@musedock.net>\r\n";
            $headers .= "Reply-To: noreply@musedock.net\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();

            return mail($email, $subject, $body, $headers);

        } catch (\Exception $e) {
            Logger::error("[PasswordReset] Email send failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envía respuesta JSON
     */
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Renderiza vista Blade
     */
    private function render(string $view, array $data = []): void
    {
        $viewPath = str_replace('.', '/', $view);
        echo \Screenart\Musedock\View::renderTheme($viewPath, $data);
    }
}
