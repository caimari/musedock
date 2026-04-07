<?php

use Screenart\Musedock\Database;

class CreateCloudDnsRecordsTable_2026_04_07_000013
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `cloud_dns_records` (
                    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `domain_id` int(10) unsigned NOT NULL,
                    `cloudflare_record_id` varchar(100) DEFAULT NULL,
                    `type` varchar(10) NOT NULL COMMENT 'A, AAAA, CNAME, MX, TXT, SRV',
                    `name` varchar(255) NOT NULL COMMENT '@, www, mail, etc.',
                    `content` varchar(500) NOT NULL,
                    `ttl` int(11) NOT NULL DEFAULT 3600,
                    `proxied` tinyint(1) NOT NULL DEFAULT 0,
                    `priority` int(11) DEFAULT NULL COMMENT 'For MX records',
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_cloud_dns_domain` (`domain_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS cloud_dns_records (
                    id SERIAL PRIMARY KEY,
                    domain_id INTEGER NOT NULL,
                    cloudflare_record_id VARCHAR(100) DEFAULT NULL,
                    type VARCHAR(10) NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    content VARCHAR(500) NOT NULL,
                    ttl INTEGER NOT NULL DEFAULT 3600,
                    proxied SMALLINT NOT NULL DEFAULT 0,
                    priority INTEGER DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT NULL
                )
            ");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cloud_dns_domain ON cloud_dns_records (domain_id)");
        }
    }

    public function down()
    {
        Database::connect()->exec("DROP TABLE IF EXISTS cloud_dns_records");
    }
}
