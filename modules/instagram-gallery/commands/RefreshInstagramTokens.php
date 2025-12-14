<?php

namespace Modules\InstagramGallery\Commands;

use Modules\InstagramGallery\Models\InstagramConnection;
use Modules\InstagramGallery\Models\InstagramSetting;
use Modules\InstagramGallery\Services\InstagramApiService;
use Exception;

/**
 * Refresh Instagram Tokens Command
 *
 * This command should be run via cron job daily to refresh Instagram tokens
 * that are expiring soon (within 7 days by default).
 *
 * Usage (via cron):
 * 0 2 * * * php /path/to/httpdocs/modules/instagram-gallery/commands/RefreshInstagramTokens.php
 */
class RefreshInstagramTokens
{
    private $pdo;
    private $logFile;

    public function __construct()
    {
        // Connect to database
        $this->pdo = \Screenart\Musedock\Database::connect();
        InstagramConnection::setPdo($this->pdo);
        InstagramSetting::setPdo($this->pdo);

        // Setup logging
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $this->logFile = $logDir . '/token-refresh-' . date('Y-m-d') . '.log';
    }

    /**
     * Execute the command
     */
    public function execute(): void
    {
        $this->log('========================================');
        $this->log('Instagram Token Refresh - Started');
        $this->log('========================================');

        // Check if auto-refresh is enabled
        $autoRefresh = InstagramSetting::get('auto_refresh_tokens', null, true);
        if (!$autoRefresh) {
            $this->log('Auto-refresh is disabled. Exiting.');
            return;
        }

        // Get threshold for expiration
        $thresholdDays = (int) InstagramSetting::get('token_refresh_threshold_days', null, 7);
        $this->log("Checking for tokens expiring within {$thresholdDays} days...");

        // Get connections that need token refresh
        $connections = InstagramConnection::getExpiringSoon($thresholdDays);
        $this->log('Found ' . count($connections) . ' connection(s) needing refresh.');

        $successCount = 0;
        $failureCount = 0;

        foreach ($connections as $connection) {
            $this->log("\nProcessing connection ID {$connection->id} (@{$connection->username})...");
            $this->log("  Current expiration: {$connection->token_expires_at}");
            $this->log("  Days until expiration: {$connection->getDaysUntilExpiration()}");

            try {
                $this->refreshConnection($connection);
                $successCount++;
                $this->log("  ✓ SUCCESS: Token refreshed successfully");
            } catch (Exception $e) {
                $failureCount++;
                $errorMsg = $e->getMessage();
                $this->log("  ✗ FAILED: " . $errorMsg);

                // Update last_error in connection
                $connection->update([
                    'last_error' => $errorMsg,
                    'is_active' => 0 // Deactivate connection on error
                ]);
            }
        }

        $this->log("\n========================================");
        $this->log("Token Refresh - Completed");
        $this->log("  Total processed: " . count($connections));
        $this->log("  Successful: {$successCount}");
        $this->log("  Failed: {$failureCount}");
        $this->log("========================================\n");
    }

    /**
     * Refresh a single connection's token
     */
    private function refreshConnection(InstagramConnection $connection): void
    {
        // Get Instagram API credentials
        $appId = InstagramSetting::get('instagram_app_id', $connection->tenant_id);
        $appSecret = InstagramSetting::get('instagram_app_secret', $connection->tenant_id);
        $redirectUri = InstagramSetting::get('instagram_redirect_uri', $connection->tenant_id);

        if (!$appId || !$appSecret) {
            throw new Exception('Instagram API credentials not configured');
        }

        // Initialize API service
        $api = new InstagramApiService($appId, $appSecret, $redirectUri);

        // Refresh token
        $result = $api->refreshToken($connection->access_token);

        // Calculate new expiration date
        $expiresAt = InstagramApiService::calculateExpirationDate($result['expires_in']);

        // Update connection
        $connection->update([
            'access_token' => $result['access_token'],
            'token_expires_at' => $expiresAt,
            'last_error' => null,
            'is_active' => 1
        ]);

        $this->log("  New expiration: {$expiresAt}");
    }

    /**
     * Log message to file and stdout
     */
    private function log(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";

        // Write to file
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);

        // Write to stdout
        echo $logMessage;
    }

    /**
     * Send notification email on errors (optional)
     */
    private function sendErrorNotification(string $message): void
    {
        // TODO: Implement email notification
        // This could use the system's email service to notify admins
        // of token refresh failures
    }
}

// Run the command if executed directly
if (php_sapi_name() === 'cli' && isset($argv[0]) && basename($argv[0]) === 'RefreshInstagramTokens.php') {
    try {
        // Load the application bootstrap
        $bootstrapPath = __DIR__ . '/../../../bootstrap.php';
        if (file_exists($bootstrapPath)) {
            require_once $bootstrapPath;
        }

        $command = new RefreshInstagramTokens();
        $command->execute();
        exit(0);
    } catch (Exception $e) {
        echo "FATAL ERROR: " . $e->getMessage() . "\n";
        echo $e->getTraceAsString() . "\n";
        exit(1);
    }
}
