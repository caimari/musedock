<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Security\TwoFactorAuth;
use Screenart\Musedock\Traits\RequiresPermission;

/**
 * Controlador para gestión de Two-Factor Authentication
 */
class TwoFactorController
{
    use RequiresPermission;

    /**
     * Obtiene información del usuario autenticado
     * Soporta: super_admin, user (sin tenant), admin (sin tenant)
     */
    private function getAuthUser(): ?array
    {
        // Super Admin
        if (isset($_SESSION['super_admin'])) {
            return [
                'id' => $_SESSION['super_admin']['id'],
                'email' => $_SESSION['super_admin']['email'] ?? '',
                'type' => 'super_admin',
                'table' => 'super_admins'
            ];
        }

        // User sin tenant
        if (isset($_SESSION['user']) && is_null($_SESSION['user']['tenant_id'] ?? null)) {
            return [
                'id' => $_SESSION['user']['id'],
                'email' => $_SESSION['user']['email'] ?? '',
                'type' => 'user',
                'table' => 'users'
            ];
        }

        // Admin sin tenant
        if (isset($_SESSION['admin']) && is_null($_SESSION['admin']['tenant_id'] ?? null)) {
            return [
                'id' => $_SESSION['admin']['id'],
                'email' => $_SESSION['admin']['email'] ?? '',
                'type' => 'admin',
                'table' => 'admins'
            ];
        }

        return null;
    }

    /**
     * Mostrar página de configuración de 2FA
     */
    public function index()
    {
        SessionSecurity::startSession();

        $authUser = $this->getAuthUser();
        if (!$authUser) {
            header('Location: /musedock/login');
            exit;
        }

        $is2FAEnabled = TwoFactorAuth::isEnabled($authUser['id'], $authUser['type']);

        // Obtener cantidad de códigos restantes si 2FA está activo
        $remainingCodes = 0;
        if ($is2FAEnabled) {
            $remainingCodes = TwoFactorAuth::getRemainingRecoveryCodes($authUser['id'], $authUser['type']);
        }

        return View::renderSuperadmin('security.two-factor', [
            'title' => 'Autenticación de Dos Factores',
            'is_enabled' => $is2FAEnabled,
            'remaining_codes' => $remainingCodes,
        ]);
    }

