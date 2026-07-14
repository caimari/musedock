<?php

namespace NodeOrchestrator\Controllers;

use NodeOrchestrator\Services\ProvisioningService;
use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\View;
use PDO;

class TenantProvisionController
{
    use RequiresPermission;

    private function checkMultitenancyEnabled(): void
    {
        $config = require APP_ROOT . '/config/config.php';
        $multitenantEnabled = $config['multi_tenant']['enabled'] ?? false;

        if (!$multitenantEnabled) {
            flash('error', 'La funcionalidad de multitenancy no está habilitada.');
            header('Location: /musedock/dashboard');
            exit;
        }
    }

    private function validateTenantInput(&$name, &$domain, &$status): bool
    {
        $name = htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $domain = trim($_POST['domain'] ?? '');
        $status = trim($_POST['status'] ?? 'active');

        if (!$name || !$domain) {
            flash('error', 'Todos los campos son obligatorios.');
            return false;
        }

        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            flash('error', 'El dominio introducido no es válido.');
            return false;
        }

        if (mb_strlen($name) < 3 || mb_strlen($name) > 100) {
            flash('error', 'El nombre debe tener entre 3 y 100 caracteres.');
            return false;
        }

        return true;
    }

    public function create()
    {
        $this->checkMultitenancyEnabled();
        SessionSecurity::startSession();
        $this->checkPermission('tenants.manage');

        echo View::renderModule('node-orchestrator', 'superadmin.tenants.create', [
            'title' => __('tenant_create_title') ?? 'Nuevo Tenant',
            'nodes' => $this->getCmsNodes(),
        ]);
    }

    public function store()
    {
        $this->checkMultitenancyEnabled();
        SessionSecurity::startSession();
        $this->checkPermission('tenants.manage');

        if (!$this->validateTenantInput($name, $domain, $status)) {
            header('Location: /musedock/tenants/create');
            exit;
        }

        $adminEmail = trim($_POST['admin_email'] ?? '');
        $adminName = trim($_POST['admin_name'] ?? '');
        $adminPassword = $_POST['admin_password'] ?? '';
        $requestedNodeId = isset($_POST['node_id']) && $_POST['node_id'] !== ''
            ? (int) $_POST['node_id']
            : null;

        if (!$adminEmail || !$adminName || !$adminPassword) {
            flash('error', 'Todos los datos del administrador son obligatorios.');
            header('Location: /musedock/tenants/create');
            exit;
        }

        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'El email del administrador no es válido.');
            header('Location: /musedock/tenants/create');
            exit;
        }

        $existing = Database::table('tenants')->where('domain', $domain)->first();
        if ($existing) {
            flash('error', 'El dominio ya existe.');
            header('Location: /musedock/tenants/create');
            exit;
        }

        try {
            $provisioner = new ProvisioningService();
            $result = $provisioner->provisionTenant(
                [
                    'name' => $name,
                    'domain' => $domain,
                    'admin_path' => 'admin',
                    'is_active' => $status === 'active' ? 1 : 0,
                    'status' => $status,
                ],
                [
                    'email' => $adminEmail,
                    'name' => $adminName,
                    'password' => $adminPassword,
                ],
                $requestedNodeId
            );

            if (empty($result['success'])) {
                throw new \RuntimeException($result['error'] ?? 'Error desconocido');
            }

            $mode = !empty($result['remote']) ? 'nodo remoto' : 'nodo local';
            flash('success', "Tenant y administrador creados correctamente ({$mode}).");
            header('Location: /musedock/tenants');
            exit;
        } catch (\Throwable $e) {
            Logger::log('[Node Orchestrator] Error al crear tenant: ' . $e->getMessage(), 'ERROR');
            flash('error', 'Error al guardar en la base de datos: ' . $e->getMessage());
            header('Location: /musedock/tenants/create');
            exit;
        }
    }

    private function getCmsNodes(): array
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $check = $pdo->query("SHOW TABLES LIKE 'nodes'");
            if (!$check || !$check->fetch(PDO::FETCH_ASSOC)) {
                return [];
            }
        } else {
            $check = $pdo->prepare("
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = 'public'
                  AND table_name = 'nodes'
                LIMIT 1
            ");
            $check->execute();
            if (!$check->fetchColumn()) {
                return [];
            }
        }

        $stmt = $pdo->query("
            SELECT id, name, slug, type, status, is_local, is_default, current_accounts, max_accounts
            FROM nodes
            WHERE type IN ('cms', 'hybrid')
            ORDER BY is_local DESC, is_default DESC, id ASC
        ");

        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }
}
