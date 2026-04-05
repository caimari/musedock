<?php

namespace Screenart\Musedock\Controllers\Api\V1;

use Screenart\Musedock\Models\ApiKey;
use Screenart\Musedock\Database;
use Screenart\Musedock\View;
use Screenart\Musedock\Services\TenantManager;

/**
 * Panel controller for managing API keys (not an API endpoint itself).
 * Used by both Superadmin and Tenant admin panels.
 */
class ApiKeyController
{
    private bool $isSuperAdmin;

    public function __construct()
    {
        $this->isSuperAdmin = isset($_SESSION['super_admin']);
    }

    /**
     * List API keys.
     */
    public function index()
    {
        $this->checkAccess();

        $pdo = Database::connect();

        if ($this->isSuperAdmin) {
            // Superadmin sees all keys, with tenant and group names
            $stmt = $pdo->query("
                SELECT ak.*, t.name as tenant_name, t.domain as tenant_domain,
                       dg.name as group_name
                FROM api_keys ak
                LEFT JOIN tenants t ON t.id = ak.tenant_id
                LEFT JOIN domain_groups dg ON dg.id = ak.domain_group_id
                ORDER BY ak.created_at DESC
            ");
        } else {
            $tenantId = TenantManager::currentTenantId();
            $stmt = $pdo->prepare("SELECT * FROM api_keys WHERE tenant_id = ? ORDER BY created_at DESC");
            $stmt->execute([$tenantId]);
        }

        $keys = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Parse permissions JSON
        foreach ($keys as &$k) {
            $k['permissions_list'] = json_decode($k['permissions'] ?? '[]', true) ?: [];
        }

        $data = [
            'title'  => 'API Keys',
            'keys'   => $keys,
            'flash'  => consume_flash('success') ?: consume_flash('error'),
        ];

        if ($this->isSuperAdmin) {
            echo View::renderSuperadmin('settings.api-keys', $data);
        } else {
            echo View::renderTenantAdmin('settings.api-keys', $data);
        }
    }

    /**
     * Create a new API key (POST).
     */
    public function store()
    {
        $this->checkAccess();

        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            flash('error', 'El nombre de la API key es obligatorio.');
            $this->redirect();
            return;
        }

        // Permissions
        $permissions = $_POST['permissions'] ?? [];
        if (!is_array($permissions)) {
            $permissions = [$permissions];
        }

        // Rate limit
        $rateLimit = max(1, min(1000, (int)($_POST['rate_limit'] ?? 60)));

        // Expiry
        $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

        // Resolve scope: tenant_id and domain_group_id
        $tenantId = null;
        $domainGroupId = null;

        if ($this->isSuperAdmin) {
            $keyType = $_POST['key_type'] ?? 'group';
            if ($keyType === 'tenant') {
                $tenantId = !empty($_POST['tenant_id']) ? (int)$_POST['tenant_id'] : null;
            } elseif ($keyType === 'group') {
                $domainGroupId = !empty($_POST['domain_group_id']) ? (int)$_POST['domain_group_id'] : null;
            }
            // keyType === 'superadmin' → both null (unrestricted)
        } else {
            // Tenant admin: always locked to their own tenant
            $tenantId = TenantManager::currentTenantId();
        }

        // Generate key
        $keyData = ApiKey::generateKey();

        ApiKey::create([
            'tenant_id'       => $tenantId,
            'domain_group_id' => $domainGroupId,
            'name'            => $name,
            'api_key_hash'    => $keyData['hash'],
            'permissions'     => json_encode($permissions),
            'rate_limit'      => $rateLimit,
            'expires_at'      => $expiresAt,
            'is_active'       => 1,
        ]);

        // Store the raw key in session so we can show it ONCE
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION['_new_api_key'] = $keyData['raw'];

        flash('success', 'API key creada correctamente. Copia la key ahora — no se mostrará de nuevo.');
        $this->redirect();
    }

