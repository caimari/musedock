<?php

namespace Blog\Traits;

use Screenart\Musedock\Database;

/**
 * Trait para filtrar posts/categorias/tags por scope del cross-publisher.
 * Usado en BlogPostController, BlogCategoryController, BlogTagController (Superadmin).
 */
trait CrossPublisherScope
{
    /**
     * Resolver el scope actual desde $_GET['scope'].
     * Retorna array con: mode, tenantIds, label, y datos adicionales segun el modo.
     */
    private function resolveTenantScope(): array
    {
        $scope = $_GET['scope'] ?? 'mine';

        if (!is_cross_publisher_active() || $scope === 'mine') {
            return ['mode' => 'mine', 'tenantIds' => [], 'label' => 'Mis posts'];
        }

        if (str_starts_with($scope, 'group:')) {
            $groupId = (int) substr($scope, 6);
            $group = \CrossPublisherAdmin\Models\DomainGroup::find($groupId);
            if (!$group) {
                return ['mode' => 'mine', 'tenantIds' => [], 'label' => 'Mis posts'];
            }
            $members = \CrossPublisherAdmin\Models\DomainGroup::getMembers($groupId);
            $tenantIds = array_map(fn($m) => $m->id, $members);
            return [
                'mode' => 'group',
                'groupId' => $groupId,
                'tenantIds' => $tenantIds,
                'label' => 'Grupo: ' . $group->name,
                'members' => $members,
            ];
        }

        if (str_starts_with($scope, 'tenant:')) {
            $tenantId = (int) substr($scope, 7);
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT id, name, domain FROM tenants WHERE id = ? AND group_id IS NOT NULL");
            $stmt->execute([$tenantId]);
            $tenant = $stmt->fetch(\PDO::FETCH_OBJ);
            if (!$tenant) {
                return ['mode' => 'mine', 'tenantIds' => [], 'label' => 'Mis posts'];
            }
            return [
                'mode' => 'tenant',
                'tenantIds' => [$tenant->id],
                'tenantId' => $tenantId,
                'label' => $tenant->domain,
                'tenant' => $tenant,
            ];
        }

        return ['mode' => 'mine', 'tenantIds' => [], 'label' => 'Mis posts'];
    }

    /**
     * Obtener datos para el dropdown de filtro del cross-publisher.
     * Solo retorna datos si el plugin esta activo.
     */
    private function getCrossPublisherFilterData(): array
    {
        if (!is_cross_publisher_active()) {
            return ['crossPublisherActive' => false, 'groups' => [], 'groupedTenants' => []];
        }

        $groups = \CrossPublisherAdmin\Models\DomainGroup::allWithCounts();

        $pdo = Database::connect();
        $stmt = $pdo->query("
            SELECT t.id, t.name, t.domain, t.group_id, dg.name as group_name
            FROM tenants t
            JOIN domain_groups dg ON t.group_id = dg.id
            WHERE t.status = 'active' AND t.group_id IS NOT NULL
            ORDER BY dg.name, t.domain
        ");
        $tenants = $stmt->fetchAll(\PDO::FETCH_OBJ);

        return [
            'crossPublisherActive' => true,
            'groups' => $groups,
            'groupedTenants' => $tenants,
            'currentScope' => $_GET['scope'] ?? 'mine',
        ];
    }

    /**
     * Construir un mapa tenantId => {id, name, domain} para mostrar en el listado.
     */
    private function buildTenantMap(array $tenantIds): array
    {
        if (empty($tenantIds)) return [];

        $pdo = Database::connect();
        $placeholders = implode(',', array_fill(0, count($tenantIds), '?'));
        $stmt = $pdo->prepare("SELECT id, name, domain FROM tenants WHERE id IN ({$placeholders})");
        $stmt->execute($tenantIds);

        $map = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_OBJ) as $t) {
            $map[$t->id] = $t;
        }
        return $map;
    }

    /**
     * Obtener el prefijo de blog de un tenant especifico.
     */
    private function getTenantBlogPrefixForScope(int $tenantId): ?string
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT value FROM tenant_settings WHERE tenant_id = ? AND key = 'blog_url_prefix'");
        $stmt->execute([$tenantId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$result) {
            return 'blog';
        }

        $value = $result['value'];
        if ($value === '' || $value === null) {
            return null;
        }

        return $value;
    }
}
