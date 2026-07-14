#!/usr/bin/env php
<?php

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Solo CLI');
}

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/core/bootstrap.php';

use Screenart\Musedock\Services\NewsletterCampaignService;

$limit = 100;
foreach ($argv as $arg) {
    if (strpos($arg, '--limit=') === 0) {
        $limit = (int)substr($arg, 8);
    }
}
$limit = max(1, min(500, $limit));

$result = NewsletterCampaignService::processPendingDeliveries($limit);

echo "Newsletter worker\n";
echo "Processed: " . (int)($result['processed'] ?? 0) . "\n";
echo "Sent: " . (int)($result['sent'] ?? 0) . "\n";
echo "Failed: " . (int)($result['failed'] ?? 0) . "\n";
