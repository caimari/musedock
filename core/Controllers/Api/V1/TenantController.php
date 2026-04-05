<?php

namespace Screenart\Musedock\Controllers\Api\V1;

use Screenart\Musedock\Middlewares\ApiKeyAuth;
use Screenart\Musedock\Models\Tenant;
use Screenart\Musedock\Database;

class TenantController
{
    /**
     * GET /api/v1/tenants — List tenants accessible by this key.
     *
     * - Superadmin key with domain_group_id: only tenants in that group
     * - Superadmin key without group: all active tenants
     * - Tenant key: only its own tenant
     */
    public function index()
    {
        ApiKeyAuth::requirePermission('tenants.read');

        $key = ApiKeyAuth::key();
        $pdo = Database::connect();

        $allowedIds = $key->getAllowedTenantIds();

        if ($allowedIds === null) {
            // Unrestricted superadmin: all active tenants
            $stmt = $pdo->query("SELECT id, name, slug, domain, status FROM tenants WHERE status = 'active' ORDER BY name");
            $tenants = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } elseif (!empty($allowedIds)) {
            // Restricted to specific tenant IDs
            $placeholders = implode(',', array_fill(0, count($allowedIds), '?'));
            $stmt = $pdo->prepare("SELECT id, name, slug, domain, status FROM tenants WHERE id IN ({$placeholders}) AND status = 'active' ORDER BY name");
            $stmt->execute($allowedIds);
            $tenants = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            $tenants = [];
        }

        // Include domain group info if applicable
        $groupInfo = null;
        if (!empty($key->domain_group_id)) {
            $gStmt = $pdo->prepare("SELECT id, name, description FROM domain_groups WHERE id = ?");
            $gStmt->execute([$key->domain_group_id]);
            $groupInfo = $gStmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        }

        $response = [
            'success' => true,
            'tenants' => $tenants,
        ];

        if ($groupInfo) {
            $response['domain_group'] = $groupInfo;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }
}
