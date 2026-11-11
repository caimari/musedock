<?php
namespace Screenart\Musedock\Security\Content;

use Screenart\Musedock\Security\SessionSecurity;

/** Authorization shared by tenant content controllers. Never trusts a posted tenant/owner. */
final class Access
{
    public static function deny(int $status = 403): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');
        echo json_encode(['success' => false, 'message' => $status === 404 ? 'Recurso no encontrado.' : 'Acceso denegado.']);
        exit;
    }

    public static function actor(?int $tenant): array
    {
        SessionSecurity::startSession();
        $actor = SessionSecurity::getAuthenticatedUser();
        $valid = $tenant === null
            ? ($actor['type'] ?? '') === 'super_admin'
            : (($actor['type'] ?? '') === 'super_admin' || (($actor['type'] ?? '') === 'admin' && (int)($actor['tenant_id'] ?? 0) === $tenant));
        if (!$valid || empty($actor['id']) || ($tenant !== null && $tenant < 1)) self::deny();
        if ($tenant !== null) $_SESSION['tenant_id'] = $tenant;
        return $actor;
    }

    public static function csrf(): void
    {
        SessionSecurity::startSession();
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? $_POST['_token'] ?? null;
        if ($token === null && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            $json = $GLOBALS['_JSON_INPUT'] ?? json_decode(file_get_contents('php://input'), true);
            if (is_array($json)) {
                $GLOBALS['_JSON_INPUT'] = $json;
                $token = $json['_csrf'] ?? $json['_token'] ?? null;
            }
        }
        if (!is_string($token) || !validate_csrf($token)) self::deny(419);
    }

    public static function permission(string $permission, ?int $tenant, bool $write = false): array
    {
        $actor = self::actor($tenant);
        if (!userCan($permission)) self::deny();
        if ($write) self::csrf();
        return $actor;
    }
}
