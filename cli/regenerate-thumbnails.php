#!/usr/bin/env php
<?php
/**
 * Regenerate thumbnails and medium variants for existing media images.
 *
 * Usage:
 *   php cli/regenerate-thumbnails.php                  - Regenerate missing thumbnails only
 *   php cli/regenerate-thumbnails.php --force           - Regenerate ALL thumbnails (overwrite existing)
 *   php cli/regenerate-thumbnails.php --webp-only       - Only regenerate old .jpg thumbnails as .webp
 *   php cli/regenerate-thumbnails.php --tenant=ID       - Only for a specific tenant
 *   php cli/regenerate-thumbnails.php --limit=100       - Process max N images
 *   php cli/regenerate-thumbnails.php --dry-run         - Show what would be done without doing it
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '512M');
set_time_limit(0);

// Verificar que se ejecuta desde CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Este script solo puede ejecutarse desde línea de comandos (CLI)');
}

// Bootstrap
$appRoot = dirname(__DIR__);
define('APP_ROOT', $appRoot);

if (file_exists(APP_ROOT . '/vendor/autoload.php')) {
    require_once APP_ROOT . '/vendor/autoload.php';
}

if (file_exists(APP_ROOT . '/core/Env.php')) {
    require_once APP_ROOT . '/core/Env.php';
    \Screenart\Musedock\Env::load();
}

if (file_exists(APP_ROOT . '/config/config.php')) {
    require_once APP_ROOT . '/config/config.php';
} elseif (file_exists(APP_ROOT . '/config.php')) {
    require_once APP_ROOT . '/config.php';
}

// Register Media Manager module namespace (not in composer.json)
spl_autoload_register(function ($class) {
    $map = [
        'MediaManager\\Controllers\\' => APP_ROOT . '/modules/media-manager/controllers/',
        'MediaManager\\Models\\'      => APP_ROOT . '/modules/media-manager/models/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $relClass = substr($class, strlen($prefix));
            $file = $dir . str_replace('\\', '/', $relClass) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});

use Screenart\Musedock\Database;

// Parse CLI arguments
$args = [];
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--')) {
        $parts = explode('=', substr($arg, 2), 2);
        $args[$parts[0]] = $parts[1] ?? true;
    }
}

$force     = isset($args['force']);
$webpOnly  = isset($args['webp-only']);
$tenantId  = isset($args['tenant']) ? (int)$args['tenant'] : null;
$limit     = isset($args['limit']) ? (int)$args['limit'] : 0;
$dryRun    = isset($args['dry-run']);

echo "=== MuseDock Thumbnail Regenerator ===\n";
echo "Mode: " . ($force ? 'FORCE (all)' : ($webpOnly ? 'WebP upgrade only' : 'Missing only')) . "\n";
if ($tenantId) echo "Tenant: {$tenantId}\n";
if ($limit) echo "Limit: {$limit}\n";
if ($dryRun) echo "*** DRY RUN - no changes will be made ***\n";
echo "\n";

$pdo = Database::connect();

// Get all image media records
$sql = "SELECT id, path, disk, mime_type, metadata, tenant_id FROM media WHERE mime_type LIKE 'image/%' AND mime_type != 'image/svg+xml'";
$params = [];

if ($tenantId) {
    $sql .= " AND tenant_id = ?";
    $params[] = $tenantId;
}

$sql .= " ORDER BY id ASC";

if ($limit > 0) {
    $sql .= " LIMIT {$limit}";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$images = $stmt->fetchAll(\PDO::FETCH_ASSOC);

echo "Found " . count($images) . " image(s) to process.\n\n";

$storageRoot = $appRoot . '/storage/app/media';

// Detect ownership from storage root to match new files
$ownerUid = fileowner($storageRoot);
$ownerGid = filegroup($storageRoot);
$mediaController = new \MediaManager\Controllers\MediaController();

// Use reflection to access the private createImageThumbnail method
$reflection = new ReflectionMethod($mediaController, 'createImageThumbnail');
$reflection->setAccessible(true);

$stats = [
    'processed' => 0,
    'thumb_created' => 0,
    'medium_created' => 0,
    'thumb_upgraded' => 0,
    'medium_upgraded' => 0,
    'skipped' => 0,
    'errors' => 0,
];

foreach ($images as $image) {
    $id = $image['id'];
    $path = $image['path'];
    $mime = $image['mime_type'];
    $disk = $image['disk'];
    $metadata = $image['metadata'] ? json_decode($image['metadata'], true) : [];

    $fullPath = $storageRoot . '/' . $path;

    if (!file_exists($fullPath)) {
        echo "  [{$id}] SKIP - file not found: {$path}\n";
        $stats['skipped']++;
        continue;
    }

    // Determine directory and filename
    $dir = dirname($path);
    $filename = pathinfo($path, PATHINFO_FILENAME);

    $needsThumb = false;
    $needsMedium = false;

    if ($force) {
        $needsThumb = true;
        $needsMedium = true;
    } elseif ($webpOnly) {
        // Check if current thumbnails are .jpg (not .webp)
        $existingThumbPath = $metadata['thumbnail']['path'] ?? null;
        $existingMediumPath = $metadata['medium']['path'] ?? null;
        $needsThumb = $existingThumbPath && !str_ends_with($existingThumbPath, '.webp');
        $needsMedium = $existingMediumPath && !str_ends_with($existingMediumPath, '.webp');
    } else {
        // Missing only
        $needsThumb = empty($metadata['thumbnail']['path']);
        $needsMedium = empty($metadata['medium']['path']);
    }

    if (!$needsThumb && !$needsMedium) {
        $stats['skipped']++;
        continue;
    }

    echo "  [{$id}] Processing: {$path}";

    if ($dryRun) {
        echo " -> would generate:";
        if ($needsThumb) echo " thumb";
        if ($needsMedium) echo " medium";
        echo "\n";
        $stats['processed']++;
        continue;
    }

    $updated = false;

    // Generate thumbnail (420px)
    if ($needsThumb) {
        try {
            $thumbnail = $reflection->invoke($mediaController, $fullPath, $mime, 420, 420);
            if ($thumbnail && !empty($thumbnail['tmp_path'])) {
                $thumbDir = "{$dir}/thumbs";
                $fullThumbDir = $storageRoot . '/' . $thumbDir;
                if (!is_dir($fullThumbDir)) {
                    mkdir($fullThumbDir, 0755, true);
                    @chown($fullThumbDir, $ownerUid);
                    @chgrp($fullThumbDir, $ownerGid);
                }

                $thumbExt = ($thumbnail['mime_type'] ?? '') === 'image/webp' ? 'webp' : 'jpg';
                $thumbBasename = $filename . '_thumb.' . $thumbExt;
                $thumbRelPath = "{$thumbDir}/{$thumbBasename}";
                $fullThumbPath = $storageRoot . '/' . $thumbRelPath;

                // Remove old thumbnail if exists and is different
                $oldThumbPath = $metadata['thumbnail']['path'] ?? null;
                if ($oldThumbPath && $oldThumbPath !== $thumbRelPath) {
                    $oldFullPath = $storageRoot . '/' . $oldThumbPath;
                    if (file_exists($oldFullPath)) {
                        @unlink($oldFullPath);
                    }
                }

                rename($thumbnail['tmp_path'], $fullThumbPath);
                @chmod($fullThumbPath, 0644);
                @chown($fullThumbPath, $ownerUid);
                @chgrp($fullThumbPath, $ownerGid);

                $metadata['thumbnail'] = [
                    'path' => $thumbRelPath,
                    'mime_type' => $thumbnail['mime_type'],
                    'size' => $thumbnail['size'],
                    'width' => $thumbnail['width'],
                    'height' => $thumbnail['height'],
                ];

                $isUpgrade = $webpOnly || ($force && $oldThumbPath && !str_ends_with($oldThumbPath, '.webp'));
                if ($isUpgrade) {
                    $stats['thumb_upgraded']++;
                    echo " [thumb:webp]";
                } else {
                    $stats['thumb_created']++;
                    echo " [thumb:new]";
                }
                $updated = true;
            }
        } catch (\Exception $e) {
            echo " [thumb:ERROR {$e->getMessage()}]";
            $stats['errors']++;
        }
    }

    // Generate medium (800px)
    if ($needsMedium) {
        try {
            $medium = $reflection->invoke($mediaController, $fullPath, $mime, 800, 800);
            if ($medium && !empty($medium['tmp_path'])) {
                $mediumDir = "{$dir}/medium";
                $fullMediumDir = $storageRoot . '/' . $mediumDir;
                if (!is_dir($fullMediumDir)) {
                    mkdir($fullMediumDir, 0755, true);
                    @chown($fullMediumDir, $ownerUid);
                    @chgrp($fullMediumDir, $ownerGid);
                }

                $mediumExt = ($medium['mime_type'] ?? '') === 'image/webp' ? 'webp' : 'jpg';
                $mediumBasename = $filename . '_medium.' . $mediumExt;
                $mediumRelPath = "{$mediumDir}/{$mediumBasename}";
                $fullMediumPath = $storageRoot . '/' . $mediumRelPath;

                // Remove old medium if exists and is different
                $oldMediumPath = $metadata['medium']['path'] ?? null;
                if ($oldMediumPath && $oldMediumPath !== $mediumRelPath) {
                    $oldFullPath = $storageRoot . '/' . $oldMediumPath;
                    if (file_exists($oldFullPath)) {
                        @unlink($oldFullPath);
                    }
                }

                rename($medium['tmp_path'], $fullMediumPath);
                @chmod($fullMediumPath, 0644);
                @chown($fullMediumPath, $ownerUid);
                @chgrp($fullMediumPath, $ownerGid);

                $metadata['medium'] = [
                    'path' => $mediumRelPath,
                    'mime_type' => $medium['mime_type'],
                    'size' => $medium['size'],
                    'width' => $medium['width'],
                    'height' => $medium['height'],
                ];

                $isUpgrade = $webpOnly || ($force && $oldMediumPath && !str_ends_with($oldMediumPath, '.webp'));
                if ($isUpgrade) {
                    $stats['medium_upgraded']++;
                    echo " [medium:webp]";
                } else {
                    $stats['medium_created']++;
                    echo " [medium:new]";
                }
                $updated = true;
            }
        } catch (\Exception $e) {
            echo " [medium:ERROR {$e->getMessage()}]";
            $stats['errors']++;
        }
    }

    // Update DB metadata
    if ($updated) {
        try {
            $stmt2 = $pdo->prepare("UPDATE media SET metadata = ? WHERE id = ?");
            $stmt2->execute([json_encode($metadata), $id]);
        } catch (\Exception $e) {
            echo " [DB ERROR: {$e->getMessage()}]";
            $stats['errors']++;
        }
    }

    echo "\n";
    $stats['processed']++;
}

echo "\n=== Results ===\n";
echo "Processed:        {$stats['processed']}\n";
echo "Thumbnails created: {$stats['thumb_created']}\n";
echo "Medium created:     {$stats['medium_created']}\n";
echo "Thumbnails upgraded to WebP: {$stats['thumb_upgraded']}\n";
echo "Medium upgraded to WebP:     {$stats['medium_upgraded']}\n";
echo "Skipped:          {$stats['skipped']}\n";
echo "Errors:           {$stats['errors']}\n";
echo "Done.\n";
