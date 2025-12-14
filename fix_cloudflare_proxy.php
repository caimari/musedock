<?php
/**
 * Fix Cloudflare Proxy for FREE Subdomains
 *
 * Changes orange cloud (proxied) to grey cloud (DNS-only)
 * This allows Caddy to obtain Let's Encrypt certificates
 *
 * Usage: php fix_cloudflare_proxy.php [tenant_id]
 */

require_once __DIR__ . '/core/bootstrap.php';

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;

// Load CloudflareService
require_once __DIR__ . '/plugins/superadmin/caddy-domain-manager/Services/CloudflareService.php';

$tenantId = $argv[1] ?? null;

if (!$tenantId) {
    echo "Usage: php fix_cloudflare_proxy.php <tenant_id>\n";
    echo "Example: php fix_cloudflare_proxy.php 3\n";
    exit(1);
}

try {
    $pdo = Database::connect();

    // Get tenant information
    $stmt = $pdo->prepare("
        SELECT id, domain, subdomain, is_subdomain, cloudflare_record_id, cloudflare_proxied, plan
        FROM tenants
        WHERE id = :id
    ");
    $stmt->execute(['id' => $tenantId]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tenant) {
        echo "❌ Tenant with ID {$tenantId} not found\n";
        exit(1);
    }

    echo "📋 Tenant Information:\n";
    echo "   ID: {$tenant['id']}\n";
    echo "   Domain: {$tenant['domain']}\n";
    echo "   Subdomain: {$tenant['subdomain']}\n";
    echo "   Is Subdomain: {$tenant['is_subdomain']}\n";
    echo "   Plan: {$tenant['plan']}\n";
    echo "   Cloudflare Record ID: {$tenant['cloudflare_record_id']}\n";
    echo "   Currently Proxied: " . ($tenant['cloudflare_proxied'] ? 'YES (orange cloud)' : 'NO (grey cloud)') . "\n\n";

    if (!$tenant['cloudflare_record_id']) {
        echo "❌ No Cloudflare record ID found for this tenant\n";
        exit(1);
    }

    if ($tenant['cloudflare_proxied'] == 0) {
        echo "✓ This tenant is already set to DNS-only (grey cloud)\n";
        exit(0);
    }

    // Initialize Cloudflare service
    $cloudflare = new CloudflareService();

    echo "🔧 Changing Cloudflare proxy status to DNS-only (grey cloud)...\n";

    // Update proxy status to false (DNS-only)
    $result = $cloudflare->updateProxyStatus($tenant['cloudflare_record_id'], false);

    if ($result['success']) {
        // Update database
        $updateStmt = $pdo->prepare("
            UPDATE tenants
            SET cloudflare_proxied = 0,
                cloudflare_error_log = NULL
            WHERE id = :id
        ");
        $updateStmt->execute(['id' => $tenantId]);

        echo "✓ Cloudflare DNS record updated to DNS-only (grey cloud)\n";
        echo "✓ Database updated\n\n";
        echo "🎯 Next steps:\n";
        echo "   1. Wait 2-3 minutes for DNS propagation\n";
        echo "   2. Caddy will automatically obtain Let's Encrypt certificate\n";
        echo "   3. Access https://{$tenant['domain']} to verify SSL works\n";

    } else {
        echo "❌ Failed to update Cloudflare record: {$result['error']}\n";

        // Log error in database
        $errorStmt = $pdo->prepare("
            UPDATE tenants
            SET cloudflare_error_log = :error
            WHERE id = :id
        ");
        $errorStmt->execute([
            'id' => $tenantId,
            'error' => $result['error']
        ]);

        exit(1);
    }

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    Logger::error("[FixCloudflareProxy] Exception: " . $e->getMessage());
    exit(1);
}
