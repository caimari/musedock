<?php

namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\Database;
use Screenart\Musedock\View;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Security\TwoFactorAuth;
use Screenart\Musedock\Services\AuditLogger;

/**
 * Mi perfil → Verificación en dos pasos (TOTP) del admin del tenant.
 *
 * Activar (QR + confirmar código), desactivar (contraseña + código) y
 * regenerar códigos de recuperación. El reto en el login está en AuthController.
 */
class TwoFactorController
{
    public function index()
    {
        $admin = $this->currentAdmin();

        $enabled = !empty($admin['two_factor_enabled']);
        $secret = null;
        $otpauthUrl = null;

        if (!$enabled) {
            // Secreto provisional: solo se guarda en la cuenta al confirmar un código válido
            $secret = $_SESSION['tenant_2fa_setup_secret'] ?? null;
            if (!$secret || ($_SESSION['tenant_2fa_setup_admin'] ?? null) !== (int) $admin['id']) {
                $secret = TwoFactorAuth::generateSecret();
                $_SESSION['tenant_2fa_setup_secret'] = $secret;
                $_SESSION['tenant_2fa_setup_admin'] = (int) $admin['id'];
            }
            $issuer = 'MuseDock · ' . ($GLOBALS['tenant']['domain'] ?? 'panel');
            $otpauthUrl = TwoFactorAuth::getQRCodeUrl($secret, $admin['email'], $issuer);
        }

        $recoveryCodes = $_SESSION['tenant_2fa_show_codes'] ?? null;
        unset($_SESSION['tenant_2fa_show_codes']);

        return View::renderTenantAdmin('profile.two-factor', [
            'title'          => 'Verificación en dos pasos',
            'enabled'        => $enabled,
            'enabledAt'      => $admin['two_factor_enabled_at'] ?? null,
            'lastUsedAt'     => $admin['two_factor_last_used_at'] ?? null,
            'remainingCodes' => $enabled ? TwoFactorAuth::getRemainingRecoveryCodes((int) $admin['id'], 'admin') : 0,
            'secret'         => $secret,
            'otpauthUrl'     => $otpauthUrl,
            'recoveryCodes'  => $recoveryCodes,
            'adminBase'      => '/' . admin_path(),
        ]);
    }

    public function enable()
    {
        $admin = $this->currentAdmin();
        $secret = $_SESSION['tenant_2fa_setup_secret'] ?? null;

        if (!empty($admin['two_factor_enabled'])) {
            $this->back();
        }
        if (!$secret || ($_SESSION['tenant_2fa_setup_admin'] ?? null) !== (int) $admin['id']) {
            flash('error', 'La configuración ha caducado. Escanea el código de nuevo.');
            $this->back();
        }

        $code = preg_replace('/\s+/', '', (string) ($_POST['code'] ?? ''));
        if (!preg_match('/^\d{6}$/', $code) || !TwoFactorAuth::verifyCode($secret, $code)) {
            flash('error', 'El código no es correcto. Comprueba la hora del móvil y vuelve a intentarlo.');
            $this->back();
        }

        $recoveryCodes = TwoFactorAuth::generateRecoveryCodes();
        if (!TwoFactorAuth::enable((int) $admin['id'], $secret, $recoveryCodes, 'admin')) {
            flash('error', 'No se pudo activar la verificación en dos pasos.');
            $this->back();
        }

        unset($_SESSION['tenant_2fa_setup_secret'], $_SESSION['tenant_2fa_setup_admin']);
        $_SESSION['tenant_2fa_show_codes'] = $recoveryCodes;

        AuditLogger::log('2fa.enabled', 'admin', (int) $admin['id']);
        flash('success', 'Verificación en dos pasos activada. Guarda los códigos de recuperación.');
        $this->back();
    }

    public function disable()
    {
        $admin = $this->currentAdmin();

        if (!$this->verifyPasswordAndCode($admin)) {
            flash('error', 'Contraseña o código incorrectos.');
            $this->back();
        }

        TwoFactorAuth::disable((int) $admin['id'], 'admin');
        AuditLogger::log('2fa.disabled', 'admin', (int) $admin['id']);
        flash('success', 'Verificación en dos pasos desactivada.');
        $this->back();
    }

    public function regenerateCodes()
    {
        $admin = $this->currentAdmin();

        if (empty($admin['two_factor_enabled']) || !$this->verifyPasswordAndCode($admin)) {
            flash('error', 'Contraseña o código incorrectos.');
            $this->back();
        }

        $recoveryCodes = TwoFactorAuth::generateRecoveryCodes();
        $secret = TwoFactorAuth::getSecret((int) $admin['id'], 'admin');
        TwoFactorAuth::enable((int) $admin['id'], $secret, $recoveryCodes, 'admin');
        $_SESSION['tenant_2fa_show_codes'] = $recoveryCodes;

        AuditLogger::log('2fa.recovery_regenerated', 'admin', (int) $admin['id']);
        flash('success', 'Nuevos códigos de recuperación generados. Los anteriores ya no sirven.');
        $this->back();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function verifyPasswordAndCode(array $admin): bool
    {
        if (!password_verify((string) ($_POST['password'] ?? ''), (string) $admin['password'])) {
            return false;
        }

        $code = preg_replace('/\s+/', '', (string) ($_POST['code'] ?? ''));
        $secret = TwoFactorAuth::getSecret((int) $admin['id'], 'admin');

        return (preg_match('/^\d{6}$/', $code) && $secret && TwoFactorAuth::verifyCode($secret, $code))
            || ($code !== '' && TwoFactorAuth::verifyRecoveryCode((int) $admin['id'], $code, 'admin'));
    }

    private function currentAdmin(): array
    {
        SessionSecurity::startSession();

        $sessionAdmin = $_SESSION['admin'] ?? null;
        if (!is_array($sessionAdmin) || (int) ($sessionAdmin['tenant_id'] ?? 0) !== (int) tenant_id()) {
            flash('error', 'La verificación en dos pasos solo está disponible para administradores del sitio.');
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }

        $stmt = Database::connect()->prepare("SELECT * FROM admins WHERE id = ? AND tenant_id = ? LIMIT 1");
        $stmt->execute([(int) $sessionAdmin['id'], (int) tenant_id()]);
        $admin = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$admin) {
            header('Location: /' . admin_path() . '/login');
            exit;
        }

        return $admin;
    }

    private function back(): never
    {
        header('Location: /' . admin_path() . '/profile/2fa');
        exit;
    }
}
