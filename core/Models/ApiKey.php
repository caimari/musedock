<?php

namespace Screenart\Musedock\Models;

use Screenart\Musedock\Database\Model;

class ApiKey extends Model
{
    protected static string $table = 'api_keys';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'tenant_id',
        'domain_group_id',
        'name',
        'api_key_hash',
        'permissions',
        'rate_limit',
        'request_count_minute',
        'last_request_at',
        'last_used_at',
        'expires_at',
        'is_active',
    ];

    protected array $casts = [
        'id'                   => 'int',
        'tenant_id'            => 'nullable',
        'domain_group_id'      => 'nullable',
        'rate_limit'           => 'int',
        'request_count_minute' => 'int',
        'is_active'            => 'boolean',
        'permissions'          => 'array',
    ];

    /**
     * Generate a new API key with the mdk_ prefix.
     * Returns the raw key (shown once) and its SHA-256 hash.
     */
    public static function generateKey(): array
    {
        $raw = 'mdk_' . bin2hex(random_bytes(20)); // mdk_ + 40 hex chars
        $hash = hash('sha256', $raw);
        return ['raw' => $raw, 'hash' => $hash];
    }

    /**
     * Find an API key by its raw value (hashes it first).
     */
    public static function findByRawKey(string $rawKey): ?self
    {
        $hash = hash('sha256', $rawKey);
        $row = \Screenart\Musedock\Database::table(static::$table)
            ->where('api_key_hash', $hash)
            ->first();
        return $row ? new static($row) : null;
    }

    /**
     * Check if this key has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        $perms = $this->getPermissions();
        if (in_array('*', $perms)) return true;

        // Check exact match
        if (in_array($permission, $perms)) return true;

        // Check wildcard (e.g. "posts.*" matches "posts.create")
        $parts = explode('.', $permission);
        if (count($parts) === 2) {
            if (in_array($parts[0] . '.*', $perms)) return true;
        }

        return false;
    }

    /**
     * Get permissions as array.
     */
    public function getPermissions(): array
    {
        $perms = $this->permissions;
        if (is_string($perms)) {
            $perms = json_decode($perms, true);
        }
        return is_array($perms) ? $perms : [];
    }

    /**
     * Check if the key is a superadmin key (tenant_id = null).
     */
    public function isSuperadmin(): bool
    {
        return $this->tenant_id === null || $this->tenant_id === '' || $this->tenant_id === 0;
    }

    /**
     * Check if the key is expired.
     */
    public function isExpired(): bool
    {
        if (empty($this->expires_at)) return false;
        return strtotime($this->expires_at) < time();
    }

    /**
     * Get the list of tenant IDs this key is allowed to access.
     *
     * - Tenant key: only its own tenant_id
     * - Superadmin key WITH domain_group_id: only tenants in that group
     * - Superadmin key WITHOUT domain_group_id: all tenants (unrestricted)
     *
     * Returns null if unrestricted, or an array of allowed tenant IDs.
     */
    public function getAllowedTenantIds(): ?array
    {
        // Tenant-specific key: locked to one tenant
        if (!$this->isSuperadmin()) {
            return [(int) $this->tenant_id];
        }

        // Superadmin key restricted to a domain group
        if (!empty($this->domain_group_id)) {
            $pdo = \Screenart\Musedock\Database::connect();
            $stmt = $pdo->prepare("SELECT id FROM tenants WHERE group_id = ? AND status = 'active'");
            $stmt->execute([$this->domain_group_id]);
            return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
        }

        // Unrestricted superadmin key
        return null;
    }

    /**
     * Check if this key can access a specific tenant.
     */
    public function canAccessTenant(int $tenantId): bool
    {
        $allowed = $this->getAllowedTenantIds();
        if ($allowed === null) return true; // unrestricted
        return in_array($tenantId, $allowed);
    }

    /**
     * Check rate limit. Returns true if allowed, false if exceeded.
     * Also increments the counter.
     */
    public function checkRateLimit(): bool
    {
        $now = date('Y-m-d H:i:s');
        $lastRequest = $this->last_request_at;

        // Reset counter if we're in a new minute
        if (!$lastRequest || date('Y-m-d H:i', strtotime($lastRequest)) !== date('Y-m-d H:i')) {
            $this->request_count_minute = 1;
            $this->last_request_at = $now;
            $this->last_used_at = $now;
            $this->save();
            return true;
        }

        // Check if within limit
        if ($this->request_count_minute >= $this->rate_limit) {
            return false;
        }

        // Increment counter
        $this->request_count_minute = $this->request_count_minute + 1;
        $this->last_request_at = $now;
        $this->last_used_at = $now;
        $this->save();
        return true;
    }
}
