<?php

namespace Screenart\Musedock\Services;

use Screenart\Musedock\Database;

/**
 * Logs every API v1 tool call and enforces per-tool rate limits.
 */
class ApiToolLogger
{
    /**
     * Default rate limits per tool (requests per minute).
     * Tools not listed here inherit the API key's global rate_limit.
     */
    private const TOOL_RATE_LIMITS = [
        // Destructive actions — very limited
        'delete_post'     => 5,
        'delete_page'     => 5,
        'delete_category' => 10,
        'delete_tag'      => 10,
        // Write actions — moderate
        'create_post'     => 15,
        'create_page'     => 15,
        'update_post'     => 20,
        'update_page'     => 20,
        'cross_publish'   => 5,
        // Read actions — generous (inherits global limit)
    ];

    /**
     * Tools that require explicit confirmation ("confirm": true in body).
     * Returns a human-readable warning message if confirmation is needed.
     */
    private const DANGEROUS_TOOLS = [
        'delete_post'     => 'This will permanently delete the post.',
        'delete_page'     => 'This will permanently delete the page.',
        'delete_category' => 'This will delete the category and unlink it from all posts.',
        'delete_tag'      => 'This will delete the tag and unlink it from all posts.',
        'cross_publish'   => 'This will publish content to other websites.',
    ];

    /**
     * Check if a tool requires confirmation and if it was provided.
     * Returns null if OK, or an array with the warning if confirmation needed.
     */
    public static function requiresConfirmation(string $toolName): ?array
    {
        if (!isset(self::DANGEROUS_TOOLS[$toolName])) {
            return null; // Not a dangerous tool
        }

        // Check if "confirm": true was sent in the request body
        $input = $GLOBALS['_JSON_INPUT'] ?? null;
        if (!$input) {
            $json = file_get_contents('php://input');
            $input = json_decode($json, true) ?: [];
        }

        if (!empty($input['confirm'])) {
            return null; // Confirmed
        }

        return [
            'confirmation_required' => true,
            'tool'                  => $toolName,
            'warning'               => self::DANGEROUS_TOOLS[$toolName],
            'message'               => "Action requires confirmation. Resend with \"confirm\": true in the request body.",
        ];
    }

    /**
     * Check per-tool rate limit. Returns true if allowed.
     *
     * @param int    $apiKeyId
     * @param string $toolName
     * @param int    $globalLimit  The API key's global rate_limit (fallback)
     */
    public static function checkToolRateLimit(int $apiKeyId, string $toolName, int $globalLimit = 60): bool
    {
        $limit = self::TOOL_RATE_LIMITS[$toolName] ?? $globalLimit;

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM api_tool_logs
                WHERE api_key_id = ? AND tool_name = ?
                AND created_at > NOW() - INTERVAL '1 minute'
            ");
            $stmt->execute([$apiKeyId, $toolName]);
            $count = (int) $stmt->fetchColumn();

            return $count < $limit;
        } catch (\Throwable $e) {
            // If logging table doesn't exist or query fails, allow the request
            return true;
        }
    }

    /**
     * Get the rate limit for a specific tool.
     */
    public static function getToolRateLimit(string $toolName, int $globalLimit = 60): int
    {
        return self::TOOL_RATE_LIMITS[$toolName] ?? $globalLimit;
    }

    /**
     * Log a tool call.
     *
     * @param int         $apiKeyId
     * @param int|null    $tenantId
     * @param string      $toolName
     * @param string      $method     HTTP method
     * @param string      $path       Request path
     * @param array       $input      Input data (will be truncated)
     * @param int         $statusCode HTTP status code
     * @param bool        $success
     * @param float|null  $startTime  microtime(true) from request start
     */
    public static function log(
        int $apiKeyId,
        ?int $tenantId,
        string $toolName,
        string $method,
        string $path,
        array $input = [],
        int $statusCode = 200,
        bool $success = true,
        ?float $startTime = null
    ): void {
        $durationMs = $startTime ? (int)((microtime(true) - $startTime) * 1000) : null;

        // Truncate input to avoid storing huge payloads
        $inputSummary = json_encode($input, JSON_UNESCAPED_UNICODE);
        if (strlen($inputSummary) > 2000) {
            $inputSummary = substr($inputSummary, 0, 2000) . '...(truncated)';
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        try {
            $pdo = Database::connect();
            $pdo->prepare("
                INSERT INTO api_tool_logs
                (api_key_id, tenant_id, tool_name, http_method, path, input_summary, status_code, success, duration_ms, ip_address, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ")->execute([
                $apiKeyId,
                $tenantId,
                $toolName,
                $method,
                $path,
                $inputSummary,
                $statusCode,
                $success ? 1 : 0,
                $durationMs,
                $ip,
            ]);
        } catch (\Throwable $e) {
            error_log("ApiToolLogger: Failed to log: " . $e->getMessage());
        }
    }

    /**
     * Auto-cleanup old logs (older than 30 days).
     * Call this from a cron job.
     */
    public static function cleanup(int $days = 30): int
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("DELETE FROM api_tool_logs WHERE created_at < NOW() - INTERVAL '{$days} days'");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            error_log("ApiToolLogger: Cleanup failed: " . $e->getMessage());
            return 0;
        }
    }
}
