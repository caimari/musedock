<?php
/**
 * Migration: Add Instagram tracking columns to blog_posts
 * Tracks when/if a blog post has been published to Instagram.
 */

use Screenart\Musedock\Database;

class AddInstagramTrackingToBlogPosts_2026_04_12_100001
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                ALTER TABLE `blog_posts`
                ADD COLUMN `instagram_posted_at` DATETIME DEFAULT NULL COMMENT 'When published to Instagram',
                ADD COLUMN `instagram_post_id` VARCHAR(100) DEFAULT NULL COMMENT 'Instagram media ID',
                ADD COLUMN `instagram_permalink` VARCHAR(500) DEFAULT NULL COMMENT 'Public URL on Instagram',
                ADD COLUMN `instagram_connection_id` INT DEFAULT NULL COMMENT 'Which IG connection was used'
            ");

            $pdo->exec("CREATE INDEX idx_blog_posts_instagram_posted ON blog_posts(instagram_posted_at)");
        } else {
            // PostgreSQL — add columns one by one (IF NOT EXISTS in 9.6+)
            $pdo->exec("ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS instagram_posted_at TIMESTAMP DEFAULT NULL");
            $pdo->exec("ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS instagram_post_id VARCHAR(100) DEFAULT NULL");
            $pdo->exec("ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS instagram_permalink VARCHAR(500) DEFAULT NULL");
            $pdo->exec("ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS instagram_connection_id INTEGER DEFAULT NULL");

            $pdo->exec("CREATE INDEX IF NOT EXISTS blog_posts_instagram_posted_idx ON blog_posts(instagram_posted_at)");
        }
    }

    public function down()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("ALTER TABLE `blog_posts`
                DROP COLUMN `instagram_posted_at`,
                DROP COLUMN `instagram_post_id`,
                DROP COLUMN `instagram_permalink`,
                DROP COLUMN `instagram_connection_id`
            ");
        } else {
            $pdo->exec("ALTER TABLE blog_posts DROP COLUMN IF EXISTS instagram_posted_at");
            $pdo->exec("ALTER TABLE blog_posts DROP COLUMN IF EXISTS instagram_post_id");
            $pdo->exec("ALTER TABLE blog_posts DROP COLUMN IF EXISTS instagram_permalink");
            $pdo->exec("ALTER TABLE blog_posts DROP COLUMN IF EXISTS instagram_connection_id");
        }
    }
}
