<?php

namespace Screenart\Musedock\Services;

/**
 * CMS Update Service — checks for and applies updates from GitHub.
 * Mirrors the pattern of MuseDock Panel's UpdateService.
 */
class CmsUpdateService
{
    private const REPO_RAW_URL = 'https://raw.githubusercontent.com/caimari/musedock/main';
    private const CHECK_INTERVAL = 21600; // 6 hours

    /**
     * Check for available update. Uses DB cache unless forced.
     */
    public static function checkForUpdate(bool $force = false): array
    {
        $current = self::getCurrentVersion();
        $cached = self::getCachedUpdateInfo();

        if (!$force && $cached && (time() - ($cached['checked_at_epoch'] ?? 0)) < self::CHECK_INTERVAL) {
            return $cached;
        }

        $remote = self::fetchRemoteVersion();

        $info = [
            'current'          => $current,
            'remote'           => $remote,
            'has_update'       => $remote && version_compare($current, $remote, '<'),
            'checked_at'       => date('Y-m-d H:i:s'),
            'checked_at_epoch' => time(),
        ];

        // Cache in settings
        self::saveSetting('cms_update_remote_version', $remote ?: '');
        self::saveSetting('cms_update_last_check', (string) time());
        self::saveSetting('cms_update_has_update', $info['has_update'] ? '1' : '0');

        return $info;
    }

    /**
     * Get cached update info (no HTTP calls).
     */
    public static function getCachedUpdateInfo(): ?array
    {
        $remote = self::getSetting('cms_update_remote_version', '');
        $lastCheck = (int) self::getSetting('cms_update_last_check', '0');

        if ($lastCheck === 0) return null;

        $current = self::getCurrentVersion();
        $hasUpdate = $remote && version_compare($current, $remote, '<');

        return [
            'current'          => $current,
            'remote'           => $remote,
            'has_update'       => $hasUpdate,
            'checked_at'       => date('Y-m-d H:i:s', $lastCheck),
            'checked_at_epoch' => $lastCheck,
        ];
    }

    /**
     * Execute the update via cli/update.sh.
     */
    public static function runUpdate(): array
    {
        $cmsDir = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
        $logFile = $cmsDir . '/storage/logs/cms-update.log';
        $updateScript = $cmsDir . '/cli/update.sh';

        if (!file_exists($updateScript)) {
            return ['success' => false, 'message' => 'Update script not found.'];
        }

        // Run in background
        $cmd = sprintf(
            'nohup bash %s --auto > %s 2>&1 &',
            escapeshellarg($updateScript),
            escapeshellarg($logFile)
        );

        shell_exec($cmd);

        self::saveSetting('cms_update_in_progress', '1');
        self::saveSetting('cms_update_started_at', (string) time());

        return [
            'success'  => true,
            'message'  => 'Update started. The page will reload when complete.',
            'log_file' => $logFile,
        ];
    }

    /**
     * Get update status (polling endpoint).
     */
    public static function getUpdateStatus(): array
    {
        $cmsDir = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
        $inProgress = self::getSetting('cms_update_in_progress', '0') === '1';
        $startedAt = (int) self::getSetting('cms_update_started_at', '0');
        $logFile = $cmsDir . '/storage/logs/cms-update.log';
        $output = file_exists($logFile) ? file_get_contents($logFile) : '';
        $output = preg_replace('/\033\[[0-9;]*m/', '', $output); // Strip ANSI

        // Timeout after 2 minutes
        if ($inProgress && (time() - $startedAt) > 120) {
            self::saveSetting('cms_update_in_progress', '0');
            $inProgress = false;
        }

        // Check for completion marker
        if ($inProgress && str_contains($output, 'Update complete')) {
            self::saveSetting('cms_update_in_progress', '0');
            self::saveSetting('cms_update_has_update', '0');
            $inProgress = false;
        }

        return [
            'in_progress' => $inProgress,
            'started_at'  => $startedAt > 0 ? date('Y-m-d H:i:s', $startedAt) : null,
            'output'      => $output,
            'elapsed'     => $startedAt > 0 ? time() - $startedAt : 0,
            'version'     => self::getCurrentVersion(),
        ];
    }

    /**
     * Get current CMS version from composer.json.
     */
    public static function getCurrentVersion(): string
    {
        $composerPath = (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2)) . '/composer.json';
        if (file_exists($composerPath)) {
            $data = json_decode(file_get_contents($composerPath), true);
            return $data['version'] ?? '0.0.0';
        }
        return '0.0.0';
    }

    /**
     * Fetch remote version from GitHub raw composer.json.
     */
    private static function fetchRemoteVersion(): ?string
    {
        $ctx = stream_context_create([
            'http' => ['timeout' => 10, 'user_agent' => 'MuseDock-CMS/' . self::getCurrentVersion()],
        ]);

        $content = @file_get_contents(self::REPO_RAW_URL . '/composer.json', false, $ctx);
        if ($content !== false) {
            $data = json_decode($content, true);
            if (isset($data['version'])) {
                return $data['version'];
            }
        }

        return null;
    }

    // --- Settings helpers (work without tenant context) ---

    private static function saveSetting(string $key, string $value): void
    {
        try {
            $pdo = \Screenart\Musedock\Database::connect();
            $keyCol = \Screenart\Musedock\Database::qi('key');
            $stmt = $pdo->prepare("SELECT id FROM settings WHERE {$keyCol} = ?");
            $stmt->execute([$key]);
            if ($stmt->fetch()) {
                $pdo->prepare("UPDATE settings SET value = ? WHERE {$keyCol} = ?")->execute([$value, $key]);
            } else {
                $pdo->prepare("INSERT INTO settings ({$keyCol}, value) VALUES (?, ?)")->execute([$key, $value]);
            }
        } catch (\Exception $e) {
            error_log("CmsUpdateService: Error saving setting {$key}: " . $e->getMessage());
        }
    }

    private static function getSetting(string $key, string $default = ''): string
    {
        try {
            $pdo = \Screenart\Musedock\Database::connect();
            $keyCol = \Screenart\Musedock\Database::qi('key');
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE {$keyCol} = ?");
            $stmt->execute([$key]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ? ($row['value'] ?? $default) : $default;
        } catch (\Exception $e) {
            return $default;
        }
    }
}
