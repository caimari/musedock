<?php

namespace CaddyDomainManager\Controllers;

use Screenart\Musedock\Database;
use Screenart\Musedock\View;
use Screenart\Musedock\Logger;
use PDO;
use Exception;

/**
 * TenantAdminController
 *
 * Gestiona las credenciales de admin de los tenants del customer.
 * Permite ver, cambiar email y password de los admins de cada sitio.
 *
 * @package CaddyDomainManager\Controllers
 */
class TenantAdminController
{
    /**
     * Mostrar panel de gestión de admins de tenants
     */
    public function index(): void
    {
        $customerId = $_SESSION['customer']['id'] ?? null;

        if (!$customerId) {
            header('Location: /customer/login');
            exit;
        }

        try {
            $pdo = Database::connect();

            // Obtener todos los tenants del customer con sus credenciales
            $stmt = $pdo->prepare("
                SELECT
                    t.id as tenant_id,
                    t.domain,
                    t.name as tenant_name,
                    t.status as tenant_status,
                    t.plan,
                    t.created_at as tenant_created_at,
                    ctc.id as credential_id,
                    ctc.admin_id,
                    ctc.admin_email,
                    ctc.initial_password,
                    ctc.password_changed,
                    ctc.last_password_change,
                    a.name as admin_name
                FROM tenants t
                LEFT JOIN customer_tenant_credentials ctc ON ctc.tenant_id = t.id AND ctc.customer_id = ?
                LEFT JOIN admins a ON a.id = ctc.admin_id
                WHERE t.customer_id = ?
                ORDER BY t.created_at DESC
            ");
            $stmt->execute([$customerId, $customerId]);
            $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Para tenants sin credenciales registradas, obtener el admin directamente
            foreach ($tenants as &$tenant) {
                if (empty($tenant['admin_id'])) {
                    $stmt = $pdo->prepare("
                        SELECT id, email, name FROM admins
                        WHERE tenant_id = ? AND is_root_admin = 1
                        LIMIT 1
                    ");
                    $stmt->execute([$tenant['tenant_id']]);
                    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($admin) {
                        $tenant['admin_id'] = $admin['id'];
                        $tenant['admin_email'] = $admin['email'];
                        $tenant['admin_name'] = $admin['name'];
                        $tenant['initial_password'] = null;
                        $tenant['password_changed'] = true; // Asumir que ya cambió si no hay registro
                    }
                }
            }

            echo View::renderTheme('Customer.tenant-admins', [
                'customer' => $_SESSION['customer'],
                'tenants' => $tenants,
                'csrf_token' => csrf_token()
            ]);

        } catch (Exception $e) {
            Logger::error("[TenantAdmin] Error loading admin panel: " . $e->getMessage());
            $_SESSION['flash_error'] = 'Error al cargar el panel';
            header('Location: /customer/dashboard');
            exit;
        }
    }

    /**
     * Cambiar email del admin de un tenant (AJAX)
     */
    public function changeEmail(int $tenantId): void
    {
        header('Content-Type: application/json');

        try {
            if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
                $this->jsonResponse(['success' => false, 'error' => 'Token inválido'], 403);
                return;
            }

            $customerId = $_SESSION['customer']['id'] ?? null;
            if (!$customerId) {
                $this->jsonResponse(['success' => false, 'error' => 'Sesión expirada'], 401);
                return;
            }

            $newEmail = strtolower(trim($_POST['email'] ?? ''));
            $currentPassword = $_POST['current_password'] ?? '';

            if (empty($newEmail) || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $this->jsonResponse(['success' => false, 'error' => 'Email no válido'], 400);
                return;
            }

            $pdo = Database::connect();

            // Verificar que el tenant pertenece al customer
            $stmt = $pdo->prepare("
                SELECT t.*, a.id as admin_id, a.password as admin_password_hash
                FROM tenants t
                INNER JOIN admins a ON a.tenant_id = t.id AND a.is_root_admin = 1
                WHERE t.id = ? AND t.customer_id = ?
            ");
            $stmt->execute([$tenantId, $customerId]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tenant) {
                $this->jsonResponse(['success' => false, 'error' => 'Tenant no encontrado'], 404);
                return;
            }

            // Verificar que el email no esté en uso por otro admin
            $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
            $stmt->execute([$newEmail, $tenant['admin_id']]);
            if ($stmt->fetch()) {
                $this->jsonResponse(['success' => false, 'error' => 'El email ya está en uso'], 400);
                return;
            }

            // Actualizar email en admins
            $stmt = $pdo->prepare("UPDATE admins SET email = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newEmail, $tenant['admin_id']]);

            // Actualizar en customer_tenant_credentials si existe
            $stmt = $pdo->prepare("
                UPDATE customer_tenant_credentials
                SET admin_email = ?, updated_at = NOW()
                WHERE customer_id = ? AND tenant_id = ?
            ");
            $stmt->execute([$newEmail, $customerId, $tenantId]);

            Logger::info("[TenantAdmin] Email changed for tenant {$tenantId}, new email: {$newEmail}");

            $this->jsonResponse([
                'success' => true,
                'message' => 'Email actualizado correctamente',
                'new_email' => $newEmail
            ]);

        } catch (Exception $e) {
            Logger::error("[TenantAdmin] Error changing email: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Cambiar password del admin de un tenant (AJAX)
     */
    public function changePassword(int $tenantId): void
    {
        header('Content-Type: application/json');

        try {
            if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
                $this->jsonResponse(['success' => false, 'error' => 'Token inválido'], 403);
                return;
            }

            $customerId = $_SESSION['customer']['id'] ?? null;
            if (!$customerId) {
                $this->jsonResponse(['success' => false, 'error' => 'Sesión expirada'], 401);
                return;
            }

            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (strlen($newPassword) < 8) {
                $this->jsonResponse(['success' => false, 'error' => 'La contraseña debe tener al menos 8 caracteres'], 400);
                return;
            }

            if ($newPassword !== $confirmPassword) {
                $this->jsonResponse(['success' => false, 'error' => 'Las contraseñas no coinciden'], 400);
                return;
            }

            $pdo = Database::connect();

            // Verificar que el tenant pertenece al customer
            $stmt = $pdo->prepare("
                SELECT t.*, a.id as admin_id
                FROM tenants t
                INNER JOIN admins a ON a.tenant_id = t.id AND a.is_root_admin = 1
                WHERE t.id = ? AND t.customer_id = ?
            ");
            $stmt->execute([$tenantId, $customerId]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tenant) {
                $this->jsonResponse(['success' => false, 'error' => 'Tenant no encontrado'], 404);
                return;
            }

            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            // Actualizar password en admins
            $stmt = $pdo->prepare("UPDATE admins SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hashedPassword, $tenant['admin_id']]);

            // Actualizar en customer_tenant_credentials
            $stmt = $pdo->prepare("
                UPDATE customer_tenant_credentials
                SET admin_password_hash = ?,
                    initial_password = NULL,
                    password_changed = 1,
                    last_password_change = NOW(),
                    updated_at = NOW()
                WHERE customer_id = ? AND tenant_id = ?
            ");
            $stmt->execute([$hashedPassword, $customerId, $tenantId]);

            Logger::info("[TenantAdmin] Password changed for tenant {$tenantId}");

            $this->jsonResponse([
                'success' => true,
                'message' => 'Contraseña actualizada correctamente'
            ]);

        } catch (Exception $e) {
            Logger::error("[TenantAdmin] Error changing password: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Regenerar password del admin (genera uno nuevo aleatorio) (AJAX)
     */
    public function regeneratePassword(int $tenantId): void
    {
        header('Content-Type: application/json');

        try {
            if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
                $this->jsonResponse(['success' => false, 'error' => 'Token inválido'], 403);
                return;
            }

            $customerId = $_SESSION['customer']['id'] ?? null;
            if (!$customerId) {
                $this->jsonResponse(['success' => false, 'error' => 'Sesión expirada'], 401);
                return;
            }

            $pdo = Database::connect();

            // Verificar que el tenant pertenece al customer
            $stmt = $pdo->prepare("
                SELECT t.*, a.id as admin_id, a.email as admin_email
                FROM tenants t
                INNER JOIN admins a ON a.tenant_id = t.id AND a.is_root_admin = 1
                WHERE t.id = ? AND t.customer_id = ?
            ");
            $stmt->execute([$tenantId, $customerId]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tenant) {
                $this->jsonResponse(['success' => false, 'error' => 'Tenant no encontrado'], 404);
                return;
            }

            // Generar nuevo password
            $newPassword = $this->generateSecurePassword(12);
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            // Actualizar en admins
            $stmt = $pdo->prepare("UPDATE admins SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hashedPassword, $tenant['admin_id']]);

            // Actualizar o insertar en customer_tenant_credentials
            $stmt = $pdo->prepare("
                INSERT INTO customer_tenant_credentials
                (customer_id, tenant_id, admin_id, admin_email, admin_password_hash, initial_password, password_changed, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
                ON DUPLICATE KEY UPDATE
                    admin_password_hash = VALUES(admin_password_hash),
                    initial_password = VALUES(initial_password),
                    password_changed = 0,
                    last_password_change = NOW(),
                    updated_at = NOW()
            ");
            $stmt->execute([
                $customerId,
                $tenantId,
                $tenant['admin_id'],
                $tenant['admin_email'],
                $hashedPassword,
                $newPassword
            ]);

            Logger::info("[TenantAdmin] Password regenerated for tenant {$tenantId}");

            $this->jsonResponse([
                'success' => true,
                'message' => 'Nueva contraseña generada',
                'new_password' => $newPassword
            ]);

        } catch (Exception $e) {
            Logger::error("[TenantAdmin] Error regenerating password: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Generar contraseña segura
     */
    private function generateSecurePassword(int $length = 12): string
    {
        $uppercase = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lowercase = 'abcdefghjkmnpqrstuvwxyz';
        $numbers = '23456789';
        $special = '!@#$%&*';

        $password = '';
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];

        $allChars = $uppercase . $lowercase . $numbers . $special;
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        return str_shuffle($password);
    }

    /**
     * JSON response helper
     */
    private function jsonResponse(array $data, int $code = 200): void
    {
        http_response_code($code);
        echo json_encode($data);
    }
}
