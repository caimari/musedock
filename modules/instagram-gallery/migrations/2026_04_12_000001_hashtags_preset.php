<?php

/**
 * Migration: columna `hashtags_preset` en instagram_connections.
 *
 * Guarda hashtags fijos por cuenta (uno por línea o separados por espacio)
 * que se añaden al caption al publicar posts del blog, con prioridad sobre
 * los dinámicos. Ej: "#freenetes #tecnologia #madrid".
 */
class HashtagsPreset_2026_04_12_000001
{
    public function up()
    {
        $pdo = \Screenart\Musedock\Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        $cols = $this->listColumns($pdo, $driver, 'instagram_connections');
        if (in_array('hashtags_preset', $cols, true)) return;

        if ($driver === 'mysql') {
            $pdo->exec("ALTER TABLE instagram_connections ADD COLUMN hashtags_preset TEXT DEFAULT NULL AFTER redirect_uri");
        } else {
            $pdo->exec("ALTER TABLE instagram_connections ADD COLUMN IF NOT EXISTS hashtags_preset TEXT DEFAULT NULL");
        }

        error_log('InstagramGallery: hashtags_preset column added');
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
