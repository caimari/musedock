<?php

/**
 * Refresh Instagram Tokens Command
 *
 * This command should be run via cron job daily to refresh Instagram tokens
 * that are expiring soon (within 7 days by default).
 *
 * Usage (via cron):
 * 0 2 * * * php /path/to/httpdocs/modules/instagram-gallery/commands/RefreshInstagramTokens.php
 */

// Only allow CLI execution
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

// Find config file to get database credentials
$configPaths = [
    __DIR__ . '/../../../config/database.php',
    __DIR__ . '/../../../.env',
    __DIR__ . '/../../../config.php',
];

$dbConfig = null;

// Try to load database config
foreach ($configPaths as $path) {
    if (file_exists($path)) {
        if (strpos($path, '.env') !== false) {
            // Parse .env file
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $env = [];
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
                }
            }
            if (isset($env['DB_HOST'])) {
                $dbConfig = [
                    'host' => $env['DB_HOST'] ?? 'localhost',
                    'dbname' => $env['DB_DATABASE'] ?? '',
                    'user' => $env['DB_USERNAME'] ?? '',
                    'password' => $env['DB_PASSWORD'] ?? '',
                    'driver' => $env['DB_CONNECTION'] ?? 'mysql',
                ];
            }
        } else {
            $config = include $path;
            if (is_array($config) && isset($config['database'])) {
                $dbConfig = $config['database'];
            }
        }
        if ($dbConfig) break;
    }
}

// Fallback: Try to connect with default credentials
if (!$dbConfig) {
    echo "⚠️  WARNING: No se encontró configuración de BD, usando valores por defecto\n";
    $dbConfig = [
        'driver' => 'mysql',
        'host' => 'localhost',
        'dbname' => 'musedock',
        'user' => 'root',
        'password' => '',
    ];
}

// Connect to database
try {
    $driver = $dbConfig['driver'] ?? 'mysql';
    $host = $dbConfig['host'] ?? 'localhost';
    $dbname = $dbConfig['dbname'] ?? $dbConfig['database'] ?? '';
    $user = $dbConfig['user'] ?? $dbConfig['username'] ?? 'root';
    $password = $dbConfig['password'] ?? '';

    if ($driver === 'pgsql') {
        $dsn = "pgsql:host=$host;dbname=$dbname";
    } else {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    }

    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

} catch (PDOException $e) {
    die("ERROR: No se pudo conectar a la base de datos: " . $e->getMessage() . "\n");
}

// Setup logging
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/token-refresh-' . date('Y-m-d') . '.log';

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    echo $logMessage;
}

logMessage('========================================');
logMessage('Instagram Token Refresh - Started');
logMessage('========================================');

// Check if auto-refresh is enabled
$stmt = $pdo->prepare("SELECT setting_value FROM instagram_settings WHERE setting_key = 'auto_refresh_tokens' AND (tenant_id IS NULL OR tenant_id = 0) LIMIT 1");
$stmt->execute();
$autoRefresh = $stmt->fetchColumn();

if ($autoRefresh === '0' || $autoRefresh === 'false') {
    logMessage('Auto-refresh is disabled. Exiting.');
    exit(0);
}

// Get threshold
$stmt = $pdo->prepare("SELECT setting_value FROM instagram_settings WHERE setting_key = 'token_refresh_threshold_days' AND (tenant_id IS NULL OR tenant_id = 0) LIMIT 1");
$stmt->execute();
$thresholdDays = (int) ($stmt->fetchColumn() ?: 7);

logMessage("Checking for tokens expiring within {$thresholdDays} days...");

// Get connections that need refresh
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
if ($driver === 'mysql') {
    $stmt = $pdo->prepare("
        SELECT * FROM instagram_connections
        WHERE is_active = 1
        AND token_expires_at <= DATE_ADD(NOW(), INTERVAL ? DAY)
        ORDER BY token_expires_at ASC
    ");
} else {
    $stmt = $pdo->prepare("
        SELECT * FROM instagram_connections
        WHERE is_active = 1
        AND token_expires_at <= NOW() + INTERVAL '{$thresholdDays} days'
        ORDER BY token_expires_at ASC
    ");
}

$stmt->execute([$thresholdDays]);
$connections = $stmt->fetchAll();

logMessage('Found ' . count($connections) . ' connection(s) needing refresh.');

$successCount = 0;
$failureCount = 0;

foreach ($connections as $connection) {
    $id = $connection['id'];
    $username = $connection['username'];
    $expiresAt = $connection['token_expires_at'];
    $tenantId = $connection['tenant_id'];

    logMessage("\nProcessing connection ID {$id} (@{$username})...");
    logMessage("  Current expiration: {$expiresAt}");

    try {
        // Get API credentials
        $stmt = $pdo->prepare("
            SELECT setting_key, setting_value
            FROM instagram_settings
            WHERE setting_key IN ('instagram_app_id', 'instagram_app_secret', 'instagram_redirect_uri')
            AND (tenant_id = ? OR tenant_id IS NULL OR tenant_id = 0)
            ORDER BY tenant_id DESC
        ");
        $stmt->execute([$tenantId]);
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        $appId = $settings['instagram_app_id'] ?? null;
        $appSecret = $settings['instagram_app_secret'] ?? null;

        if (!$appId || !$appSecret) {
            throw new Exception('Instagram API credentials not configured');
        }

        // Refresh token via Instagram API
        $url = 'https://graph.instagram.com/refresh_access_token';
        $params = [
            'grant_type' => 'ig_refresh_token',
            'access_token' => $connection['access_token']
        ];

        $ch = curl_init($url . '?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("API error: HTTP {$httpCode}");
        }

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            throw new Exception('Invalid API response');
        }

        // Calculate new expiration
        $expiresIn = $data['expires_in'] ?? 5184000; // 60 days default
        $newExpiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

        // Update database
        $stmt = $pdo->prepare("
            UPDATE instagram_connections
            SET access_token = ?, token_expires_at = ?, last_error = NULL, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$data['access_token'], $newExpiresAt, $id]);

        $successCount++;
        logMessage("  ✓ SUCCESS: Token refreshed successfully");
        logMessage("  New expiration: {$newExpiresAt}");

    } catch (Exception $e) {
        $failureCount++;
        $errorMsg = $e->getMessage();
        logMessage("  ✗ FAILED: " . $errorMsg);

        // Update last_error
        $stmt = $pdo->prepare("
            UPDATE instagram_connections
            SET last_error = ?, is_active = 0, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$errorMsg, $id]);
    }
}

logMessage("\n========================================");
logMessage("Token Refresh - Completed");
logMessage("  Total processed: " . count($connections));
logMessage("  Successful: {$successCount}");
logMessage("  Failed: {$failureCount}");
logMessage("========================================\n");

exit($failureCount > 0 ? 1 : 0);