    /**
     * Iniciar proceso de activación de 2FA
     */
    public function setup()
    {
        SessionSecurity::startSession();

        $authUser = $this->getAuthUser();
        if (!$authUser) {
            header('Location: /musedock/login');
            exit;
        }

        // Generar secreto temporal (se guardará al confirmar)
        $secret = TwoFactorAuth::generateSecret();
        $_SESSION['2fa_temp_secret'] = $secret;
        $_SESSION['2fa_user_type'] = $authUser['type'];

        // Generar códigos de recuperación
        $recoveryCodes = TwoFactorAuth::generateRecoveryCodes();
        $_SESSION['2fa_temp_recovery_codes'] = $recoveryCodes;

        // Generar URL para QR
        $qrCodeUrl = TwoFactorAuth::getQRCodeDataUri($secret, $authUser['email']);

        return View::renderSuperadmin('security.two-factor-setup', [
            'title' => 'Configurar 2FA',
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Confirmar activación de 2FA
     */
    public function enable()
    {
        SessionSecurity::startSession();

        // Validar CSRF
        if (!verify_csrf_token($_POST['_csrf'] ?? '')) {
            flash('error', 'Token de seguridad inválido.');
            header('Location: /musedock/security/2fa');
            exit;
        }

        $authUser = $this->getAuthUser();
        if (!$authUser) {
            header('Location: /musedock/login');
            exit;
        }

        $code = trim($_POST['code'] ?? '');
        $secret = $_SESSION['2fa_temp_secret'] ?? null;
        $recoveryCodes = $_SESSION['2fa_temp_recovery_codes'] ?? [];
        $userType = $_SESSION['2fa_user_type'] ?? $authUser['type'];

        if (!$secret) {
            flash('error', 'Sesión expirada. Inicia el proceso de nuevo.');
            header('Location: /musedock/security/2fa');
            exit;
        }

        // Verificar código
        if (!TwoFactorAuth::verifyCode($secret, $code)) {
            flash('error', 'Código incorrecto. Inténtalo de nuevo.');
            header('Location: /musedock/security/2fa/setup');
            exit;
        }

        // Activar 2FA
        $result = TwoFactorAuth::enable($authUser['id'], $secret, $recoveryCodes, $userType);

        if ($result) {
            // Limpiar datos temporales
            unset($_SESSION['2fa_temp_secret']);
            unset($_SESSION['2fa_temp_recovery_codes']);
            unset($_SESSION['2fa_user_type']);

            flash('success', 'Autenticación de dos factores activada correctamente.');
        } else {
            flash('error', 'Error al activar 2FA. Inténtalo de nuevo.');
        }

        header('Location: /musedock/security/2fa');
        exit;
    }

    /**
     * Deshabilitar 2FA
     */
    public function disable()
    {
        SessionSecurity::startSession();

        // Validar CSRF
        if (!verify_csrf_token($_POST['_csrf'] ?? '')) {
            flash('error', 'Token de seguridad inválido.');
            header('Location: /musedock/security/2fa');
            exit;
        }

        $authUser = $this->getAuthUser();
        if (!$authUser) {
            header('Location: /musedock/login');
            exit;
        }

        // Verificar contraseña actual
        $password = $_POST['password'] ?? '';
        if (!$this->verifyPassword($authUser['id'], $password, $authUser['table'])) {
            flash('error', 'Contraseña incorrecta.');
            header('Location: /musedock/security/2fa');
            exit;
        }

        $result = TwoFactorAuth::disable($authUser['id'], $authUser['type']);

        if ($result) {
            flash('success', '2FA desactivado correctamente.');
        } else {
            flash('error', 'Error al desactivar 2FA.');
        }

        header('Location: /musedock/security/2fa');
        exit;
    }

    /**
     * Regenerar códigos de recuperación
     */
    public function regenerateCodes()
    {
        SessionSecurity::startSession();

        // Validar CSRF
        if (!verify_csrf_token($_POST['_csrf'] ?? '')) {
            flash('error', 'Token de seguridad inválido.');
            header('Location: /musedock/security/2fa');
            exit;
        }

        $authUser = $this->getAuthUser();
        if (!$authUser) {
            header('Location: /musedock/login');
            exit;
        }

        // Verificar que 2FA esté habilitado
        if (!TwoFactorAuth::isEnabled($authUser['id'], $authUser['type'])) {
            flash('error', '2FA no está habilitado.');
            header('Location: /musedock/security/2fa');
            exit;
        }

        // Generar nuevos códigos
        $recoveryCodes = TwoFactorAuth::generateRecoveryCodes();
        $secret = TwoFactorAuth::getSecret($authUser['id'], $authUser['type']);

        // Actualizar en BD
        $result = TwoFactorAuth::enable($authUser['id'], $secret, $recoveryCodes, $authUser['type']);

        if ($result) {
            return View::renderSuperadmin('security.recovery-codes', [
                'title' => 'Nuevos Códigos de Recuperación',
                'recovery_codes' => $recoveryCodes,
            ]);
        }

        flash('error', 'Error al regenerar códigos.');
        header('Location: /musedock/security/2fa');
        exit;
    }

    /**
     * Página de verificación 2FA durante login
     */
    public function verify()
    {
        SessionSecurity::startSession();

        // Verificar que hay un login pendiente de 2FA
        if (!isset($_SESSION['2fa_pending_user_id'])) {
            header('Location: /musedock/login');
            exit;
        }

        return View::renderSuperadmin('security.two-factor-verify', [
            'title' => 'Verificación 2FA',
        ]);
    }

    /**
     * Procesar verificación 2FA durante login
     */
    public function verifyCode()
    {
        SessionSecurity::startSession();

        // Validar CSRF
        if (!verify_csrf_token($_POST['_csrf'] ?? '')) {
            flash('error', 'Token de seguridad inválido.');
            header('Location: /musedock/login/2fa');
            exit;
        }

        $userId = $_SESSION['2fa_pending_user_id'] ?? null;
        $userType = $_SESSION['2fa_pending_user_type'] ?? 'user';
        $userData = $_SESSION['2fa_pending_user_data'] ?? [];

        if (!$userId) {
            header('Location: /musedock/login');
            exit;
        }

        $code = trim($_POST['code'] ?? '');
        $isRecoveryCode = isset($_POST['use_recovery']) && $_POST['use_recovery'] === '1';

        $verified = false;

        if ($isRecoveryCode) {
            // Verificar código de recuperación
            $verified = TwoFactorAuth::verifyRecoveryCode($userId, $code, $userType);
        } else {
            // Verificar código TOTP
            $secret = TwoFactorAuth::getSecret($userId, $userType);
            if ($secret) {
                $verified = TwoFactorAuth::verifyCode($secret, $code);
            }
        }

        if ($verified) {
            // Completar login según el tipo de usuario
            $this->completeLogin($userType, $userData);

            // Actualizar último uso de 2FA
            $this->updateLastUsed($userId, $userType);

            // Limpiar datos temporales
            unset($_SESSION['2fa_pending_user_id']);
            unset($_SESSION['2fa_pending_user_type']);
            unset($_SESSION['2fa_pending_user_data']);

            flash('success', 'Bienvenido al panel de administración.');
            header('Location: /musedock/dashboard');
            exit;
        }

        flash('error', 'Código incorrecto. Inténtalo de nuevo.');
        header('Location: /musedock/login/2fa');
        exit;
    }

    /**
     * Completar login según tipo de usuario
     */
    private function completeLogin(string $userType, array $userData): void
    {
        switch ($userType) {
            case 'super_admin':
                $_SESSION['super_admin'] = $userData;
                break;
            case 'user':
                $_SESSION['user'] = $userData;
                break;
            case 'admin':
                $_SESSION['admin'] = $userData;
                break;
        }
    }

    /**
     * Verificar contraseña del usuario
     */
    private function verifyPassword(int $userId, string $password, string $table): bool
    {
        $db = \Screenart\Musedock\Database::connect();
        $stmt = $db->prepare("SELECT password FROM {$table} WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$result) {
            return false;
        }

        return password_verify($password, $result['password']);
    }

    /**
     * Actualizar timestamp de último uso de 2FA
     */
    private function updateLastUsed(int $userId, string $userType): void
    {
        $db = \Screenart\Musedock\Database::connect();

        $tables = [
            'super_admin' => 'super_admins',
            'user' => 'users',
            'admin' => 'admins'
        ];

        $table = $tables[$userType] ?? 'users';

        $stmt = $db->prepare("UPDATE {$table} SET two_factor_last_used_at = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
    }
}
