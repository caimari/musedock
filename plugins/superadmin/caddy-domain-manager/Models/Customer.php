<?php

namespace CaddyDomainManager\Models;

use Screenart\Musedock\Database;

/**
 * Customer Model
 *
 * Modelo para la tabla customers (4to nivel de usuarios)
 * Métodos estáticos para operaciones CRUD básicas
 *
 * @package CaddyDomainManager
 */
class Customer
{
    /**
     * Busca customer por ID
     *
     * @param int $id
     * @return array|null Customer data o null si no existe
     */
    public static function find(int $id): ?array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT id, name, email, company, phone, country,
                   status, email_verified_at,
                   last_login_at, last_login_ip,
                   failed_login_attempts, locked_until,
                   created_at, updated_at
            FROM customers
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);

        $customer = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $customer ?: null;
    }

    /**
     * Busca customer por email
     *
     * @param string $email
     * @return array|null Customer data (incluye password hash) o null
     */
    public static function findByEmail(string $email): ?array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT * FROM customers WHERE email = ? LIMIT 1
        ");
        $stmt->execute([$email]);

        $customer = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $customer ?: null;
    }

    /**
     * Obtiene todos los tenants de un customer
     *
     * @param int $customerId
     * @return array Lista de tenants con datos básicos
     */
    public static function getTenants(int $customerId): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT
                id, domain, name, slug, plan, status,
                is_subdomain, parent_domain,
                cloudflare_proxied, cloudflare_configured_at,
                caddy_status,
                created_at, updated_at
            FROM tenants
            WHERE customer_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$customerId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Verifica email del customer
     *
     * @param int $customerId
     * @return bool Success
     */
    public static function verifyEmail(int $customerId): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            UPDATE customers SET
                email_verified_at = NOW(),
                email_verification_token = NULL,
                status = 'active'
            WHERE id = ?
        ");
        return $stmt->execute([$customerId]);
    }

    /**
     * Actualiza contraseña del customer
     *
     * @param int $customerId
     * @param string $newPassword Contraseña en texto plano (será hasheada)
     * @return bool Success
     */
    public static function updatePassword(int $customerId, string $newPassword): bool
    {
        $pdo = Database::connect();
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            UPDATE customers SET password = ? WHERE id = ?
        ");
        return $stmt->execute([$hashedPassword, $customerId]);
    }

    /**
     * Actualiza último login del customer
     *
     * @param int $customerId
     * @param string $ip IPv4 o IPv6
     * @return bool Success
     */
    public static function updateLastLogin(int $customerId, string $ip): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            UPDATE customers SET
                last_login_at = NOW(),
                last_login_ip = ?,
                failed_login_attempts = 0,
                locked_until = NULL
            WHERE id = ?
        ");
        return $stmt->execute([$ip, $customerId]);
    }

    /**
     * Incrementa intentos fallidos de login
     *
     * @param int $customerId
     * @param int $lockMinutes Si llega a 5 intentos, bloquear por N minutos
     * @return bool Success
     */
    public static function incrementFailedAttempts(int $customerId, int $lockMinutes = 15): bool
    {
        $pdo = Database::connect();

        // Incrementar contador
        $stmt = $pdo->prepare("
            UPDATE customers SET
                failed_login_attempts = failed_login_attempts + 1
            WHERE id = ?
        ");
        $stmt->execute([$customerId]);

        // Verificar si debe bloquearse
        $customer = self::find($customerId);
        if ($customer && $customer['failed_login_attempts'] >= 5) {
            $stmt = $pdo->prepare("
                UPDATE customers SET
                    locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE)
                WHERE id = ?
            ");
            return $stmt->execute([$lockMinutes, $customerId]);
        }

        return true;
    }

    /**
     * Verifica si customer está bloqueado
     *
     * @param int $customerId
     * @return bool true si está bloqueado
     */
    public static function isLocked(int $customerId): bool
    {
        $customer = self::find($customerId);

        if (!$customer || !$customer['locked_until']) {
            return false;
        }

        return strtotime($customer['locked_until']) > time();
    }

    /**
     * Obtiene estadísticas del customer
     *
     * @param int $customerId
     * @return array ['total_tenants', 'active_tenants', 'cloudflare_protected']
     */
    public static function getStats(int $customerId): array
    {
        $pdo = Database::connect();

        // Total de tenants
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tenants WHERE customer_id = ?");
        $stmt->execute([$customerId]);
        $totalTenants = (int) $stmt->fetchColumn();

        // Tenants activos
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tenants WHERE customer_id = ? AND status = 'active'");
        $stmt->execute([$customerId]);
        $activeTenants = (int) $stmt->fetchColumn();

        // Tenants protegidos con Cloudflare
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tenants WHERE customer_id = ? AND cloudflare_proxied = 1");
        $stmt->execute([$customerId]);
        $cloudflareProtected = (int) $stmt->fetchColumn();

        return [
            'total_tenants' => $totalTenants,
            'active_tenants' => $activeTenants,
            'cloudflare_protected' => $cloudflareProtected
        ];
    }
}
