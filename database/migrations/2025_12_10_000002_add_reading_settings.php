<?php
/**
 * Migration: Add reading settings
 * Generated at: 2025_12_10_000002
 */

use Screenart\Musedock\Database;

class AddReadingSettings_2025_12_10_000002
{
    public function up()
    {
        $pdo = Database::connect();

        // Ajustes de lectura para el sitio
        $settings = [
            'show_on_front' => 'posts', // 'posts', 'page' o 'post'
            'page_on_front' => '', // ID de la página de inicio (si show_on_front = 'page')
            'post_on_front' => '', // ID del post de inicio (si show_on_front = 'post')
            'posts_per_page' => '10', // Número de posts por página
            'posts_per_rss' => '10', // Número de posts en el feed RSS
            'blog_public' => '1', // Visibilidad en buscadores: 1 = indexar (default), 0 = no indexar
        ];

        foreach ($settings as $key => $value) {
            // Verificar si ya existe el setting
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE `key` = ?");
            $stmt->execute([$key]);
            $exists = $stmt->fetchColumn();

            if (!$exists) {
                $insertStmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?)");
                $insertStmt->execute([$key, $value]);
                echo "✓ Setting '{$key}' added with value '{$value}'\n";
            } else {
                echo "⊘ Setting '{$key}' already exists\n";
            }
        }
    }

    public function down()
    {
        $pdo = Database::connect();

        $settings = ['show_on_front', 'page_on_front', 'post_on_front', 'posts_per_page', 'posts_per_rss', 'blog_public'];

        foreach ($settings as $key) {
            $stmt = $pdo->prepare("DELETE FROM settings WHERE `key` = ?");
            $stmt->execute([$key]);
            echo "✓ Setting '{$key}' removed\n";
        }
    }
}
