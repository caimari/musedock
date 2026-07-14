<?php

namespace Screenart\Musedock\Services\Tasks;

use Screenart\Musedock\Services\NewsletterCampaignService;

class NewsletterQueueTask
{
    public static function run(): array
    {
        $enabled = self::isEnabled();
        if (!$enabled) {
            return [
                'enabled' => false,
                'processed' => 0,
                'sent' => 0,
                'failed' => 0,
            ];
        }

        $batch = (int)(getenv('NEWSLETTER_BATCH_SIZE') ?: 100);
        $batch = max(1, min(500, $batch));

        $result = NewsletterCampaignService::processPendingDeliveries($batch);

        return [
            'enabled' => true,
            'processed' => (int)($result['processed'] ?? 0),
            'sent' => (int)($result['sent'] ?? 0),
            'failed' => (int)($result['failed'] ?? 0),
        ];
    }

    private static function isEnabled(): bool
    {
        $raw = getenv('NEWSLETTER_QUEUE_ENABLED');
        if ($raw === false || $raw === null || $raw === '') {
            return true;
        }

        $raw = strtolower(trim((string)$raw));
        return in_array($raw, ['1', 'true', 'yes', 'on'], true);
    }
}
