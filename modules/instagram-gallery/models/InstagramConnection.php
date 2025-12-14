<?php

namespace Modules\InstagramGallery\Models;

use PDO;
use Exception;

class InstagramConnection
{
    private static ?PDO $pdo = null;

    public int $id;
    public ?int $tenant_id;
    public ?int $user_id;
    public string $instagram_user_id;
    public string $username;
    public ?string $profile_picture;
    public string $access_token;
    public ?string $refresh_token;
    public string $token_expires_at;
    public int $is_active;
    public ?string $last_synced_at;
    public ?string $last_error;
    public ?string $created_at;
    public ?string $updated_at;

    public static function setPdo(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    private static function getPdo(): PDO
    {
        if (self::$pdo === null) {
            throw new Exception('PDO instance not set. Call setPdo() first.');
        }
        return self::$pdo;
    }

    /**
     * Find connection by ID
     */
    public static function find(int $id): ?self
    {
        $pdo = self::getPdo();
        $stmt = $pdo->prepare('SELECT * FROM instagram_connections WHERE id = ?');
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        return self::hydrate($data);
    }

    /**
     * Get all connections by tenant
     */
    public static function getByTenant(?int $tenantId = null, bool $includeGlobal = true): array
    {
        $pdo = self::getPdo();

        if ($tenantId !== null) {
            if ($includeGlobal) {
                $stmt = $pdo->prepare('
                    SELECT * FROM instagram_connections
                    WHERE (tenant_id = ? OR tenant_id IS NULL OR tenant_id = 0)
                    ORDER BY created_at DESC
                ');
                $stmt->execute([$tenantId]);
            } else {
                $stmt = $pdo->prepare('
                    SELECT * FROM instagram_connections
                    WHERE tenant_id = ?
                    ORDER BY created_at DESC
                ');
                $stmt->execute([$tenantId]);
            }
        } else {
            $stmt = $pdo->query('
                SELECT * FROM instagram_connections
                WHERE tenant_id IS NULL OR tenant_id = 0
                ORDER BY created_at DESC
            ');
        }

        $connections = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $connections[] = self::hydrate($row);
        }

        return $connections;
    }

    /**
     * Get active connections by tenant
     */
    public static function getActiveByTenant(?int $tenantId = null): array
    {
        $pdo = self::getPdo();

        if ($tenantId !== null) {
            $stmt = $pdo->prepare('
                SELECT * FROM instagram_connections
                WHERE (tenant_id = ? OR tenant_id IS NULL OR tenant_id = 0)
                AND is_active = 1
                AND token_expires_at > NOW()
                ORDER BY created_at DESC
            ');
            $stmt->execute([$tenantId]);
        } else {
            $stmt = $pdo->query('
                SELECT * FROM instagram_connections
                WHERE (tenant_id IS NULL OR tenant_id = 0)
                AND is_active = 1
                AND token_expires_at > NOW()
                ORDER BY created_at DESC
            ');
        }

        $connections = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $connections[] = self::hydrate($row);
        }

        return $connections;
    }

    /**
     * Find connection by Instagram user ID and tenant
     */
    public static function findByInstagramUserId(string $instagramUserId, ?int $tenantId = null): ?self
    {
        $pdo = self::getPdo();

        if ($tenantId !== null) {
            $stmt = $pdo->prepare('
                SELECT * FROM instagram_connections
                WHERE instagram_user_id = ? AND tenant_id = ?
            ');
            $stmt->execute([$instagramUserId, $tenantId]);
        } else {
            $stmt = $pdo->prepare('
                SELECT * FROM instagram_connections
                WHERE instagram_user_id = ? AND (tenant_id IS NULL OR tenant_id = 0)
            ');
            $stmt->execute([$instagramUserId]);
        }

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? self::hydrate($data) : null;
    }

    /**
     * Get connections that need token refresh (expiring in 7 days)
     */
    public static function getExpiringSoon(int $daysThreshold = 7): array
    {
        $pdo = self::getPdo();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $stmt = $pdo->prepare('
                SELECT * FROM instagram_connections
                WHERE is_active = 1
                AND token_expires_at <= DATE_ADD(NOW(), INTERVAL ? DAY)
                ORDER BY token_expires_at ASC
            ');
        } else {
            $stmt = $pdo->prepare('
                SELECT * FROM instagram_connections
                WHERE is_active = 1
                AND token_expires_at <= NOW() + INTERVAL \':days days\'
                ORDER BY token_expires_at ASC
            ');
        }

        $stmt->execute([$daysThreshold]);

        $connections = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $connections[] = self::hydrate($row);
        }

        return $connections;
    }

    /**
     * Create new connection
     */
    public static function create(array $data): self
    {
        $pdo = self::getPdo();

        $stmt = $pdo->prepare('
            INSERT INTO instagram_connections (
                tenant_id, user_id, instagram_user_id, username,
                profile_picture, access_token, refresh_token,
                token_expires_at, is_active, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ');

        $stmt->execute([
            $data['tenant_id'] ?? null,
            $data['user_id'] ?? null,
            $data['instagram_user_id'],
            $data['username'],
            $data['profile_picture'] ?? null,
            $data['access_token'],
            $data['refresh_token'] ?? null,
            $data['token_expires_at'],
            $data['is_active'] ?? 1
        ]);

        $id = (int) $pdo->lastInsertId();
        return self::find($id);
    }

    /**
     * Update connection
     */
    public function update(array $data): bool
    {
        $pdo = self::getPdo();

        $fields = [];
        $values = [];

        $allowedFields = [
            'username', 'profile_picture', 'access_token', 'refresh_token',
            'token_expires_at', 'is_active', 'last_synced_at', 'last_error'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = 'updated_at = NOW()';
        $values[] = $this->id;

        $sql = 'UPDATE instagram_connections SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $pdo->prepare($sql);

        $result = $stmt->execute($values);

        if ($result) {
            // Reload data
            $updated = self::find($this->id);
            if ($updated) {
                foreach (get_object_vars($updated) as $key => $value) {
                    $this->$key = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Delete connection and all its posts
     */
    public function delete(): bool
    {
        $pdo = self::getPdo();

        // Delete posts first (cascade should handle this, but being explicit)
        $stmt = $pdo->prepare('DELETE FROM instagram_posts WHERE connection_id = ?');
        $stmt->execute([$this->id]);

        // Delete connection
        $stmt = $pdo->prepare('DELETE FROM instagram_connections WHERE id = ?');
        return $stmt->execute([$this->id]);
    }

    /**
     * Get all posts for this connection
     */
    public function posts(): array
    {
        return InstagramPost::getByConnection($this->id);
    }

    /**
     * Get active posts for this connection
     */
    public function activePosts(int $limit = null): array
    {
        return InstagramPost::getByConnection($this->id, $limit, true);
    }

    /**
     * Check if token is expired
     */
    public function isTokenExpired(): bool
    {
        return strtotime($this->token_expires_at) <= time();
    }

    /**
     * Check if token will expire soon (within days)
     */
    public function isTokenExpiringSoon(int $days = 7): bool
    {
        return strtotime($this->token_expires_at) <= strtotime("+{$days} days");
    }

    /**
     * Get days until token expiration
     */
    public function getDaysUntilExpiration(): int
    {
        $diff = strtotime($this->token_expires_at) - time();
        return max(0, (int) ceil($diff / 86400));
    }

    /**
     * Hydrate object from database row
     */
    private static function hydrate(array $data): self
    {
        $instance = new self();
        $instance->id = (int) $data['id'];
        $instance->tenant_id = $data['tenant_id'] !== null ? (int) $data['tenant_id'] : null;
        $instance->user_id = $data['user_id'] !== null ? (int) $data['user_id'] : null;
        $instance->instagram_user_id = $data['instagram_user_id'];
        $instance->username = $data['username'];
        $instance->profile_picture = $data['profile_picture'];
        $instance->access_token = $data['access_token'];
        $instance->refresh_token = $data['refresh_token'];
        $instance->token_expires_at = $data['token_expires_at'];
        $instance->is_active = (int) $data['is_active'];
        $instance->last_synced_at = $data['last_synced_at'];
        $instance->last_error = $data['last_error'];
        $instance->created_at = $data['created_at'];
        $instance->updated_at = $data['updated_at'];

        return $instance;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'instagram_user_id' => $this->instagram_user_id,
            'username' => $this->username,
            'profile_picture' => $this->profile_picture,
            'access_token' => '***', // Don't expose token
            'token_expires_at' => $this->token_expires_at,
            'is_active' => $this->is_active,
            'is_expired' => $this->isTokenExpired(),
            'days_until_expiration' => $this->getDaysUntilExpiration(),
            'last_synced_at' => $this->last_synced_at,
            'last_error' => $this->last_error,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
