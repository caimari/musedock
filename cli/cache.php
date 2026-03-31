#!/usr/bin/env php
<?php

/**
 * CLI: HTML Cache Management
 *
 * Commands:
 *   php cli/cache.php warm                    — Warm cache for all active tenants
 *   php cli/cache.php warm --tenant=5         — Warm cache for tenant 5 only
 *   php cli/cache.php warm --only=home,blog   — Warm only specific types
 *   php cli/cache.php warm --limit=20         — Limit items per type
 *   php cli/cache.php purge                   — Purge all HTML cache
 *   php cli/cache.php purge --tenant=5        — Purge cache for tenant 5 only
 *   php cli/cache.php status                  — Show cache statistics
 *   php cli/cache.php enable                  — Enable HTML cache (.env)
 *   php cli/cache.php disable                 — Disable HTML cache (.env)
 *
 * @package Screenart\Musedock\CLI
 */

// Only run from CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "CLI only.";
    exit(1);
}

// Bootstrap
define('APP_ROOT', realpath(__DIR__ . '/../'));
require_once APP_ROOT . '/core/Env.php';
\Screenart\Musedock\Env::load();
require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/core/helpers.php';
require_once APP_ROOT . '/core/Logger.php';
\Screenart\Musedock\Logger::init(APP_ROOT . '/storage/logs/error.log', 'ERROR');
require_once APP_ROOT . '/core/Cache/HtmlCache.php';

use Screenart\Musedock\Cache\HtmlCache;

// Parse arguments
$command = $argv[1] ?? 'help';
$options = parseOptions(array_slice($argv, 2));

switch ($command) {
    case 'warm':
        cmdWarm($options);
        break;
    case 'purge':
        cmdPurge($options);
        break;
    case 'status':
        cmdStatus($options);
        break;
    case 'enable':
        cmdToggle(true);
        break;
    case 'disable':
        cmdToggle(false);
        break;
    default:
        cmdHelp();
        break;
}

// =========================================================================
// Commands
// =========================================================================

function cmdWarm(array $options): void
{
    $tenantId = isset($options['tenant']) ? (int) $options['tenant'] : null;
    $only = isset($options['only']) ? explode(',', $options['only']) : null;
    $limit = isset($options['limit']) ? (int) $options['limit'] : 50;

    if (!HtmlCache::isEnabled()) {
        output("HTML Cache is DISABLED. Enable it first:", 'yellow');
        output("  php cli/cache.php enable");
        output("  # or add HTML_CACHE_ENABLED=true to .env");
        return;
    }

    $target = $tenantId !== null ? "tenant {$tenantId}" : "all active tenants";
    $types = $only ? implode(', ', $only) : 'all types';
    output("Warming cache for {$target} ({$types}, limit {$limit})...", 'cyan');

    $startTime = microtime(true);
    $warmed = HtmlCache::warm($tenantId, [
        'only'  => $only,
        'limit' => $limit,
    ]);

    $elapsed = round(microtime(true) - $startTime, 2);
    $count = count($warmed);

    output("");
    if ($count > 0) {
        output("Warmed {$count} pages in {$elapsed}s:", 'green');
        foreach ($warmed as $url) {
            output("  OK  {$url}", 'green');
        }
    } else {
        output("No pages warmed. Check that content exists and cache is enabled.", 'yellow');
    }
}

function cmdPurge(array $options): void
{
    $tenantId = isset($options['tenant']) ? (int) $options['tenant'] : null;
    $target = $tenantId !== null ? "tenant {$tenantId}" : "ALL tenants";

    output("Purging HTML cache for {$target}...", 'cyan');

    $count = HtmlCache::purge($tenantId);

    if ($count > 0) {
        output("Purged {$count} cached HTML files.", 'green');
    } else {
        output("No cached files found.", 'yellow');
    }
}

function cmdStatus(array $options): void
{
    $tenantId = isset($options['tenant']) ? (int) $options['tenant'] : null;
    $stats = HtmlCache::stats($tenantId);

    output("=== HTML Cache Status ===", 'cyan');
    output("Enabled:     " . ($stats['enabled'] ? 'YES' : 'NO'));
    output("Total files: " . $stats['total_files']);
    output("Total size:  " . formatSize($stats['total_size']));
    output("");

    if (!empty($stats['tenants'])) {
        output("Per-tenant breakdown:", 'cyan');
        foreach ($stats['tenants'] as $name => $ts) {
            $oldest = $ts['oldest'] ? date('Y-m-d H:i', $ts['oldest']) : 'N/A';
            $newest = $ts['newest'] ? date('Y-m-d H:i', $ts['newest']) : 'N/A';
            output("  {$name}: {$ts['files']} files, " . formatSize($ts['size']) . " | oldest: {$oldest} | newest: {$newest}");
        }
    } else {
        output("No cached files found.", 'yellow');
    }
}

function cmdToggle(bool $enable): void
{
    $envFile = APP_ROOT . '/.env';
    if (!file_exists($envFile)) {
        output("ERROR: .env file not found", 'red');
        return;
    }

    $content = file_get_contents($envFile);
    $key = 'HTML_CACHE_ENABLED';
    $value = $enable ? 'true' : 'false';

    if (strpos($content, $key) !== false) {
        // Replace existing
        $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
    } else {
        // Append
        $content = rtrim($content) . "\n\n# HTML Static Cache\n{$key}={$value}\n";
    }

    file_put_contents($envFile, $content);

    $state = $enable ? 'ENABLED' : 'DISABLED';
    output("HTML Cache {$state} in .env", $enable ? 'green' : 'yellow');

    if (!$enable) {
        output("Purging existing cache...", 'cyan');
        $count = HtmlCache::purge();
        output("Purged {$count} files.", 'green');
    }

    if ($enable) {
        output("");
        output("To warm the cache now, run:", 'cyan');
        output("  php cli/cache.php warm");
    }
}

function cmdHelp(): void
{
    output("MuseDock HTML Cache CLI", 'cyan');
    output("");
    output("Usage: php cli/cache.php <command> [options]");
    output("");
    output("Commands:");
    output("  warm      Warm (pre-generate) cache for public pages");
    output("  purge     Delete all cached HTML files");
    output("  status    Show cache statistics");
    output("  enable    Enable HTML cache in .env");
    output("  disable   Disable HTML cache and purge files");
    output("");
    output("Options:");
    output("  --tenant=ID     Target specific tenant (default: all)");
    output("  --only=types    Comma-separated: home,blog,pages");
    output("  --limit=N       Max items per type (default: 50)");
}

// =========================================================================
// Helpers
// =========================================================================

function parseOptions(array $args): array
{
    $options = [];
    foreach ($args as $arg) {
        if (str_starts_with($arg, '--')) {
            $parts = explode('=', substr($arg, 2), 2);
            $options[$parts[0]] = $parts[1] ?? true;
        }
    }
    return $options;
}

function output(string $msg, ?string $color = null): void
{
    $colors = [
        'red'    => "\033[31m",
        'green'  => "\033[32m",
        'yellow' => "\033[33m",
        'cyan'   => "\033[36m",
        'reset'  => "\033[0m",
    ];

    if ($color && isset($colors[$color])) {
        echo $colors[$color] . $msg . $colors['reset'] . "\n";
    } else {
        echo $msg . "\n";
    }
}

function formatSize(int $bytes): string
{
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}
