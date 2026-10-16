<?php

namespace Screenart\Musedock\Security;

use Screenart\Musedock\Database;

/**
 * Confirma la contraseña del administrador con sesión iniciada antes de acciones destructivas
 * (borrar una cuenta DNS, desconectar Search Console…). Máx. 5 fallos cada 10 minutos por sesión.
 */
class PasswordConfirmation
{
    public static function verify(string $password, ?\PDO $pdo = null): bool
    {
        $user = SessionSecurity::getAuthenticatedUser();
        $table = ['super_admin' => 'super_admins', 'admin' => 'admins'][$user['type'] ?? ''] ?? null;
        if (!$user || !$table || $password === '') {
            return false;
        }
        $now = time();
        $fails = array_values(array_filter((array) ($_SESSION['pw_confirm_fails'] ?? []), fn ($t) => $t > $now - 600));
        if (count($fails) >= 5) {
            $_SESSION['pw_confirm_fails'] = $fails;

            return false;
        }
        $pdo ??= Database::connect();
        $stmt = $pdo->prepare("SELECT password FROM {$table} WHERE id = ?");
        $stmt->execute([(int) $user['id']]);
        $hash = (string) $stmt->fetchColumn();
        $ok = $hash !== '' && password_verify($password, $hash);
        if (!$ok) {
            $fails[] = $now;
        }
        $_SESSION['pw_confirm_fails'] = $fails;

        return $ok;
    }
}
