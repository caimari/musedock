<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;

class PasswordResetController
{
    /**
     * Muestra el formulario de solicitud de recuperación de contraseña
     */
    public function showForgotForm()
    {
        // Asegurar que la sesión esté iniciada para generar el token CSRF
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Forzar generación del token CSRF
        csrf_token();

        return View::renderSuperadmin('auth.forgot-password');
    }

    /**
     * Envía el email con el enlace de recuperación
     */
    public function sendResetLinkEmail()
    {
        // Nota: El CSRF ya fue validado por el middleware global
        $email = trim($_POST['email'] ?? '');

        // Validar email
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return View::renderSuperadmin('auth.forgot-password', [
                'error' => 'Por favor, ingresa un email válido.'
            ]);
        }

        // Verificar si el email existe en super_admins
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT id, email, name FROM super_admins WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$user) {
                // Por seguridad, no revelamos si el email existe o no
                return View::renderSuperadmin('auth.forgot-password', [
                    'success' => 'Si el correo existe en nuestra base de datos, recibirás un enlace de recuperación.'
                ]);
            }

            // Generar token único
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Eliminar tokens anteriores para este email
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = :email");
            $stmt->execute([':email' => $email]);

            // Guardar nuevo token
            $stmt = $pdo->prepare("
                INSERT INTO password_resets (email, token, created_at)
                VALUES (:email, :token, NOW())
            ");
            $stmt->execute([
                ':email' => $email,
                ':token' => $token
            ]);

            // Generar URL de reset
            $resetUrl = $this->getBaseUrl() . '/musedock/password/reset?token=' . $token . '&email=' . urlencode($email);

            // Enviar email
            $sent = $this->sendEmail($user, $resetUrl, $token);

            if ($sent) {
                Logger::info("Password reset email sent to: " . $email);
                return View::renderSuperadmin('auth.forgot-password', [
                    'success' => 'Se ha enviado un enlace de recuperación a tu correo electrónico. El enlace expira en 1 hora.'
                ]);
            } else {
                Logger::error("Failed to send password reset email to: " . $email);
                return View::renderSuperadmin('auth.forgot-password', [
                    'error' => 'No se pudo enviar el correo. Por favor, contacta al administrador del sistema.'
                ]);
            }

        } catch (\Exception $e) {
            Logger::error("Password reset error: " . $e->getMessage());
            return View::renderSuperadmin('auth.forgot-password', [
                'error' => 'Ocurrió un error. Por favor, inténtalo de nuevo.'
            ]);
        }
    }

    /**
     * Muestra el formulario de restablecimiento de contraseña
     */
    public function showResetForm()
    {
        // Asegurar que la sesión esté iniciada para generar el token CSRF
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Forzar generación del token CSRF
        csrf_token();

        $token = $_GET['token'] ?? '';
        $email = $_GET['email'] ?? '';

        if (empty($token) || empty($email)) {
            return View::renderSuperadmin('auth.forgot-password', [
                'error' => 'Enlace de recuperación inválido.'
            ]);
        }

        // Verificar que el token sea válido y no haya expirado
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
                SELECT * FROM password_resets
                WHERE email = :email
                AND token = :token
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                LIMIT 1
            ");
            $stmt->execute([
                ':email' => $email,
                ':token' => $token
            ]);
            $resetRequest = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$resetRequest) {
                return View::renderSuperadmin('auth.forgot-password', [
                    'error' => 'El enlace de recuperación ha expirado o es inválido. Por favor, solicita uno nuevo.'
                ]);
            }

            // Mostrar formulario de reset
            return View::renderSuperadmin('auth.reset-password', [
                'token' => $token,
                'email' => $email
            ]);

        } catch (\Exception $e) {
            Logger::error("Password reset form error: " . $e->getMessage());
            return View::renderSuperadmin('auth.forgot-password', [
                'error' => 'Ocurrió un error. Por favor, inténtalo de nuevo.'
            ]);
        }
    }

    /**
     * Actualiza la contraseña del usuario
     */
    public function resetPassword()
    {
        // Nota: El CSRF ya fue validado por el middleware global
        $token = $_POST['token'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $passwordConfirmation = $_POST['password_confirmation'] ?? '';

        // Validaciones
        if (empty($token) || empty($email) || empty($password)) {
            return View::renderSuperadmin('auth.reset-password', [
                'error' => 'Todos los campos son obligatorios.',
                'token' => $token,
                'email' => $email
            ]);
        }

        if ($password !== $passwordConfirmation) {
            return View::renderSuperadmin('auth.reset-password', [
                'error' => 'Las contraseñas no coinciden.',
                'token' => $token,
                'email' => $email
            ]);
        }

        if (strlen($password) < 8) {
            return View::renderSuperadmin('auth.reset-password', [
                'error' => 'La contraseña debe tener al menos 8 caracteres.',
                'token' => $token,
                'email' => $email
            ]);
        }

        try {
            $pdo = Database::connect();
            $pdo->beginTransaction();

            // Verificar token
            $stmt = $pdo->prepare("
                SELECT * FROM password_resets
                WHERE email = :email
                AND token = :token
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                LIMIT 1
            ");
            $stmt->execute([
                ':email' => $email,
                ':token' => $token
            ]);
            $resetRequest = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$resetRequest) {
                $pdo->rollBack();
                return View::renderSuperadmin('auth.forgot-password', [
                    'error' => 'El enlace de recuperación ha expirado o es inválido.'
                ]);
            }

            // Actualizar contraseña
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE super_admins SET password = :password WHERE email = :email");
            $stmt->execute([
                ':password' => $hashedPassword,
                ':email' => $email
            ]);

            // Eliminar el token usado
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = :email");
            $stmt->execute([':email' => $email]);

            $pdo->commit();

            Logger::info("Password reset successful for: " . $email);

            // Redirigir al login con mensaje de éxito
            $_SESSION['password_reset_success'] = 'Tu contraseña ha sido restablecida correctamente. Ya puedes iniciar sesión.';
            header('Location: /musedock/login');
            exit;

        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::error("Password reset update error: " . $e->getMessage());
            return View::renderSuperadmin('auth.reset-password', [
                'error' => 'Ocurrió un error al restablecer la contraseña. Por favor, inténtalo de nuevo.',
                'token' => $token,
                'email' => $email
            ]);
        }
    }

    /**
     * Envía el email de recuperación
     */
    private function sendEmail($user, $resetUrl, $token)
    {
        $to = $user['email'];
        $subject = 'Recuperación de Contraseña - MuseDock CMS';
        $name = $user['name'] ?? 'Usuario';

        // HTML del email
        $htmlMessage = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            background: #f9fafb;
            border-radius: 10px;
            padding: 30px;
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #667eea;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .content {
            padding: 30px 0;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white !important;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 14px;
        }
        .warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
        }
        .token-box {
            background: #f3f4f6;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            word-break: break-all;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">MuseDock CMS</div>
        </div>

        <div class="content">
            <h2>Hola, ' . htmlspecialchars($name) . '</h2>

            <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta en MuseDock CMS.</p>

            <p>Haz clic en el siguiente botón para restablecer tu contraseña:</p>

            <div style="text-align: center;">
                <a href="' . htmlspecialchars($resetUrl) . '" class="button">Restablecer Contraseña</a>
            </div>

            <p>O copia y pega el siguiente enlace en tu navegador:</p>
            <div class="token-box">' . htmlspecialchars($resetUrl) . '</div>

            <div class="warning">
                <strong>⚠️ Importante:</strong> Este enlace expirará en 1 hora por seguridad.
            </div>

            <p>Si no solicitaste restablecer tu contraseña, puedes ignorar este correo de forma segura.</p>
        </div>

        <div class="footer">
            <p>Este es un mensaje automático, por favor no respondas a este correo.</p>
            <p>&copy; ' . date('Y') . ' MuseDock CMS. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>';

        // Versión texto plano
        $textMessage = "Hola, $name\n\n";
        $textMessage .= "Recibimos una solicitud para restablecer la contraseña de tu cuenta en MuseDock CMS.\n\n";
        $textMessage .= "Para restablecer tu contraseña, visita el siguiente enlace:\n";
        $textMessage .= "$resetUrl\n\n";
        $textMessage .= "Este enlace expirará en 1 hora por seguridad.\n\n";
        $textMessage .= "Si no solicitaste restablecer tu contraseña, puedes ignorar este correo.\n\n";
        $textMessage .= "Saludos,\nMuseDock CMS";

        // Headers del email
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"boundary\"\r\n";
        $headers .= "From: MuseDock CMS <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
        $headers .= "Reply-To: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        $message = "--boundary\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $message .= $textMessage . "\r\n";
        $message .= "--boundary\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $message .= $htmlMessage . "\r\n";
        $message .= "--boundary--";

        return mail($to, $subject, $message, $headers);
    }

    /**
     * Obtiene la URL base del sitio
     */
    private function getBaseUrl()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        return $protocol . '://' . $host;
    }
}
