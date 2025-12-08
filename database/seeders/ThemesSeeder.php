<?php

namespace Screenart\Musedock\Database\Seeders;

use Screenart\Musedock\Database;

/**
 * Seeder para temas del sistema
 */
class ThemesSeeder
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function run(): void
    {
        $themes = [
            [
                'name' => 'default',
                'display_name' => 'Default Theme',
                'description' => 'Tema por defecto de MuseDock - limpio y moderno',
                'version' => '1.0.0',
                'author' => 'MuseDock',
                'status' => 'active',
                'is_default' => 1,
                'settings' => json_encode([
                    'primary_color' => '#3b82f6',
                    'secondary_color' => '#64748b',
                    'font_family' => 'Inter, sans-serif'
                ])
            ],
            [
                'name' => 'starter-blog',
                'display_name' => 'Starter Blog',
                'description' => 'Tema optimizado para blogs',
                'version' => '1.0.0',
                'author' => 'MuseDock',
                'status' => 'active',
                'is_default' => 0,
                'settings' => json_encode([
                    'primary_color' => '#059669',
                    'show_sidebar' => true
                ])
            ],
        ];

        foreach ($themes as $theme) {
            $this->insertIfNotExists($theme);
        }
    }

    private function insertIfNotExists(array $data): void
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM themes WHERE name = ?");
        $stmt->execute([$data['name']]);

        if ($stmt->fetchColumn() == 0) {
            $columns = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            $stmt = $this->db->prepare("INSERT INTO themes ({$columns}) VALUES ({$placeholders})");
            $stmt->execute(array_values($data));
        }
    }
}
