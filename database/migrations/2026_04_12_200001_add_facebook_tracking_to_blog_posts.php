<?php

/**
 * Migration: columnas de tracking de publicación en Facebook en blog_posts.
 * Paralelas a las `instagram_*` que ya existen.
 */

use Screenart\Musedock\Database;

class AddFacebookTrackingToBlogPosts_2026_04_12_200001
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $cols = $this->listColumns($pdo, $driver, 'blog_posts');

        $toAdd = [
            'facebook_posted_at' => $driver === 'mysql' ? 'DATETIME DEFAULT NULL' : 'TIMESTAMP DEFAULT NULL',
            'facebook_post_id'   => 'VARCHAR(100) DEFAULT NULL',
            'facebook_permalink' => 'VARCHAR(500) DEFAULT NULL',
        ];

        foreach ($toAdd as $name => $def) {
            if (in_array($name, $cols, true)) continue;
            if ($driver === 'mysql') {
                $pdo->exec("ALTER TABLE `blog_posts` ADD COLUMN `{$name}` {$def}");
            } else {
                $pdo->exec("ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS {$name} {$def}");
            }
        }

        if ($driver === 'mysql') {
            try {
                $pdo->exec("CREATE INDEX idx_blog_posts_facebook_posted ON blog_posts(facebook_posted_at)");
            } catch (\Exception $e) { /* exists */ }
        } else {
            $pdo->exec("CREATE INDEX IF NOT EXISTS blog_posts_facebook_posted_idx ON blog_posts(facebook_posted_at)");
        }
    }

    public function down()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("ALTER TABLE `blog_posts`
                DROP COLUMN `facebook_posted_at`,
                DROP COLUMN `facebook_post_id`,
                DROP COLUMN `facebook_permalink`
            ");
        } else {
            $pdo->exec("ALTER TABLE blog_posts DROP COLUMN IF EXISTS facebook_posted_at");
            $pdo->exec("ALTER TABLE blog_posts DROP COLUMN IF EXISTS facebook_post_id");
            $pdo->exec("ALTER TABLE blog_posts DROP COLUMN IF EXISTS facebook_permalink");
        }
    }

    private function listColumns(\PDO $pdo, string $driver, string $table): array
    {
        if ($driver === 'mysql') {
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
            return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'Field');
        }
        $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name = ?");
        $stmt->execute([$table]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}
