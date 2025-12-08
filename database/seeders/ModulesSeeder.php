<?php

namespace Screenart\Musedock\Database\Seeders;

use Screenart\Musedock\Database;

/**
 * Seeder para módulos base del sistema
 */
class ModulesSeeder
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function run(): void
    {
        $modules = [
            [
                'name' => 'blog',
                'display_name' => 'Blog',
                'description' => 'Sistema de blog con posts, categorías y tags',
                'version' => '1.0.0',
                'author' => 'MuseDock',
                'status' => 'active',
                'is_core' => 0,
                'settings' => json_encode([
                    'posts_per_page' => 10,
                    'enable_comments' => true,
                    'enable_revisions' => true
                ])
            ],
            [
                'name' => 'media-manager',
                'display_name' => 'Gestor de Medios',
                'description' => 'Gestión de archivos multimedia',
                'version' => '1.0.0',
                'author' => 'MuseDock',
                'status' => 'active',
                'is_core' => 1,
                'settings' => json_encode([
                    'max_upload_size' => 10485760,
                    'allowed_types' => ['image', 'document', 'video']
                ])
            ],
            [
                'name' => 'ai-writer',
                'display_name' => 'AI Writer',
                'description' => 'Generador de contenido con IA',
                'version' => '1.0.0',
                'author' => 'MuseDock',
                'status' => 'inactive',
                'is_core' => 0,
                'settings' => json_encode([
                    'default_provider' => 'openai',
                    'max_tokens' => 2000
                ])
            ],
            [
                'name' => 'custom-forms',
                'display_name' => 'Formularios Personalizados',
                'description' => 'Constructor de formularios drag & drop',
                'version' => '1.0.0',
                'author' => 'MuseDock',
                'status' => 'inactive',
                'is_core' => 0,
                'settings' => json_encode([])
            ],
            [
                'name' => 'image-gallery',
                'display_name' => 'Galería de Imágenes',
                'description' => 'Galería de imágenes con álbumes',
                'version' => '1.0.0',
                'author' => 'MuseDock',
                'status' => 'inactive',
                'is_core' => 0,
                'settings' => json_encode([])
            ],
        ];

        foreach ($modules as $module) {
            $this->insertIfNotExists($module);
        }
    }

    private function insertIfNotExists(array $data): void
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM modules WHERE name = ?");
        $stmt->execute([$data['name']]);

        if ($stmt->fetchColumn() == 0) {
            $columns = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            $stmt = $this->db->prepare("INSERT INTO modules ({$columns}) VALUES ({$placeholders})");
            $stmt->execute(array_values($data));
        }
    }
}
