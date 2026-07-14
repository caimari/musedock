<?php

namespace Screenart\Musedock\Services\Tasks;

use Screenart\Musedock\Services\PublicVersionBadgeService;

class PublicVersionSnapshotTask
{
    public static function run(): array
    {
        try {
            $result = PublicVersionBadgeService::refreshCache(false);

            return [
                'enabled' => true,
                'cached' => (bool)($result['cached'] ?? false),
                'cms_current' => (string)($result['cms_current'] ?? ''),
                'cms_latest' => (string)($result['cms_latest'] ?? ''),
                'panel_latest' => (string)($result['panel_latest'] ?? ''),
                'last_check' => (int)($result['last_check'] ?? 0),
            ];
        } catch (\Throwable $e) {
            error_log('PublicVersionSnapshotTask error: ' . $e->getMessage());
            return [
                'enabled' => true,
                'error' => $e->getMessage(),
            ];
        }
    }
}
