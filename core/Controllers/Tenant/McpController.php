<?php

namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\Database;
use Screenart\Musedock\View;
use Screenart\Musedock\Models\ApiKey;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Services\AuditLogger;
use Screenart\Musedock\Services\Mcp\McpPermissions;
use Screenart\Musedock\Traits\RequiresPermission;

/**
 * Panel del tenant: Ajustes → Conexiones IA (MCP).
 *
 * Activar/desactivar el servidor MCP del sitio, crear tokens con permisos
 * por sección × nivel, editarlos, revocarlos y ver la actividad reciente.
 */
class McpController
{
    use RequiresPermission;

    private const EXPIRY_OPTIONS = ['30' => '30 días', '90' => '90 días', '365' => '1 año', 'never' => 'Nunca'];

    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.view');
        $tenantId = $this->tenantIdOrRedirect();

        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            SELECT k.*, c.client_uri AS oauth_client_uri, u.name AS oauth_user_name,
                   (SELECT MAX(t.created_at) FROM oauth_tokens t WHERE t.api_key_id = k.id) AS oauth_last_token_at
            FROM api_keys k
            LEFT JOIN oauth_clients c ON c.client_id = k.oauth_client_id AND c.tenant_id = k.tenant_id
            LEFT JOIN admins u ON u.id = k.user_id AND k.user_id > 0 AND k.auth_type = 'oauth'
            WHERE k.tenant_id = ?
            ORDER BY k.is_active DESC, k.created_at DESC
        ");
        $stmt->execute([$tenantId]);
        $keys = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($keys as &$k) {
            $perms = json_decode($k['permissions'] ?? '[]', true) ?: [];
            $k['permissions_list'] = $perms;
            $k['matrix'] = McpPermissions::summarize($perms);
            $k['template'] = McpPermissions::detectTemplate($perms);
            $k['is_expired'] = !empty($k['expires_at']) && strtotime($k['expires_at']) < time();
        }
        unset($k);

