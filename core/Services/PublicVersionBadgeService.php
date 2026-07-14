<?php

namespace Screenart\Musedock\Services;

use Screenart\Musedock\Database;

/**
 * PublicVersionBadgeService
 *
 * Cachea en BD las versiones visibles en el topbar público:
 * - CMS actual (local)
 * - CMS última disponible (repo)
 * - Panel última disponible (repo)
 *
 * Importante:
 * - No hace consultas remotas en cada request.
 * - Se actualiza por cron (o manualmente llamando refreshCache(true)).
 */
class PublicVersionBadgeService
{
    private const DEFAULT_CMS_REPO_URL = 'https://github.com/caimari/musedock';
    private const DEFAULT_PANEL_REPO_URL = 'https://github.com/caimari/musedock-panel';
    private const CHECK_INTERVAL = 21600; // 6 horas

    /**
     * Datos cacheados para pintar en frontend sin tocar red.
     */
    public static function getTopbarData(): array
    {
        $settings = self::getSettingsMap([
            'topbar_versions_cms_current',
            'topbar_versions_cms_latest',
            'topbar_versions_panel_latest',
            'topbar_versions_last_check',
            'topbar_versions_cms_repo_url',
            'topbar_versions_panel_repo_url',
        ]);

        $cmsCurrent = CmsUpdateService::getCurrentVersion();
        $storedCurrent = trim((string)($settings['topbar_versions_cms_current'] ?? ''));

        // Si cambió versión local tras deploy, persistir sin tocar red.
        if ($storedCurrent !== $cmsCurrent) {
            self::saveSettings([
                'topbar_versions_cms_current' => $cmsCurrent,
            ]);
        }

        $cmsLatest = trim((string)($settings['topbar_versions_cms_latest'] ?? ''));
        $panelLatest = trim((string)($settings['topbar_versions_panel_latest'] ?? ''));
        $cmsRepo = trim((string)($settings['topbar_versions_cms_repo_url'] ?? self::DEFAULT_CMS_REPO_URL));
        $panelRepo = trim((string)($settings['topbar_versions_panel_repo_url'] ?? self::DEFAULT_PANEL_REPO_URL));
        $lastCheck = (int)($settings['topbar_versions_last_check'] ?? 0);

        return [
            'cms_current' => $cmsCurrent,
            'cms_latest' => $cmsLatest,
            'panel_latest' => $panelLatest,
            'cms_repo_url' => $cmsRepo ?: self::DEFAULT_CMS_REPO_URL,
            'panel_repo_url' => $panelRepo ?: self::DEFAULT_PANEL_REPO_URL,
            'last_check' => $lastCheck,
            'last_check_human' => $lastCheck > 0 ? date('Y-m-d H:i:s', $lastCheck) : null,
            'cms_has_update' => $cmsLatest !== '' && version_compare($cmsCurrent, $cmsLatest, '<'),
        ];
    }

    /**
     * Refresca el cache consultando repos remotos.
     */
    public static function refreshCache(bool $force = false): array
    {
        $settings = self::getSettingsMap([
            'topbar_versions_last_check',
            'topbar_versions_cms_repo_url',
            'topbar_versions_panel_repo_url',
        ]);

        $lastCheck = (int)($settings['topbar_versions_last_check'] ?? 0);
        if (!$force && $lastCheck > 0 && (time() - $lastCheck) < self::CHECK_INTERVAL) {
            return array_merge(['cached' => true], self::getTopbarData());
        }

        $cmsRepo = trim((string)($settings['topbar_versions_cms_repo_url'] ?? self::DEFAULT_CMS_REPO_URL));
        $panelRepo = trim((string)($settings['topbar_versions_panel_repo_url'] ?? self::DEFAULT_PANEL_REPO_URL));

        $cmsCurrent = CmsUpdateService::getCurrentVersion();
        $cmsLatest = self::fetchLatestVersionFromRepo($cmsRepo);
        $panelLatest = self::fetchLatestVersionFromRepo($panelRepo);

        $payload = [
            'topbar_versions_cms_current' => $cmsCurrent,
            'topbar_versions_cms_latest' => $cmsLatest,
            'topbar_versions_panel_latest' => $panelLatest,
            'topbar_versions_cms_repo_url' => $cmsRepo,
            'topbar_versions_panel_repo_url' => $panelRepo,
            'topbar_versions_last_check' => (string)time(),
            'topbar_versions_last_error' => '',
        ];

        self::saveSettings($payload);

        return [
            'cached' => false,
            'cms_current' => $cmsCurrent,
            'cms_latest' => $cmsLatest,
            'panel_latest' => $panelLatest,
            'cms_repo_url' => $cmsRepo,
            'panel_repo_url' => $panelRepo,
            'last_check' => time(),
            'cms_has_update' => $cmsLatest !== '' && version_compare($cmsCurrent, $cmsLatest, '<'),
        ];
    }

    /**
     * Permite editar URLs de repos desde UI.
     */
    public static function updateRepoUrls(string $cmsRepoUrl, string $panelRepoUrl): void
    {
        $cmsRepoUrl = self::normalizeRepoUrl($cmsRepoUrl, self::DEFAULT_CMS_REPO_URL);
        $panelRepoUrl = self::normalizeRepoUrl($panelRepoUrl, self::DEFAULT_PANEL_REPO_URL);

        self::saveSettings([
            'topbar_versions_cms_repo_url' => $cmsRepoUrl,
            'topbar_versions_panel_repo_url' => $panelRepoUrl,
        ]);
    }

