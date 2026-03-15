<?php
/**
 * Migration: Create blog_post_views table
 * Tracking de visitas únicas por IP para blog posts
 * GDPR compliant con hash de IP + fecha diaria
 */

use Screenart\Musedock\Database;

class CreateBlogPostViewsTable_2026_03_05_000001
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
            CREATE TABLE IF NOT EXISTS `blog_post_views` (
              `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID único',
              `post_id` int(10) unsigned NOT NULL COMMENT 'FK blog_posts.id',
              `tenant_id` int(11) DEFAULT NULL COMMENT 'Tenant ID para filtrado',
              `ip_hash` varchar(64) NOT NULL COMMENT 'SHA-256 hash de IP + fecha diaria (GDPR)',
              `viewed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de la vista',
              PRIMARY KEY (`id`),
              UNIQUE KEY `unique_post_ip` (`post_id`, `ip_hash`),
              KEY `idx_bpv_tenant_id` (`tenant_id`),
              KEY `idx_bpv_viewed_at` (`viewed_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Blog post unique view tracking - GDPR compliant'
        ";

        $pdo->exec($sql);
        echo "✓ Tabla blog_post_views creada (MySQL)\n";
    }

    private function upPostgreSQL($pdo)
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS blog_post_views (
              id BIGSERIAL PRIMARY KEY,
              post_id INTEGER NOT NULL,
              tenant_id INTEGER DEFAULT NULL,
              ip_hash VARCHAR(64) NOT NULL,
              viewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $pdo->exec($sql);

        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS unique_post_ip ON blog_post_views (post_id, ip_hash)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_bpv_tenant_id ON blog_post_views (tenant_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_bpv_viewed_at ON blog_post_views (viewed_at)");

        echo "✓ Tabla blog_post_views creada (PostgreSQL)\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("DROP TABLE IF EXISTS `blog_post_views`");
        } else {
            $pdo->exec("DROP TABLE IF EXISTS blog_post_views");
        }

        echo "✓ Tabla blog_post_views eliminada\n";
    }
}
