<?php

namespace Screenart\Musedock\Services;

class TenantManager
{
    /**
     * Detecta el tenant actual desde sesión o desde $GLOBALS
     */
    public static function currentTenantId(): ?int
    {
        // Prioriza la sesión (útil tras login o panel admin)
        if (isset($_SESSION['tenant_id'])) {
            return $_SESSION['tenant_id'];
        }

        // Si no está en sesión, pero sí en $GLOBALS (modo público/slug)
        if (isset($GLOBALS['tenant']['id'])) {
            // Sincroniza sesión para usos posteriores
            $_SESSION['tenant_id'] = $GLOBALS['tenant']['id'];
            return $GLOBALS['tenant']['id'];
        }

        // Si no hay tenant cargado, y multitenant está activo, seguir sin error
        return null;
    }

    /**
     * Establece el tenant manualmente y lo sincroniza globalmente
     */
    public static function setTenantId(?int $id): void
    {
        // Si ya hay sesión activa de admin, validar que coincida
        if (isset($_SESSION['admin']['tenant_id']) && $_SESSION['admin']['tenant_id'] !== $id) {
            error_log("⚠️ ALERTA DE SEGURIDAD: Intento de cambiar tenant_id de sesión activa. Admin: {$_SESSION['admin']['id']}, Tenant actual: {$_SESSION['admin']['tenant_id']}, Tenant solicitado: {$id}");

            // Destruir sesión y forzar nuevo login
            \Screenart\Musedock\Security\SessionSecurity::destroy();

            // Redirigir con mensaje de error
            if (headers_sent() === false) {
                header("Location: /" . admin_path() . "/login?error=wrong_tenant");
                exit;
            }
            return;
        }

        $_SESSION['tenant_id'] = $id;

        // Si existe el array global, actualiza solo el ID
        if (isset($GLOBALS['tenant']) && is_array($GLOBALS['tenant'])) {
            $GLOBALS['tenant']['id'] = $id;
        } else {
            // Si no existía, inicializa el array con ID y valor por defecto
            $GLOBALS['tenant'] = [
                'id' => $id,
                'theme' => config('default_theme', 'default'),
                'admin_path' => 'admin',
                'name' => 'Principal'
            ];
        }
    }

    /**
     * Retorna la info actual del tenant (si la hay)
     */
    public static function current(): ?array
    {
        return $GLOBALS['tenant'] ?? null;
    }
}
