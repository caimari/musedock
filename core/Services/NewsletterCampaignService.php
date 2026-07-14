<?php

namespace Screenart\Musedock\Services;

use Screenart\Musedock\Database;
use Screenart\Musedock\Mail\Mailer;

class NewsletterCampaignService
{
    public static function createCampaign(array $data, ?int $forcedTenantId = null): int
    {
        $pdo = Database::connect();

        $tenantId = $forcedTenantId;
        if ($tenantId === null && array_key_exists('tenant_id', $data) && $data['tenant_id'] !== '' && $data['tenant_id'] !== null) {
            $tenantId = (int)$data['tenant_id'];
        }

        $stmt = $pdo->prepare("INSERT INTO newsletter_campaigns
            (tenant_id, name, subject, preheader, html_content, text_content, from_name, from_email, reply_to, scheduled_at, status, created_by_type, created_by_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, NOW(), NOW())");

        $scheduledAt = self::normalizeDateTime($data['scheduled_at'] ?? null);

        $stmt->execute([
            $tenantId,
            trim((string)($data['name'] ?? 'Campaña sin título')),
            trim((string)($data['subject'] ?? '')),
            trim((string)($data['preheader'] ?? '')),
            (string)($data['html_content'] ?? ''),
            (string)($data['text_content'] ?? ''),
            trim((string)($data['from_name'] ?? '')),
            trim((string)($data['from_email'] ?? '')),
            trim((string)($data['reply_to'] ?? '')),
            $scheduledAt,
            (string)($data['created_by_type'] ?? 'admin'),
            !empty($data['created_by_id']) ? (int)$data['created_by_id'] : null,
        ]);

        return (int)$pdo->lastInsertId();
    }

    public static function updateCampaign(int $campaignId, array $data, ?int $forcedTenantId = null): bool
    {
        $pdo = Database::connect();
        $campaign = self::findCampaign($campaignId, $forcedTenantId);
        if (!$campaign) {
            return false;
        }

        if (in_array((string)$campaign['status'], ['sent', 'cancelled'], true)) {
            return false;
        }

        $stmt = $pdo->prepare("UPDATE newsletter_campaigns SET
            name = ?,
            subject = ?,
            preheader = ?,
            html_content = ?,
            text_content = ?,
            from_name = ?,
            from_email = ?,
            reply_to = ?,
            scheduled_at = ?,
            updated_at = NOW()
            WHERE id = ?");

        $scheduledAt = self::normalizeDateTime($data['scheduled_at'] ?? null);

        return $stmt->execute([
            trim((string)($data['name'] ?? $campaign['name'] ?? 'Campaña sin título')),
            trim((string)($data['subject'] ?? $campaign['subject'] ?? '')),
            trim((string)($data['preheader'] ?? $campaign['preheader'] ?? '')),
            (string)($data['html_content'] ?? $campaign['html_content'] ?? ''),
            (string)($data['text_content'] ?? $campaign['text_content'] ?? ''),
            trim((string)($data['from_name'] ?? $campaign['from_name'] ?? '')),
            trim((string)($data['from_email'] ?? $campaign['from_email'] ?? '')),
            trim((string)($data['reply_to'] ?? $campaign['reply_to'] ?? '')),
            $scheduledAt,
            $campaignId,
        ]);
    }

    public static function queueCampaign(int $campaignId, ?int $forcedTenantId = null): array
    {
        $pdo = Database::connect();
        $campaign = self::findCampaign($campaignId, $forcedTenantId);

        if (!$campaign) {
            return ['success' => false, 'message' => 'Campaña no encontrada'];
        }

        if (in_array((string)$campaign['status'], ['sent', 'cancelled'], true)) {
            return ['success' => false, 'message' => 'La campaña ya está cerrada'];
        }

        $subject = trim((string)($campaign['subject'] ?? ''));
        if ($subject === '') {
            return ['success' => false, 'message' => 'La campaña necesita asunto'];
        }

        $hasContent = trim((string)($campaign['html_content'] ?? '')) !== '' || trim((string)($campaign['text_content'] ?? '')) !== '';
        if (!$hasContent) {
            return ['success' => false, 'message' => 'La campaña necesita contenido (HTML o texto)'];
        }

        $pdo->beginTransaction();
        try {
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM newsletter_campaign_deliveries WHERE campaign_id = ?");
            $countStmt->execute([$campaignId]);
            $existing = (int)$countStmt->fetchColumn();

            if ($existing === 0) {
                $subSql = "SELECT id, email, name FROM newsletter_subscribers WHERE status = 'active'";
                $params = [];

                if ($campaign['tenant_id'] === null) {
                    $subSql .= " AND tenant_id IS NULL";
                } else {
                    $subSql .= " AND tenant_id = ?";
                    $params[] = (int)$campaign['tenant_id'];
                }

                $subSql .= " ORDER BY id ASC";
                $subStmt = $pdo->prepare($subSql);
                $subStmt->execute($params);
                $subscribers = $subStmt->fetchAll(\PDO::FETCH_ASSOC);

                if (empty($subscribers)) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => 'No hay suscriptores activos para esta campaña'];
                }

                $ins = $pdo->prepare("INSERT INTO newsletter_campaign_deliveries
                    (campaign_id, subscriber_id, tenant_id, email, name, status, track_token, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW(), NOW())");

                foreach ($subscribers as $sub) {
                    $ins->execute([
                        $campaignId,
                        (int)$sub['id'],
                        $campaign['tenant_id'] !== null ? (int)$campaign['tenant_id'] : null,
                        (string)$sub['email'],
                        (string)($sub['name'] ?? ''),
                        bin2hex(random_bytes(24)),
                    ]);
                }

                $totalRecipients = count($subscribers);
            } else {
                $totStmt = $pdo->prepare("SELECT COUNT(*) FROM newsletter_campaign_deliveries WHERE campaign_id = ?");
                $totStmt->execute([$campaignId]);
                $totalRecipients = (int)$totStmt->fetchColumn();
            }

            $status = 'queued';
            $scheduledAt = self::normalizeDateTime($campaign['scheduled_at'] ?? null);

            $up = $pdo->prepare("UPDATE newsletter_campaigns SET
                status = ?,
                queued_at = NOW(),
                scheduled_at = COALESCE(?, scheduled_at),
                total_recipients = ?,
                updated_at = NOW()
                WHERE id = ?");
            $up->execute([$status, $scheduledAt, $totalRecipients, $campaignId]);

            $pdo->commit();

            return [
                'success' => true,
                'message' => 'Campaña en cola correctamente',
                'total_recipients' => $totalRecipients,
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('NewsletterCampaignService::queueCampaign error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error al poner en cola la campaña'];
        }
    }

    public static function processPendingDeliveries(int $limit = 100): array
    {
        $pdo = Database::connect();
        $limit = max(1, min(500, $limit));

        $sql = "SELECT
                d.id,
                d.campaign_id,
                d.subscriber_id,
                d.email,
                d.name,
                d.track_token,
                c.tenant_id,
                c.subject,
                c.preheader,
                c.html_content,
                c.text_content,
                c.from_name,
                c.from_email,
                c.reply_to,
                c.status AS campaign_status,
                c.scheduled_at,
                s.unsubscribe_token
            FROM newsletter_campaign_deliveries d
            INNER JOIN newsletter_campaigns c ON c.id = d.campaign_id
            LEFT JOIN newsletter_subscribers s ON s.id = d.subscriber_id
            WHERE d.status = 'pending'
              AND c.status IN ('queued', 'sending')
              AND (c.scheduled_at IS NULL OR c.scheduled_at <= NOW())
            ORDER BY d.id ASC
            LIMIT {$limit}";

        $rows = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($rows)) {
            return ['processed' => 0, 'sent' => 0, 'failed' => 0];
        }

        $sentCount = 0;
        $failCount = 0;
        $campaignTouched = [];

        foreach ($rows as $row) {
            $campaignId = (int)$row['campaign_id'];
            $campaignTouched[$campaignId] = true;

            self::markCampaignSendingIfNeeded($pdo, $campaignId, (string)$row['campaign_status']);

            $unsubscribeToken = (string)($row['unsubscribe_token'] ?? '');
            $html = self::renderHtmlWithTracking(
                (string)$row['html_content'],
                (string)$row['text_content'],
                (string)$row['track_token'],
                $unsubscribeToken
            );

            $text = trim((string)$row['text_content']);
            if ($text === '') {
                $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?: '');
            }
            if ($unsubscribeToken !== '') {
                $text .= "\n\nBaja de newsletter: " . self::absoluteUrl('/newsletter/unsubscribe/' . urlencode($unsubscribeToken));
            }

            $tenantId = $row['tenant_id'] !== null ? (int)$row['tenant_id'] : null;
            $fromEmail = trim((string)($row['from_email'] ?? '')) ?: null;
            $fromName = trim((string)($row['from_name'] ?? '')) ?: null;

            $ok = Mailer::send(
                (string)$row['email'],
                (string)$row['subject'],
                $html,
                $text,
                $fromEmail,
                $fromName,
                $tenantId
            );

            if ($ok) {
                $stmt = $pdo->prepare("UPDATE newsletter_campaign_deliveries
                    SET status = 'sent', sent_at = NOW(), updated_at = NOW()
                    WHERE id = ?");
                $stmt->execute([(int)$row['id']]);
                $sentCount++;
            } else {
                $stmt = $pdo->prepare("UPDATE newsletter_campaign_deliveries
                    SET status = 'failed', failed_at = NOW(), error_message = ?, updated_at = NOW()
                    WHERE id = ?");
                $stmt->execute(['Error SMTP al enviar', (int)$row['id']]);
                $failCount++;
            }
        }

        foreach (array_keys($campaignTouched) as $campaignId) {
            self::rebuildCampaignCounters((int)$campaignId);
        }

        return ['processed' => count($rows), 'sent' => $sentCount, 'failed' => $failCount];
    }

    public static function markOpenByToken(string $token): void
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("UPDATE newsletter_campaign_deliveries
            SET
                open_count = open_count + 1,
                first_opened_at = COALESCE(first_opened_at, NOW()),
                last_opened_at = NOW(),
                updated_at = NOW()
            WHERE track_token = ?");
        $stmt->execute([$token]);

        $campaignId = self::campaignIdByTrackToken($token);
        if ($campaignId !== null) {
            self::rebuildCampaignCounters($campaignId);
        }
    }

    public static function markClickByToken(string $token): void
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("UPDATE newsletter_campaign_deliveries
            SET
                click_count = click_count + 1,
                first_clicked_at = COALESCE(first_clicked_at, NOW()),
                last_clicked_at = NOW(),
                updated_at = NOW()
            WHERE track_token = ?");
        $stmt->execute([$token]);

        $campaignId = self::campaignIdByTrackToken($token);
        if ($campaignId !== null) {
            self::rebuildCampaignCounters($campaignId);
        }
    }

    public static function rebuildCampaignCounters(int $campaignId): void
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare("SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent_count,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_count,
            SUM(CASE WHEN first_opened_at IS NOT NULL THEN 1 ELSE 0 END) AS opened_unique,
            SUM(CASE WHEN first_clicked_at IS NOT NULL THEN 1 ELSE 0 END) AS clicked_unique
            FROM newsletter_campaign_deliveries
            WHERE campaign_id = ?");
        $stmt->execute([$campaignId]);
        $agg = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        $total = (int)($agg['total'] ?? 0);
        $pending = (int)($agg['pending_count'] ?? 0);
        $sent = (int)($agg['sent_count'] ?? 0);
        $failed = (int)($agg['failed_count'] ?? 0);
        $opened = (int)($agg['opened_unique'] ?? 0);
        $clicked = (int)($agg['clicked_unique'] ?? 0);

        $statusUpdate = "status = CASE
                WHEN status IN ('cancelled','paused') THEN status
                WHEN ? > 0 THEN 'sending'
                WHEN ? = 0 AND ? > 0 THEN 'sent'
                ELSE status
            END";

        $completedAt = ($pending === 0 && $total > 0) ? 'NOW()' : 'completed_at';

        $up = $pdo->prepare("UPDATE newsletter_campaigns SET
            total_recipients = ?,
            sent_count = ?,
            failed_count = ?,
            open_count = ?,
            click_count = ?,
            {$statusUpdate},
            completed_at = {$completedAt},
            updated_at = NOW()
            WHERE id = ?");

        $up->execute([
            $total,
            $sent,
            $failed,
            $opened,
            $clicked,
            $pending,
            $pending,
            $total,
            $campaignId,
        ]);
    }

    public static function findCampaign(int $campaignId, ?int $forcedTenantId = null): ?array
    {
        $pdo = Database::connect();

        if ($forcedTenantId === null) {
            $stmt = $pdo->prepare("SELECT * FROM newsletter_campaigns WHERE id = ? LIMIT 1");
            $stmt->execute([$campaignId]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM newsletter_campaigns WHERE id = ? AND tenant_id = ? LIMIT 1");
            $stmt->execute([$campaignId, $forcedTenantId]);
        }

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private static function campaignIdByTrackToken(string $token): ?int
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT campaign_id FROM newsletter_campaign_deliveries WHERE track_token = ? LIMIT 1");
        $stmt->execute([$token]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int)$id : null;
    }

    private static function markCampaignSendingIfNeeded(\PDO $pdo, int $campaignId, string $status): void
    {
        if ($status === 'sending') {
            return;
        }

        if ($status === 'queued') {
            $stmt = $pdo->prepare("UPDATE newsletter_campaigns
                SET status = 'sending', started_at = COALESCE(started_at, NOW()), updated_at = NOW()
                WHERE id = ?");
            $stmt->execute([$campaignId]);
        }
    }

    private static function renderHtmlWithTracking(string $html, string $textFallback, string $trackToken, string $unsubscribeToken): string
    {
        $html = trim($html);
        if ($html === '') {
            $html = nl2br(htmlspecialchars($textFallback !== '' ? $textFallback : ''));
        }

        $html = self::rewriteLinksForTracking($html, $trackToken);

        $trackingPixel = '<img src="' . htmlspecialchars(self::absoluteUrl('/newsletter/track/open/' . urlencode($trackToken))) . '" width="1" height="1" style="display:none;" alt="" />';

        $footer = '';
        if ($unsubscribeToken !== '') {
            $unsubscribeUrl = self::absoluteUrl('/newsletter/unsubscribe/' . urlencode($unsubscribeToken));
            $footer .= "<hr style='margin-top:24px;border:none;border-top:1px solid #eee;'>";
            $footer .= "<p style='font-size:12px;color:#666;'>Si no deseas recibir más emails, puedes darte de baja aquí: ";
            $footer .= "<a href='" . htmlspecialchars($unsubscribeUrl) . "'>" . htmlspecialchars($unsubscribeUrl) . "</a></p>";
        }

        return $html . $footer . $trackingPixel;
    }

    private static function rewriteLinksForTracking(string $html, string $trackToken): string
    {
        return (string)preg_replace_callback(
            '/<a\s+([^>]*?)href=("|\')(.*?)\2([^>]*)>/i',
            function (array $m) use ($trackToken) {
                $before = $m[1] ?? '';
                $quote = $m[2] ?? '"';
                $href = trim((string)($m[3] ?? ''));
                $after = $m[4] ?? '';

                if ($href === '' || strpos($href, '#') === 0 || stripos($href, 'mailto:') === 0 || stripos($href, 'tel:') === 0) {
                    return '<a ' . $before . 'href=' . $quote . $href . $quote . $after . '>';
                }

                if (!preg_match('#^https?://#i', $href)) {
                    return '<a ' . $before . 'href=' . $quote . $href . $quote . $after . '>';
                }

                $tracked = self::absoluteUrl('/newsletter/track/click/' . urlencode($trackToken) . '?url=' . rawurlencode($href));
                return '<a ' . $before . 'href=' . $quote . htmlspecialchars($tracked, ENT_QUOTES, 'UTF-8') . $quote . $after . '>';
            },
            $html
        );
    }

    private static function absoluteUrl(string $path): string
    {
        $appUrl = (string)(getenv('APP_URL') ?: '');
        $base = rtrim($appUrl, '/');

        if ($base !== '') {
            return $base . $path;
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . $path;
    }

    private static function normalizeDateTime($value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $ts);
    }
}
