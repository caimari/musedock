<?php

use Screenart\Musedock\Database;

class CreateNewsletterCampaignsTables_2026_04_28_220000
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `newsletter_campaigns` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id` INT UNSIGNED NULL,
                `name` VARCHAR(255) NOT NULL,
                `subject` VARCHAR(255) NOT NULL,
                `preheader` VARCHAR(255) NULL,
                `html_content` MEDIUMTEXT NULL,
                `text_content` MEDIUMTEXT NULL,
                `from_name` VARCHAR(255) NULL,
                `from_email` VARCHAR(255) NULL,
                `reply_to` VARCHAR(255) NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
                `scheduled_at` DATETIME NULL,
                `queued_at` DATETIME NULL,
                `started_at` DATETIME NULL,
                `completed_at` DATETIME NULL,
                `total_recipients` INT UNSIGNED NOT NULL DEFAULT 0,
                `sent_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `failed_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `open_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `click_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_by_type` VARCHAR(20) NULL,
                `created_by_id` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_newsletter_campaigns_tenant` (`tenant_id`),
                KEY `idx_newsletter_campaigns_status` (`status`),
                KEY `idx_newsletter_campaigns_scheduled` (`scheduled_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `newsletter_campaign_deliveries` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `campaign_id` INT UNSIGNED NOT NULL,
                `subscriber_id` INT UNSIGNED NULL,
                `tenant_id` INT UNSIGNED NULL,
                `email` VARCHAR(255) NOT NULL,
                `name` VARCHAR(255) NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
                `track_token` VARCHAR(64) NOT NULL,
                `sent_at` DATETIME NULL,
                `failed_at` DATETIME NULL,
                `error_message` TEXT NULL,
                `open_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `click_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `first_opened_at` DATETIME NULL,
                `last_opened_at` DATETIME NULL,
                `first_clicked_at` DATETIME NULL,
                `last_clicked_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_newsletter_delivery_track` (`track_token`),
                UNIQUE KEY `uniq_newsletter_campaign_subscriber` (`campaign_id`, `subscriber_id`),
                KEY `idx_newsletter_delivery_campaign_status` (`campaign_id`, `status`),
                KEY `idx_newsletter_delivery_pending` (`status`, `id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS newsletter_campaigns (
                id SERIAL PRIMARY KEY,
                tenant_id INTEGER NULL,
                name VARCHAR(255) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                preheader VARCHAR(255) NULL,
                html_content TEXT NULL,
                text_content TEXT NULL,
                from_name VARCHAR(255) NULL,
                from_email VARCHAR(255) NULL,
                reply_to VARCHAR(255) NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                scheduled_at TIMESTAMP NULL,
                queued_at TIMESTAMP NULL,
                started_at TIMESTAMP NULL,
                completed_at TIMESTAMP NULL,
                total_recipients INTEGER NOT NULL DEFAULT 0,
                sent_count INTEGER NOT NULL DEFAULT 0,
                failed_count INTEGER NOT NULL DEFAULT 0,
                open_count INTEGER NOT NULL DEFAULT 0,
                click_count INTEGER NOT NULL DEFAULT 0,
                created_by_type VARCHAR(20) NULL,
                created_by_id INTEGER NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CHECK (status IN ('draft','queued','sending','sent','paused','cancelled'))
            )");

            $pdo->exec("CREATE INDEX IF NOT EXISTS newsletter_campaigns_idx_tenant ON newsletter_campaigns(tenant_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS newsletter_campaigns_idx_status ON newsletter_campaigns(status)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS newsletter_campaigns_idx_scheduled ON newsletter_campaigns(scheduled_at)");

            $pdo->exec("CREATE TABLE IF NOT EXISTS newsletter_campaign_deliveries (
                id BIGSERIAL PRIMARY KEY,
                campaign_id INTEGER NOT NULL,
                subscriber_id INTEGER NULL,
                tenant_id INTEGER NULL,
                email VARCHAR(255) NOT NULL,
                name VARCHAR(255) NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                track_token VARCHAR(64) NOT NULL UNIQUE,
                sent_at TIMESTAMP NULL,
                failed_at TIMESTAMP NULL,
                error_message TEXT NULL,
                open_count INTEGER NOT NULL DEFAULT 0,
                click_count INTEGER NOT NULL DEFAULT 0,
                first_opened_at TIMESTAMP NULL,
                last_opened_at TIMESTAMP NULL,
                first_clicked_at TIMESTAMP NULL,
                last_clicked_at TIMESTAMP NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (campaign_id, subscriber_id),
                CHECK (status IN ('pending','sent','failed'))
            )");

            $pdo->exec("CREATE INDEX IF NOT EXISTS newsletter_delivery_idx_campaign_status ON newsletter_campaign_deliveries(campaign_id, status)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS newsletter_delivery_idx_pending ON newsletter_campaign_deliveries(status, id)");
        }

        echo "✓ Newsletter campaigns tables created\n";

        // Registrar tarea de cron newsletter_queue si no existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM scheduled_tasks WHERE task_name = ?");
        $stmt->execute(['newsletter_queue']);
        if ((int)$stmt->fetchColumn() === 0) {
            $ins = $pdo->prepare("INSERT INTO scheduled_tasks
                (task_name, next_run, status, run_count, success_count, fail_count, created_at, updated_at)
                VALUES (?, NOW(), 'idle', 0, 0, 0, NOW(), NOW())");
            $ins->execute(['newsletter_queue']);
        }

        // Garantizar también entradas de tareas base (evita que CronService las salte por falta de fila)
        foreach (['cleanup_trash', 'cleanup_revisions'] as $taskName) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM scheduled_tasks WHERE task_name = ?");
            $stmt->execute([$taskName]);
            if ((int)$stmt->fetchColumn() === 0) {
                $ins = $pdo->prepare("INSERT INTO scheduled_tasks
                    (task_name, next_run, status, run_count, success_count, fail_count, created_at, updated_at)
                    VALUES (?, NOW(), 'idle', 0, 0, 0, NOW(), NOW())");
                $ins->execute([$taskName]);
            }
        }

        echo "✓ Scheduled task entries ensured\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("DROP TABLE IF EXISTS `newsletter_campaign_deliveries`");
            $pdo->exec("DROP TABLE IF EXISTS `newsletter_campaigns`");
        } else {
            $pdo->exec("DROP TABLE IF EXISTS newsletter_campaign_deliveries");
            $pdo->exec("DROP TABLE IF EXISTS newsletter_campaigns");
        }

        $stmt = $pdo->prepare("DELETE FROM scheduled_tasks WHERE task_name = ?");
        $stmt->execute(['newsletter_queue']);

        echo "✓ Newsletter campaigns tables dropped\n";
    }
}