    /**
     * Toggle active/inactive.
     */
    public function toggle(int $id)
    {
        $this->checkAccess();

        $key = $this->findKey($id);
        if (!$key) {
            flash('error', 'API key no encontrada.');
            $this->redirect();
            return;
        }

        $key->update(['is_active' => $key->is_active ? 0 : 1]);

        flash('success', $key->is_active ? 'API key activada.' : 'API key desactivada.');
        $this->redirect();
    }

    /**
     * Delete an API key.
     */
    public function destroy(int $id)
    {
        $this->checkAccess();

        $key = $this->findKey($id);
        if (!$key) {
            flash('error', 'API key no encontrada.');
            $this->redirect();
            return;
        }

        $key->delete();
        flash('success', 'API key eliminada.');
        $this->redirect();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function checkAccess(): void
    {
        if ($this->isSuperAdmin) return;

        if (!isset($_SESSION['admin'])) {
            header('Location: /admin/login');
            exit;
        }

        if (!userCan('settings.edit')) {
            flash('error', 'No tienes permiso para gestionar API keys.');
            header('Location: ' . admin_url('dashboard'));
            exit;
        }
    }

    private function findKey(int $id): ?ApiKey
    {
        $key = ApiKey::find($id);
        if (!$key) return null;

        // Tenant isolation
        if (!$this->isSuperAdmin) {
            $tenantId = TenantManager::currentTenantId();
            if ((int)$key->tenant_id !== $tenantId) return null;
        }

        return $key;
    }

    private function redirect(): void
    {
        if ($this->isSuperAdmin) {
            header('Location: /musedock/settings/api-keys');
        } else {
            header('Location: /' . admin_path() . '/settings/api-keys');
        }
        exit;
    }

    /**
     * Available permissions for the UI.
     */
    public static function availablePermissions(): array
    {
        return [
            // Posts
            'posts.read'        => 'Leer posts',
            'posts.create'      => 'Crear posts',
            'posts.update'      => 'Editar posts',
            'posts.delete'      => 'Eliminar posts',
            // Categories
            'categories.read'   => 'Leer categorias',
            'categories.create' => 'Crear categorias',
            'categories.update' => 'Editar categorias',
            'categories.delete' => 'Eliminar categorias',
            // Tags
            'tags.read'         => 'Leer tags',
            'tags.create'       => 'Crear tags',
            'tags.update'       => 'Editar tags',
            'tags.delete'       => 'Eliminar tags',
            // Pages
            'pages.read'        => 'Leer paginas',
            'pages.create'      => 'Crear paginas',
            'pages.update'      => 'Editar paginas',
            'pages.delete'      => 'Eliminar paginas',
            // System
            'tenants.read'      => 'Listar tenants (superadmin)',
            'cross-publish'     => 'Cross-publicar posts',
            '*'                 => 'Acceso total (todos los permisos)',
        ];
    }

    /**
     * Permission groups for the UI (cleaner display).
     */
    public static function permissionGroups(): array
    {
        $groups = [
            'Paginas' => ['pages.read', 'pages.create', 'pages.update', 'pages.delete'],
        ];

        // Blog permissions only if blog module is active
        if (function_exists('is_module_active') && is_module_active('blog')) {
            $groups['Posts']      = ['posts.read', 'posts.create', 'posts.update', 'posts.delete'];
            $groups['Categorias'] = ['categories.read', 'categories.create', 'categories.update', 'categories.delete'];
            $groups['Tags']       = ['tags.read', 'tags.create', 'tags.update', 'tags.delete'];
        }

        // System permissions
        $sistema = ['tenants.read', '*'];
        if (function_exists('is_cross_publisher_active') && is_cross_publisher_active()) {
            array_splice($sistema, 1, 0, ['cross-publish']);
        }
        $groups['Sistema'] = $sistema;

        return $groups;
    }
}
