<?php
namespace Screenart\Musedock\Helpers;

use Screenart\Musedock\Database;
use PDO;

class AdHelper
{
    private static array $cache = [];

    /**
     * Get the active ad for a given slot and tenant.
     * Returns null if no active ad exists.
     */
    public static function getActiveAd(string $slotSlug, ?int $tenantId = null): ?object
    {
        $cacheKey = $slotSlug . ':' . ($tenantId ?? 'global');
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        try {
            $pdo = Database::connect();

            if ($tenantId) {
                $stmt = $pdo->prepare("
                    SELECT * FROM ad_units
                    WHERE slot_slug = ? AND tenant_id = ? AND is_active = true
                    AND (starts_at IS NULL OR starts_at <= NOW())
                    AND (ends_at IS NULL OR ends_at >= NOW())
                    ORDER BY priority DESC
                    LIMIT 1
                ");
                $stmt->execute([$slotSlug, $tenantId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT * FROM ad_units
                    WHERE slot_slug = ? AND tenant_id IS NULL AND is_active = true
                    AND (starts_at IS NULL OR starts_at <= NOW())
                    AND (ends_at IS NULL OR ends_at >= NOW())
                    ORDER BY priority DESC
                    LIMIT 1
                ");
                $stmt->execute([$slotSlug]);
            }

            $ad = $stmt->fetch(PDO::FETCH_OBJ);
            self::$cache[$cacheKey] = $ad ?: null;
            return self::$cache[$cacheKey];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Render an ad slot. Returns empty string if no ad exists (nothing in DOM).
     */
    public static function renderSlot(string $slotSlug, array $options = []): string
    {
        $tenantId = function_exists('tenant_id') ? tenant_id() : null;
        $ad = self::getActiveAd($slotSlug, $tenantId);

        if (!$ad) return '';

        // For in-feed: only render at specific intervals
        if ($slotSlug === 'in-feed' && isset($options['index'])) {
            $every = $ad->repeat_every ?: 4;
            if (($options['index'] + 1) % $every !== 0) return '';
        }

        $html = self::buildAdHtml($ad, $slotSlug);

        // Increment impressions asynchronously (fire-and-forget)
        self::trackImpression($ad->id);

        return $html;
    }

    /**
     * Build the HTML for an ad unit.
     */
    private static function buildAdHtml(object $ad, string $slotSlug): string
    {
        $wrapperClass = 'musedock-ad musedock-ad--' . htmlspecialchars($slotSlug);

        if ($ad->ad_type === 'html' && $ad->html_content) {
            return '<div class="' . $wrapperClass . '">' . $ad->html_content . '</div>';
        }

        if ($ad->ad_type === 'image' && $ad->image_url) {
            $img = '<img src="' . htmlspecialchars($ad->image_url) . '" alt="' . htmlspecialchars($ad->alt_text ?? '') . '" loading="lazy" style="max-width:100%;height:auto;">';

            if ($ad->link_url) {
                $target = htmlspecialchars($ad->link_target ?: '_blank');
                $img = '<a href="' . htmlspecialchars($ad->link_url) . '" target="' . $target . '" rel="noopener sponsored" data-ad-id="' . $ad->id . '">' . $img . '</a>';
            }

            return '<div class="' . $wrapperClass . '">' . $img . '</div>';
        }

        return '';
    }

    /**
     * Inject in-article ad into HTML content after N paragraphs.
     */
    public static function injectInArticle(string $htmlContent, ?int $tenantId = null): string
    {
        $ad = self::getActiveAd('in-article', $tenantId);
        if (!$ad) return $htmlContent;

        $afterParagraph = $ad->repeat_every ?: 3;
        $adHtml = self::buildAdHtml($ad, 'in-article');
        if (!$adHtml) return $htmlContent;

        // Split by closing </p> tags
        $parts = preg_split('/(<\/p>)/i', $htmlContent, -1, PREG_SPLIT_DELIM_CAPTURE);
        $result = '';
        $pCount = 0;
        $injected = false;

        for ($i = 0; $i < count($parts); $i++) {
            $result .= $parts[$i];
            if (strtolower($parts[$i]) === '</p>') {
                $pCount++;
                if ($pCount === $afterParagraph && !$injected) {
                    $result .= $adHtml;
                    $injected = true;
                    self::trackImpression($ad->id);
                }
            }
        }

        return $result;
    }

    /**
     * Track ad impression (increment counter).
     */
    private static function trackImpression(int $adId): void
    {
        try {
            $pdo = Database::connect();
            $pdo->prepare("UPDATE ad_units SET impressions = impressions + 1 WHERE id = ?")->execute([$adId]);
        } catch (\Exception $e) {
            // Silent fail — don't break the page for a counter
        }
    }

    /**
     * Track ad click (called via AJAX endpoint).
     */
    public static function trackClick(int $adId): void
    {
        try {
            $pdo = Database::connect();
            $pdo->prepare("UPDATE ad_units SET clicks = clicks + 1 WHERE id = ?")->execute([$adId]);
        } catch (\Exception $e) {}
    }

    /**
     * Get all available slots with their descriptions.
     */
    public static function getSlots(): array
    {
        try {
            $pdo = Database::connect();
            return $pdo->query("SELECT * FROM ad_slots ORDER BY id")->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            return [];
        }
    }
}
