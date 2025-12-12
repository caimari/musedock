<?php
namespace Screenart\Musedock\Controllers\Superadmin;
use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Security\SessionSecurity;

class AuthController
{
    public function login()
    {
        SessionSecurity::startSession();
        
        // Verificar si ya hay una sesión activa y redirigir al dashboard
        if (isset($_SESSION['super_admin']) || isset($_SESSION['admin']) || isset($_SESSION['user'])) {
            header('Location: /musedock/dashboard');
            exit;
        }
        
        // Intentar restaurar sesión desde token "recordarme" si no hay sesión activa
        if (SessionSecurity::checkRemembered()) {
            header('Location: /musedock/dashboard');
            exit;
        }
        
        return View::renderSuperadmin('auth.login', [
            'title' => __('login_title') ?? 'Iniciar sesión'
        ]);
    }

    public function authenticate()
    {
        SessionSecurity::startSession();
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $rememberMe = isset($_POST['remember']); // Verificar si se marcó "Recordarme"
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Validar que se hayan enviado credenciales
        if (empty($email) || empty($password)) {
            flash('error', __('auth.please_enter_credentials'));
            header('Location: /musedock/login');
            exit;
        }

        // --- RATE LIMITING CON DOBLE BLOQUEO ---
        $identifier = $email . '|' . $ip;
        $rateCheck = \Screenart\Musedock\Security\RateLimiter::checkDual($identifier, $email);

        if (!$rateCheck['allowed']) {
            flash('error', $rateCheck['message']);
            header('Location: /musedock/login');
            exit;
        }

        // Si detectamos ataque distribuido, mostrar advertencia pero permitir login
        if ($rateCheck['reason'] === 'under_attack') {
            flash('warning', __('auth.account_under_attack'));
        }
        // ----------------------

        // 🔒 SECURITY: Hash email antes de loguear para prevenir information disclosure
        $emailHash = substr(hash('sha256', $email), 0, 8);
        error_log("Intento de login en Superadmin (email hash: {$emailHash})");

        $db = Database::connect();

        // 1. Verificar Superadmin
        $stmt = $db->prepare("SELECT * FROM super_admins WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['password'])) {
            // 🔒 SECURITY: No loguear email completo
            error_log("Autenticación OK para SUPERADMIN (hash: {$emailHash})");

            // Limpiar intentos fallidos (específico + global)
            \Screenart\Musedock\Security\RateLimiter::clearDual($identifier, $email);

            SessionSecurity::regenerate();
            $_SESSION['super_admin'] = [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'] ?? $email,
                'role' => $user['role'] ?? 'superadmin',
                'avatar' => $user['avatar'] ?? null
            ];

            // Si se marcó "Recordarme", crear token y marcar sesión como persistente
            if ($rememberMe) {
                SessionSecurity::rememberSuperAdmin($user['id']);
                $_SESSION['persistent'] = true;
            }

            header('Location: /musedock/dashboard');
            exit;
        }
        
        // 2. Verificar Admin global (tenant_id NULL)
        $stmt = $db->prepare("SELECT * FROM admins WHERE email = :email AND tenant_id IS NULL LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['password'])) {
            // 🔒 SECURITY: No loguear email completo
            error_log("Autenticación OK para ADMIN (hash: {$emailHash})");

            // Limpiar intentos fallidos (específico + global)
            \Screenart\Musedock\Security\RateLimiter::clearDual($identifier, $email);

            SessionSecurity::regenerate();
            $_SESSION['admin'] = [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'] ?? $email,
                'tenant_id' => null,
                'role' => 'admin',
                'avatar' => $user['avatar'] ?? null
            ];

            // Si se marcó "Recordarme", crear token y marcar sesión como persistente
            if ($rememberMe) {
                SessionSecurity::rememberAdmin($user['id']);
                $_SESSION['persistent'] = true;
            }

            header('Location: /musedock/dashboard');
            exit;
        }

        // 3. Verificar Usuario CMS global (tenant_id NULL)
        $stmt = $db->prepare("SELECT * FROM users WHERE email = :email AND tenant_id IS NULL LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['password'])) {
            // 🔒 SECURITY: No loguear email completo
            error_log("Autenticación OK para USER (hash: {$emailHash})");

            // Limpiar intentos fallidos (específico + global)
            \Screenart\Musedock\Security\RateLimiter::clearDual($identifier, $email);

            SessionSecurity::regenerate();
            $_SESSION['user'] = [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'] ?? $email,
                'tenant_id' => null,
                'avatar' => $user['avatar'] ?? null
            ];

            // Si se marcó "Recordarme", crear token y marcar sesión como persistente
            if ($rememberMe) {
                SessionSecurity::rememberUser($user['id']);
                $_SESSION['persistent'] = true;
            }

            header('Location: /musedock/dashboard');
            exit;
        }

        // Fallo de autenticación - Incrementar contador (específico + global)
        $attempts = \Screenart\Musedock\Security\RateLimiter::incrementDual($identifier, $email);
        $remaining = \Screenart\Musedock\Security\RateLimiter::remaining($identifier);

        // 🔒 SECURITY: No loguear email completo
        error_log("Login fallido en Superadmin (hash: {$emailHash}). Intentos restantes: {$remaining}, Global: {$attempts['global_attempts']}");

        if ($remaining > 0) {
            flash('error', __('auth.invalid_credentials_attempts', ['attempts' => $remaining]));
        } else {
            flash('error', __('auth.invalid_credentials'));
        }

        header('Location: /musedock/login');
        exit;
    }

    public function logout()
    {
        SessionSecurity::startSession();

        // Eliminar la sesión y los tokens
        SessionSecurity::destroy();

        // Nueva sesión para flash message
        session_start();
        flash('logout_success', __('auth.logout_success'));
        header("Location: /musedock/login");
        exit;
    }
}