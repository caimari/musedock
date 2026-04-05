<?php

namespace Screenart\Musedock\Middlewares;

use Screenart\Musedock\Models\ApiKey;

/**
 * Middleware for API key authentication.
 *
 * Reads Authorization: Bearer mdk_xxx header, validates against api_keys table,
 * checks active/expiry/rate-limit, and sets the authenticated key context.
 */
class ApiKeyAuth
{
    /**
     * The authenticated API key (available after handle()).
     */
    private static ?ApiKey $authenticatedKey = null;

    /**
     * Handle the authentication check.
     * Sends JSON error response and exits on failure.
     */
    public function handle(): bool
    {
        // Set JSON content type for all API responses
        header('Content-Type: application/json; charset=utf-8');

        // CORS headers
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');

        // Handle preflight
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        // Extract Bearer token
        $authHeader = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if (!preg_match('/^Bearer\s+(mdk_[a-f0-9]{40})$/i', $authHeader, $matches)) {
            self::respond(401, 'UNAUTHORIZED', 'Missing or invalid API key. Use: Authorization: Bearer mdk_xxx');
            return false;
        }

        $rawKey = $matches[1];

        // Find key by hash
        $apiKey = ApiKey::findByRawKey($rawKey);
        if (!$apiKey) {
            self::respond(401, 'UNAUTHORIZED', 'Invalid API key.');
            return false;
        }

        // Check active
        if (!$apiKey->is_active) {
            self::respond(401, 'KEY_DISABLED', 'This API key has been deactivated.');
            return false;
        }

        // Check expiry
        if ($apiKey->isExpired()) {
            self::respond(401, 'KEY_EXPIRED', 'This API key has expired.');
            return false;
        }

        // Check rate limit
        if (!$apiKey->checkRateLimit()) {
            $retryAfter = 60 - (int)date('s');
            header("Retry-After: {$retryAfter}");
            self::respond(429, 'RATE_LIMIT_EXCEEDED', "Rate limit exceeded. Max {$apiKey->rate_limit} requests/minute.");
            return false;
        }

        self::$authenticatedKey = $apiKey;
        return true;
    }

    /**
     * Get the authenticated API key.
     */
    public static function key(): ?ApiKey
    {
        return self::$authenticatedKey;
    }

    /**
     * Resolve the tenant ID for the current request.
     * - Tenant key: locked to its tenant_id.
     * - Superadmin key with domain_group_id: reads tenant_id from request, validates against group.
     * - Superadmin key without group: reads tenant_id from request, no restrictions.
     */
    public static function resolveTenantId(): ?int
    {
        $key = self::$authenticatedKey;
        if (!$key) return null;

        // Tenant-specific key: locked to one tenant
        if (!$key->isSuperadmin()) {
            return (int) $key->tenant_id;
        }

        // Superadmin key: read from request body or query
        $input = self::getJsonInput();
        $tenantId = $input['tenant_id']
            ?? $_GET['tenant_id']
            ?? null;

        if ($tenantId === null) return null;

        $tenantId = (int) $tenantId;

        // Enforce domain group restriction
        if (!$key->canAccessTenant($tenantId)) {
            self::respond(403, 'TENANT_NOT_ALLOWED', "This API key cannot access tenant {$tenantId}. It is restricted to domain group #{$key->domain_group_id}.");
        }

        return $tenantId;
    }

    /**
     * Get the list of tenant IDs this key can access, or null if unrestricted.
     * Useful for list endpoints that should only show allowed tenants.
     */
    public static function getAllowedTenantIds(): ?array
    {
        $key = self::$authenticatedKey;
        if (!$key) return [];
        return $key->getAllowedTenantIds();
    }

    /**
     * Check if the authenticated key has a specific permission.
     * Sends 403 and exits if not.
     */
    public static function requirePermission(string $permission): void
    {
        $key = self::$authenticatedKey;
        if (!$key || !$key->hasPermission($permission)) {
            self::respond(403, 'FORBIDDEN', "This API key does not have the '{$permission}' permission.");
        }
    }

    /**
     * Get parsed JSON input from the request body.
     */
    public static function getJsonInput(): array
    {
        // Use cached JSON from CSRF middleware if available
        if (isset($GLOBALS['_JSON_INPUT']) && is_array($GLOBALS['_JSON_INPUT'])) {
            return $GLOBALS['_JSON_INPUT'];
        }

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (is_array($data)) {
            $GLOBALS['_JSON_INPUT'] = $data;
            return $data;
        }

        return [];
    }

    /**
     * Send a JSON error response and exit.
     */
    public static function respond(int $httpCode, string $errorCode, string $message): void
    {
        http_response_code($httpCode);
        echo json_encode([
            'success' => false,
            'error'   => [
                'code'    => $errorCode,
                'message' => $message,
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