    private static function fetchLatestVersionFromRepo(string $repoUrl): string
    {
        $repo = self::parseRepoUrl($repoUrl);
        if (!$repo) {
            return '';
        }

        [$owner, $name] = $repo;

        // 1) composer.json en main/master
        $composerMain = self::fetchJson("https://raw.githubusercontent.com/{$owner}/{$name}/main/composer.json");
        if (isset($composerMain['version']) && is_string($composerMain['version'])) {
            return trim($composerMain['version']);
        }

        $composerMaster = self::fetchJson("https://raw.githubusercontent.com/{$owner}/{$name}/master/composer.json");
        if (isset($composerMaster['version']) && is_string($composerMaster['version'])) {
            return trim($composerMaster['version']);
        }

        // 2) version.json (algunos repos usan core/version)
        $versionMain = self::fetchJson("https://raw.githubusercontent.com/{$owner}/{$name}/main/version.json");
        if (isset($versionMain['core']) && is_string($versionMain['core'])) {
            return trim($versionMain['core']);
        }
        if (isset($versionMain['version']) && is_string($versionMain['version'])) {
            return trim($versionMain['version']);
        }

        // 3) GitHub release/tag más reciente
        $release = self::fetchJson(
            "https://api.github.com/repos/{$owner}/{$name}/releases/latest",
            [
                'Accept: application/vnd.github+json',
                'User-Agent: MuseDock-CMS/' . CmsUpdateService::getCurrentVersion(),
            ]
        );

        if (isset($release['tag_name']) && is_string($release['tag_name']) && $release['tag_name'] !== '') {
            return ltrim(trim($release['tag_name']), 'vV');
        }

        // 4) fallback tags list (cuando no existen "releases")
        $tags = self::fetchJson(
            "https://api.github.com/repos/{$owner}/{$name}/tags",
            [
                'Accept: application/vnd.github+json',
                'User-Agent: MuseDock-CMS/' . CmsUpdateService::getCurrentVersion(),
            ]
        );
        if (is_array($tags) && !empty($tags[0]['name']) && is_string($tags[0]['name'])) {
            return ltrim(trim((string)$tags[0]['name']), 'vV');
        }

        return '';
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private static function parseRepoUrl(string $url): ?array
    {
        $path = trim((string)parse_url($url, PHP_URL_PATH), '/');
        if ($path === '') {
            return null;
        }

        $parts = explode('/', $path);
        if (count($parts) < 2) {
            return null;
        }

        $owner = trim((string)$parts[0]);
        $repo = trim((string)$parts[1]);
        $repo = preg_replace('/\.git$/i', '', $repo);

        if ($owner === '' || $repo === '') {
            return null;
        }

        return [$owner, $repo];
    }

    private static function normalizeRepoUrl(string $url, string $fallback): string
    {
        $url = trim($url);
        if ($url === '') {
            return $fallback;
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return $fallback;
        }

        return rtrim($url, '/');
    }

    private static function fetchJson(string $url, array $headers = []): ?array
    {
        $httpHeaders = $headers;
        if (empty($httpHeaders)) {
            $httpHeaders = [
                'User-Agent: MuseDock-CMS/' . CmsUpdateService::getCurrentVersion(),
                'Accept: application/json',
            ];
        }

        $githubToken = trim((string)(getenv('GITHUB_TOKEN') ?: ''));
        if ($githubToken !== '') {
            $hasAuth = false;
            foreach ($httpHeaders as $headerLine) {
                if (stripos((string)$headerLine, 'Authorization:') === 0) {
                    $hasAuth = true;
                    break;
                }
            }
            if (!$hasAuth) {
                $httpHeaders[] = 'Authorization: Bearer ' . $githubToken;
            }
        }

        // 1) cURL primero (más robusto en servidores con stream SSL restringido)
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_HTTPHEADER => $httpHeaders,
            ]);

            $raw = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (is_string($raw) && $raw !== '' && $httpCode >= 200 && $httpCode < 400) {
                $data = json_decode($raw, true);
                if (is_array($data)) {
                    return $data;
                }
            }
        }

        // 2) fallback a stream wrapper
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'header' => implode("\r\n", $httpHeaders),
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false || trim($raw) === '') {
            return null;
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    /**
     * @param string[] $keys
     * @return array<string,string>
     */
    private static function getSettingsMap(array $keys): array
    {
        try {
            $pdo = Database::connect();
            $keyCol = Database::qi('key');
            $in = implode(',', array_fill(0, count($keys), '?'));
            $stmt = $pdo->prepare("SELECT {$keyCol}, value FROM settings WHERE {$keyCol} IN ({$in})");
            $stmt->execute($keys);

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $map = [];
            foreach ($rows as $row) {
                $k = (string)($row[$keyCol] ?? $row['key'] ?? '');
                if ($k !== '') {
                    $map[$k] = (string)($row['value'] ?? '');
                }
            }

            return $map;
        } catch (\Throwable $e) {
            error_log('PublicVersionBadgeService::getSettingsMap error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @param array<string,string> $values
     */
    private static function saveSettings(array $values): void
    {
        if (empty($values)) {
            return;
        }

        try {
            $pdo = Database::connect();
            $keyCol = Database::qi('key');

            foreach ($values as $key => $value) {
                $stmt = $pdo->prepare("SELECT id FROM settings WHERE {$keyCol} = ? LIMIT 1");
                $stmt->execute([$key]);
                $exists = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($exists) {
                    $upd = $pdo->prepare("UPDATE settings SET value = ? WHERE {$keyCol} = ?");
                    $upd->execute([(string)$value, $key]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO settings ({$keyCol}, value) VALUES (?, ?)");
                    $ins->execute([$key, (string)$value]);
                }
            }

            if (function_exists('clear_settings_cache')) {
                clear_settings_cache();
            }
        } catch (\Throwable $e) {
            error_log('PublicVersionBadgeService::saveSettings error: ' . $e->getMessage());
        }
    }
}
