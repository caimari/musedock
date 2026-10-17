<?php
use Screenart\Musedock\Database;

/** Mostrar el número de visitas en cada entrada: desactivado por defecto, se activa por entrada. */
class AddShowViewsToBlogPosts_2026_10_17_230000
{
    public function up(): void
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'pgsql') {
            $pdo->exec('ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS show_views SMALLINT NOT NULL DEFAULT 0');
            return;
        }
        $has = $pdo->query("SHOW COLUMNS FROM blog_posts LIKE 'show_views'")->fetch();
        if (!$has) {
            $pdo->exec('ALTER TABLE blog_posts ADD COLUMN show_views TINYINT(1) NOT NULL DEFAULT 0');
        }
    }

    public function down(): void { /* Se conserva la columna. */ }
}
