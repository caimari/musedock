<?php

namespace NodeAgent\Controllers;

use NodeAgent\Services\LocalTenantProvisioner;
use NodeAgent\Services\NodeTokenAuthService;
use Screenart\Musedock\Logger;

class NodeApiController
{
    private LocalTenantProvisioner $provisioner;
    private NodeTokenAuthService $tokenAuth;

    public function __construct()
    {
        $this->provisioner = new LocalTenantProvisioner();
        $this->tokenAuth = new NodeTokenAuthService();
    }

    public function health()
    {
        if (!$this->authenticate()) {
            return;
        }

        $this->json(200, [
            'success' => true,
            'service' => 'node-agent',
            'version' => NODE_AGENT_VERSION,
            'time' => gmdate('c'),
        ]);
    }

    public function createTenant()
    {
        if (!$this->authenticate()) {
            return;
        }

        $payload = $this->readJsonBody();
        $tenant = $payload['tenant'] ?? [];
        $admin = $payload['admin'] ?? [];

        $tenantName = trim((string) ($tenant['name'] ?? ''));
        $tenantDomain = trim((string) ($tenant['domain'] ?? ''));
        $adminEmail = trim((string) ($admin['email'] ?? ''));
        $adminName = trim((string) ($admin['name'] ?? ''));
        $adminPassword = (string) ($admin['password'] ?? '');

        if ($tenantName === '' || $tenantDomain === '' || $adminEmail === '' || $adminName === '' || $adminPassword === '') {
            $this->json(422, [
                'success' => false,
                'error' => 'Payload inválido: faltan tenant/admin obligatorios',
            ]);
            return;
        }

        if (!filter_var($tenantDomain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            $this->json(422, ['success' => false, 'error' => 'Dominio inválido']);
            return;
        }

        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $this->json(422, ['success' => false, 'error' => 'Email admin inválido']);
            return;
        }

        $tenantData = [
            'name' => $tenantName,
            'domain' => $tenantDomain,
            'admin_path' => $tenant['admin_path'] ?? 'admin',
            'is_active' => isset($tenant['is_active']) ? (int) $tenant['is_active'] : 1,
            'status' => $tenant['status'] ?? 'active',
            'theme' => $tenant['theme'] ?? 'default',
        ];

        $adminData = [
            'email' => $adminEmail,
            'name' => $adminName,
            'password' => $adminPassword,
        ];

        try {
            $result = $this->provisioner->createTenant($tenantData, $adminData);
            if (empty($result['success'])) {
                $this->json(500, [
                    'success' => false,
                    'error' => $result['error'] ?? 'Error al crear tenant local',
                ]);
                return;
            }

            $this->json(201, [
                'success' => true,
                'tenant_id' => $result['tenant_id'] ?? null,
                'admin_id' => $result['admin_id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Logger::log('[Node Agent] createTenant error: ' . $e->getMessage(), 'ERROR');
            $this->json(500, [
                'success' => false,
                'error' => 'Excepción interna al crear tenant',
            ]);
        }
    }

    private function authenticate(): bool
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            $this->json(401, ['success' => false, 'error' => 'Authorization Bearer requerido']);
            return false;
        }

        $token = trim((string) ($matches[1] ?? ''));
        if (!$this->tokenAuth->authenticate($token)) {
            $this->json(401, ['success' => false, 'error' => 'Token inválido']);
            return false;
        }

        return true;
    }

    private function readJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!$raw) {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function json(int $status, array $payload): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload);
    }
}
