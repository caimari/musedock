<?php

namespace FestivalDirectory\Models;

use Screenart\Musedock\Database\Model;
use Screenart\Musedock\Database;

class FestivalSubmissionLink extends Model
{
    protected static string $table = 'festival_submission_links';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = false;

    protected array $fillable = [
        'festival_id',
        'platform',
        'url',
        'label',
        'sort_order',
    ];

    protected array $casts = [
        'id'          => 'int',
        'festival_id' => 'int',
        'sort_order'  => 'int',
    ];

    /**
     * Known platform definitions (icon, color, default label).
     */
    public static function getPlatforms(): array
    {
        return [
            'filmfreeway' => ['label' => 'FilmFreeway',     'icon' => 'bi-camera-reels', 'color' => '#20c997'],
            'festhome'    => ['label' => 'Festhome',         'icon' => 'bi-globe2',       'color' => '#f15122'],
            'festgate'    => ['label' => 'FestGate',         'icon' => 'bi-door-open',    'color' => '#0d6efd'],
            'shortfilmdepot' => ['label' => 'ShortFilmDepot', 'icon' => 'bi-film',       'color' => '#6f42c1'],
            'clickforfestivals' => ['label' => 'Click for Festivals', 'icon' => 'bi-cursor', 'color' => '#fd7e14'],
            'withoutabox' => ['label' => 'Withoutabox',      'icon' => 'bi-box',          'color' => '#6c757d'],
            'website'     => ['label' => 'Web oficial',      'icon' => 'bi-globe',        'color' => '#212529'],
            'other'       => ['label' => 'Otra plataforma',  'icon' => 'bi-link-45deg',   'color' => '#adb5bd'],
        ];
    }

    /**
     * Get all submission links for a festival.
     */
    public static function getForFestival(int $festivalId): array
    {
        return self::where('festival_id', $festivalId)->orderBy('sort_order', 'ASC')->get();
    }

    /**
     * Sync submission links for a festival (replace all).
     */
    public static function syncForFestival(int $festivalId, array $links): void
    {
        $pdo = Database::connect();
        $pdo->prepare("DELETE FROM festival_submission_links WHERE festival_id = ?")->execute([$festivalId]);

        if (empty($links)) return;

        $stmt = $pdo->prepare("INSERT INTO festival_submission_links (festival_id, platform, url, label, sort_order) VALUES (?, ?, ?, ?, ?)");
        foreach ($links as $i => $link) {
            if (empty($link['url'])) continue;
            $platform = $link['platform'] ?? 'other';
            $platforms = self::getPlatforms();
            $label = $link['label'] ?? ($platforms[$platform]['label'] ?? $platform);
            $stmt->execute([$festivalId, $platform, $link['url'], $label, $link['sort_order'] ?? $i]);
        }
    }

    /**
     * Add a single submission link (upsert — if platform exists for this festival, update URL).
     */
    public static function addLink(int $festivalId, string $platform, string $url, ?string $label = null): void
    {
        $pdo = Database::connect();
        $platforms = self::getPlatforms();
        $label = $label ?? ($platforms[$platform]['label'] ?? $platform);

        // Upsert
        $existing = $pdo->prepare("SELECT id FROM festival_submission_links WHERE festival_id = ? AND platform = ?");
        $existing->execute([$festivalId, $platform]);

        if ($existing->fetch()) {
            $pdo->prepare("UPDATE festival_submission_links SET url = ?, label = ? WHERE festival_id = ? AND platform = ?")
                ->execute([$url, $label, $festivalId, $platform]);
        } else {
            $maxOrder = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 as next FROM festival_submission_links WHERE festival_id = ?");
            $maxOrder->execute([$festivalId]);
            $order = (int)$maxOrder->fetch(\PDO::FETCH_ASSOC)['next'];

            $pdo->prepare("INSERT INTO festival_submission_links (festival_id, platform, url, label, sort_order) VALUES (?, ?, ?, ?, ?)")
                ->execute([$festivalId, $platform, $url, $label, $order]);
        }
    }

    /**
     * Batch load submission links for multiple festivals.
     */
    public static function batchLoad(array $festivalIds): array
    {
        if (empty($festivalIds)) return [];

        $placeholders = implode(',', array_fill(0, count($festivalIds), '?'));
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM festival_submission_links WHERE festival_id IN ({$placeholders}) ORDER BY sort_order ASC");
        $stmt->execute($festivalIds);

        $result = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $result[$row['festival_id']][] = $row;
        }
        return $result;
    }
}
