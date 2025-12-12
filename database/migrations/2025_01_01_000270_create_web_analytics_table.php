<?php
/**
 * Migration: Create web_analytics table
 * Sistema de Web Analytics integrado compatible con Cloudflare
 * GDPR compliant con anonimización de IPs
 */

use Screenart\Musedock\Database;

class CreateWebAnalyticsTable_2025_01_01_000270
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $this->upMySQL($pdo);
        } else {
            $this->upPostgreSQL($pdo);
        }
    }

    private function upMySQL($pdo)
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS `web_analytics` (
              `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID único',
              `tenant_id` int(11) unsigned DEFAULT NULL COMMENT 'ID del tenant (NULL = global)',
              `session_id` varchar(64) NOT NULL COMMENT 'ID de sesión única del visitante',
              `visitor_id` varchar(64) NOT NULL COMMENT 'ID único del visitante (cookie/fingerprint)',
              `ip_hash` varchar(64) NOT NULL COMMENT 'Hash de IP anonimizada (GDPR)',
              `country` varchar(2) DEFAULT NULL COMMENT 'Código país (Cloudflare)',
              `page_url` varchar(2048) NOT NULL COMMENT 'URL de la página visitada',
              `page_title` varchar(255) DEFAULT NULL COMMENT 'Título de la página',
              `referrer` varchar(2048) DEFAULT NULL COMMENT 'URL de referencia',
              `referrer_domain` varchar(255) DEFAULT NULL COMMENT 'Dominio de referencia',
              `referrer_type` varchar(50) DEFAULT NULL COMMENT 'Tipo: search, social, direct, referral',
              `search_engine` varchar(50) DEFAULT NULL COMMENT 'Buscador: google, bing, etc',
              `search_query` varchar(512) DEFAULT NULL COMMENT 'Query de búsqueda (si disponible)',
              `user_agent` varchar(512) DEFAULT NULL COMMENT 'User Agent',
              `device_type` varchar(20) DEFAULT NULL COMMENT 'desktop, mobile, tablet',
              `browser` varchar(50) DEFAULT NULL COMMENT 'Navegador',
              `os` varchar(50) DEFAULT NULL COMMENT 'Sistema operativo',
              `language` varchar(10) DEFAULT NULL COMMENT 'Idioma del navegador',
              `screen_resolution` varchar(20) DEFAULT NULL COMMENT 'Resolución de pantalla',
              `is_bot` tinyint(1) DEFAULT 0 COMMENT 'Si es un bot/crawler',
              `is_returning` tinyint(1) DEFAULT 0 COMMENT 'Si es visitante recurrente',
              `session_duration` int(11) DEFAULT NULL COMMENT 'Duración de sesión en segundos',
              `page_views` int(11) DEFAULT 1 COMMENT 'Páginas vistas en esta sesión',
              `bounce` tinyint(1) DEFAULT 0 COMMENT 'Si rebotó (solo 1 página)',
              `tracking_enabled` tinyint(1) DEFAULT 1 COMMENT 'Si el usuario aceptó tracking',
              `cf_ray` varchar(64) DEFAULT NULL COMMENT 'Cloudflare Ray ID',
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de visita',
              PRIMARY KEY (`id`),
              KEY `idx_tenant_id` (`tenant_id`),
              KEY `idx_session_id` (`session_id`),
              KEY `idx_visitor_id` (`visitor_id`),
              KEY `idx_country` (`country`),
              KEY `idx_referrer_type` (`referrer_type`),
              KEY `idx_device_type` (`device_type`),
              KEY `idx_created_at` (`created_at`),
              KEY `idx_tenant_date` (`tenant_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Web Analytics - Rastreo de visitantes compatible con GDPR'
        ";

        $pdo->exec($sql);
        echo "✓ Tabla web_analytics creada (MySQL)\n";
    }

    private function upPostgreSQL($pdo)
    {
        // Crear tabla
        $sql = "
            CREATE TABLE IF NOT EXISTS web_analytics (
              id BIGSERIAL PRIMARY KEY,
              tenant_id INTEGER DEFAULT NULL,
              session_id VARCHAR(64) NOT NULL,
              visitor_id VARCHAR(64) NOT NULL,
              ip_hash VARCHAR(64) NOT NULL,
              country VARCHAR(2) DEFAULT NULL,
              page_url VARCHAR(2048) NOT NULL,
              page_title VARCHAR(255) DEFAULT NULL,
              referrer VARCHAR(2048) DEFAULT NULL,
              referrer_domain VARCHAR(255) DEFAULT NULL,
              referrer_type VARCHAR(50) DEFAULT NULL,
              search_engine VARCHAR(50) DEFAULT NULL,
              search_query VARCHAR(512) DEFAULT NULL,
              user_agent VARCHAR(512) DEFAULT NULL,
              device_type VARCHAR(20) DEFAULT NULL,
              browser VARCHAR(50) DEFAULT NULL,
              os VARCHAR(50) DEFAULT NULL,
              language VARCHAR(10) DEFAULT NULL,
              screen_resolution VARCHAR(20) DEFAULT NULL,
              is_bot BOOLEAN DEFAULT FALSE,
              is_returning BOOLEAN DEFAULT FALSE,
              session_duration INTEGER DEFAULT NULL,
              page_views INTEGER DEFAULT 1,
              bounce BOOLEAN DEFAULT FALSE,
              tracking_enabled BOOLEAN DEFAULT TRUE,
              cf_ray VARCHAR(64) DEFAULT NULL,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $pdo->exec($sql);

        // Crear índices
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tenant_id ON web_analytics (tenant_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_session_id ON web_analytics (session_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_visitor_id ON web_analytics (visitor_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_country ON web_analytics (country)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_referrer_type ON web_analytics (referrer_type)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_device_type ON web_analytics (device_type)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_created_at ON web_analytics (created_at)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tenant_date ON web_analytics (tenant_id, created_at)");

        echo "✓ Tabla web_analytics creada (PostgreSQL)\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("DROP TABLE IF EXISTS `web_analytics`");
        } else {
            $pdo->exec("DROP TABLE IF EXISTS web_analytics");
        }

        echo "✓ Tabla web_analytics eliminada\n";
    }
}