        $activity = [];
        try {
            $stmt = $pdo->prepare("
                SELECT l.tool_name, l.status_code, l.success, l.duration_ms, l.ip_address, l.created_at, l.path, l.source,
                       k.name AS key_name
                FROM api_tool_logs l
                LEFT JOIN api_keys k ON k.id = l.api_key_id
                WHERE l.tenant_id = ?
                ORDER BY l.created_at DESC
                LIMIT 50
            ");
            $stmt->execute([$tenantId]);
            $activity = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // tabla sin migrar
        }

        $newKey = $_SESSION['_new_mcp_key'] ?? null;
        unset($_SESSION['_new_mcp_key']);

        $domain = $GLOBALS['tenant']['domain'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');

        return View::renderTenantAdmin('mcp.index', [
            'title'          => 'Conexiones IA (MCP)',
            'enabled'        => (string) tenant_setting('mcp_enabled', '0') === '1',
            'globalEnabled'  => filter_var(\Screenart\Musedock\Env::get('MCP_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            'keys'           => $keys,
            'activity'       => $activity,
            'sections'       => McpPermissions::sections(),
            'levels'         => McpPermissions::LEVELS,
            'templates'      => McpPermissions::TEMPLATES,
            'templateMatrix' => array_map(fn($t) => McpPermissions::templateMatrix($t), array_combine(array_keys(McpPermissions::TEMPLATES), array_keys(McpPermissions::TEMPLATES))),
            'expiryOptions'  => self::EXPIRY_OPTIONS,
            'newKey'         => $newKey,
            'mcpUrl'         => 'https://' . $domain . '/mcp',
            'adminBase'      => '/' . admin_path(),
            'canEdit'        => userCan('settings.edit'),
        ]);
    }

    public function toggle()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');
        $tenantId = $this->tenantIdOrRedirect();

        $enable = ($_POST['enabled'] ?? '0') === '1';
        set_tenant_setting('mcp_enabled', $enable ? '1' : '0');

        AuditLogger::log($enable ? 'mcp.enabled' : 'mcp.disabled', 'tenant', $tenantId);
        flash('success', $enable ? 'Servidor MCP activado para este sitio.' : 'Servidor MCP desactivado. Ninguna conexión podrá acceder.');
        $this->back();
    }

    public function store()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');
        $tenantId = $this->tenantIdOrRedirect();

        $name = trim(strip_tags($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            flash('error', 'Indica un nombre para la conexión (máx. 100 caracteres).');
            $this->back();
        }

        $permissions = $this->permissionsFromRequest();
        if (!$permissions) {
            flash('error', 'Selecciona al menos un permiso.');
            $this->back();
        }

        $allowedIps = $this->allowedIpsFromRequest();
        if ($allowedIps === false) {
            flash('error', 'Lista de IPs no válida. Usa IPs o rangos CIDR separados por comas.');
            $this->back();
        }

        $expiry = $_POST['expires'] ?? '90';
        $expiresAt = isset(self::EXPIRY_OPTIONS[$expiry]) && $expiry !== 'never'
            ? date('Y-m-d H:i:s', strtotime('+' . (int) $expiry . ' days'))
            : null;

        $keyData = ApiKey::generateKey();

        $key = ApiKey::create([
            'tenant_id'       => $tenantId,
            'domain_group_id' => null,
            'name'            => $name,
            'api_key_hash'    => $keyData['hash'],
            'permissions'     => json_encode($permissions),
            'rate_limit'      => max(10, min(300, (int) ($_POST['rate_limit'] ?? 60))),
            'expires_at'      => $expiresAt,
            'is_active'       => 1,
            'allowed_ips'     => $allowedIps ?: null,
        ]);

        $_SESSION['_new_mcp_key'] = ['raw' => $keyData['raw'], 'name' => $name];

        AuditLogger::log('mcp.token_created', 'api_key', (int) $key->id, ['name' => $name, 'permissions' => $permissions, 'expires_at' => $expiresAt]);
        \Screenart\Musedock\Services\Mcp\McpNotifier::connectionChanged($tenantId, 'created', $name, 'token', $permissions,
            $_SESSION['admin']['name'] ?? null, $_SESSION['admin']['email'] ?? null);
        flash('success', 'Conexión creada. Copia el token ahora: no se volverá a mostrar.');
        $this->back();
    }

    public function update(int $id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');
        $key = $this->findKeyOrBack($id);

        $permissions = $this->permissionsFromRequest();
        if (!$permissions) {
            flash('error', 'Selecciona al menos un permiso.');
            $this->back();
        }

        $allowedIps = $this->allowedIpsFromRequest();
        if ($allowedIps === false) {
            flash('error', 'Lista de IPs no válida. Usa IPs o rangos CIDR separados por comas.');
            $this->back();
        }

        $name = trim(strip_tags($_POST['name'] ?? ''));

        $key->update([
            'name'        => ($name !== '' && mb_strlen($name) <= 100) ? $name : $key->name,
            'permissions' => json_encode($permissions),
            'allowed_ips' => $allowedIps ?: null,
        ]);

        AuditLogger::log('mcp.token_updated', 'api_key', $id, ['permissions' => $permissions]);
        \Screenart\Musedock\Services\Mcp\McpNotifier::connectionChanged((int) tenant_id(), 'updated', (string) $key->name,
            ($key->auth_type ?? 'token') === 'oauth' ? 'oauth' : 'token', $permissions,
            $_SESSION['admin']['name'] ?? null, $_SESSION['admin']['email'] ?? null);
        flash('success', 'Permisos actualizados. Se aplican en la siguiente petición de la conexión.');
        $this->back();
    }

    public function revoke(int $id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');
        $key = $this->findKeyOrBack($id);

        $name = $key->name;
        if (($key->auth_type ?? 'token') === 'oauth') {
            \Screenart\Musedock\Services\Mcp\OAuthServer::revokeGrantTokens($id);
        }
        $key->delete();

        AuditLogger::log('mcp.token_revoked', 'api_key', $id, ['name' => $name]);
        flash('success', "Conexión \"{$name}\" revocada. El token ha dejado de funcionar.");
        $this->back();
    }

    /**
     * Revoca de golpe todas las conexiones del sitio (tokens y OAuth).
     */
    public function revokeAll()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');
        $tenantId = $this->tenantIdOrRedirect();

        if (($_POST['confirm'] ?? '') !== 'REVOCAR') {
            flash('error', 'Escribe REVOCAR para confirmar.');
            $this->back();
        }

        $pdo = Database::connect();
        $ids = $pdo->prepare("SELECT id FROM api_keys WHERE tenant_id = ?");
        $ids->execute([$tenantId]);
        $ids = array_map('intval', $ids->fetchAll(\PDO::FETCH_COLUMN));

        foreach ($ids as $id) {
            \Screenart\Musedock\Services\Mcp\OAuthServer::revokeGrantTokens($id);
        }
        $pdo->prepare("DELETE FROM api_keys WHERE tenant_id = ?")->execute([$tenantId]);

        AuditLogger::log('mcp.all_revoked', 'tenant', $tenantId, ['count' => count($ids)]);
        flash('success', count($ids) . ' conexión(es) revocada(s). Ningún asistente ni token tiene ya acceso al sitio.');
        $this->back();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function permissionsFromRequest(): array
    {
        $template = $_POST['template'] ?? 'custom';
        if (isset(McpPermissions::TEMPLATES[$template])) {
            return McpPermissions::toPermissions(McpPermissions::templateMatrix($template));
        }

        $matrix = $_POST['perm'] ?? [];
        return is_array($matrix) ? McpPermissions::toPermissions($matrix) : [];
    }

    /**
     * @return string|false Lista normalizada, '' si vacía, false si inválida
     */
    private function allowedIpsFromRequest(): string|false
    {
        $entries = array_filter(array_map('trim', preg_split('/[\s,]+/', (string) ($_POST['allowed_ips'] ?? ''))));
        foreach ($entries as $entry) {
            [$ip, $bits] = array_pad(explode('/', $entry, 2), 2, null);
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                return false;
            }
            $max = str_contains($ip, ':') ? 128 : 32;
            if ($bits !== null && (!ctype_digit($bits) || (int) $bits > $max)) {
                return false;
            }
        }
        return implode(', ', $entries);
    }

    private function findKeyOrBack(int $id): ApiKey
    {
        $key = ApiKey::find($id);
        if (!$key || (int) $key->tenant_id !== (int) tenant_id()) {
            flash('error', 'Conexión no encontrada.');
            $this->back();
        }
        return $key;
    }

    private function tenantIdOrRedirect(): int
    {
        $tenantId = tenant_id();
        if (!$tenantId) {
            flash('error', 'No se ha detectado el tenant actual');
            header('Location: /' . admin_path());
            exit;
        }
        return (int) $tenantId;
    }

    private function back(): never
    {
        header('Location: /' . admin_path() . '/mcp');
        exit;
    }
}
