<?php

namespace Screenart\Musedock\Helpers;

/**
 * 🔒 SECURITY: Helper para validación de tenant_id
 * Previene: Cross-tenant data access, authorization bypass
 */
class TenantValidator
{
    /**
     * Validar que el tenant_id de entrada coincide con el tenant actual
     *
     * @param int|null $inputTenantId Tenant ID recibido (ej. de POST/GET)
     * @param int|null $currentTenantId Tenant ID de la sesión actual (opcional, se obtiene automáticamente)
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validate(?int $inputTenantId, ?int $currentTenantId = null): array
    {
        // Obtener tenant actual de sesión si no se provee
        if ($currentTenantId === null) {
            $currentTenantId = tenant_id();
        }

        // Si el usuario es superadmin, permitir cualquier tenant
        if (isset($_SESSION['super_admin'])) {
            return ['valid' => true, 'error' => null];
        }

        // Validar que ambos sean del mismo tipo (null o int)
        if (($inputTenantId === null) !== ($currentTenantId === null)) {
            return [
                'valid' => false,
                'error' => 'Acceso no autorizado: Discrepancia de tenant.'
            ];
        }

        // Si ambos son null (superadmin context), permitir
        if ($inputTenantId === null && $currentTenantId === null) {
            return ['valid' => true, 'error' => null];
        }

        // Validar que sean el mismo tenant
        if ($inputTenantId !== $currentTenantId) {
            error_log("⚠️ SECURITY ALERT: Tenant mismatch - Input: {$inputTenantId}, Current: {$currentTenantId}");
            return [
                'valid' => false,
                'error' => 'Acceso no autorizado: No tienes permisos para este tenant.'
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validar y redirigir si el tenant es inválido
     *
     * @param int|null $inputTenantId Tenant ID a validar
     * @param string $redirectUrl URL de redirección en caso de error
     * @return void (exit si inválido)
     */
    public static function validateOrRedirect(?int $inputTenantId, string $redirectUrl = null): void
    {
        $result = self::validate($inputTenantId);

        if (!$result['valid']) {
            if ($redirectUrl === null) {
                $redirectUrl = isset($_SESSION['admin']) ? '/' . admin_path() . '/dashboard' : '/';
            }

            flash('error', $result['error']);
            header("Location: {$redirectUrl}");
            exit;
        }
    }

    /**
     * Forzar tenant_id a ser el del usuario actual (previene manipulación)
     *
     * @param array &$data Array de datos (se modifica por referencia)
     * @param string $key Clave del tenant_id en el array (default: 'tenant_id')
     * @return void
     */
    public static function enforceCurrentTenant(array &$data, string $key = 'tenant_id'): void
    {
        $currentTenantId = tenant_id();

        // Solo forzar si no es superadmin
        if (!isset($_SESSION['super_admin'])) {
            $data[$key] = $currentTenantId;
        }
    }

    /**
     * Validar que un recurso pertenece al tenant actual
     *
     * @param int|null $resourceTenantId Tenant ID del recurso
     * @param string $resourceType Tipo de recurso (para logging)
     * @param int|null $resourceId ID del recurso (para logging)
     * @return bool
     */
    public static function validateResourceOwnership(?int $resourceTenantId, string $resourceType = 'resource', ?int $resourceId = null): bool
    {
        $currentTenantId = tenant_id();

        // Superadmin puede acceder a todo
        if (isset($_SESSION['super_admin'])) {
            return true;
        }

        // Validar ownership
        if ($resourceTenantId !== $currentTenantId) {
            $maskedId = $resourceId ? substr($resourceId, 0, 3) . '***' : 'N/A';
            error_log("⚠️ SECURITY ALERT: Cross-tenant access attempt - Resource: {$resourceType}, ID: {$maskedId}, Tenant: {$resourceTenantId} vs Current: {$currentTenantId}");
            return false;
        }

        return true;
    }
}
