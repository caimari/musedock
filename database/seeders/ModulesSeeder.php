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
                'slug' => 'blog',
                'name' => 'Blog',
                'description' => 'Sistema de Blog completo con categorías, etiquetas y traducciones multiidioma para MuseDock CMS.',
                'version' => '1.0.0',
                'author' => 'MuseDock Team',
                'active' => 1,
                'public' => 0,
                'cms_enabled' => 1,
                'tenant_enabled_default' => 1
            ],
            [
                'slug' => 'media-manager',
                'name' => 'Media Manager',
                'description' => 'Gestor de archivos y biblioteca de medios para MuseDock CMS.',
                'version' => '1.0.0',
                'author' => 'MuseDock Team',
                'active' => 1,
                'public' => 0,
                'cms_enabled' => 1,
                'tenant_enabled_default' => 1
            ],
            [
                'slug' => 'ai-writer',
                'name' => 'AI Writer',
                'description' => 'Integración de IA con TinyMCE para generar y mejorar contenido',
                'version' => '1.0.0',
                'author' => 'MuseDock',
                'active' => 0,
                'public' => 0,
                'cms_enabled' => 1,
                'tenant_enabled_default' => 1
            ],
            [
                'slug' => 'react-sliders',
                'name' => 'React Sliders',
                'description' => 'Sistema moderno de sliders con React y Tailwind CSS',
                'version' => '1.0.0',
                'author' => 'MuseDock',
                'active' => 0,
                'public' => 0,
                'cms_enabled' => 1,
                'tenant_enabled_default' => 1
            ],
            [
                'slug' => 'custom-forms',
                'name' => 'Custom Forms',
                'description' => 'Creador de formularios personalizados con drag & drop, múltiples tipos de campos y gestión de envíos',
                'version' => '1.0.0',
                'author' => 'MuseDock',
                'active' => 0,
                'public' => 0,
                'cms_enabled' => 1,
                'tenant_enabled_default' => 1
            ],
            [
                'slug' => 'image-gallery',
                'name' => 'Image Gallery',
                'description' => 'Módulo de galerías de imágenes con múltiples layouts, shortcodes y almacenamiento permanente',
                'version' => '1.0.0',
                'author' => 'MuseDock',
                'active' => 0,
                'public' => 0,
                'cms_enabled' => 1,
                'tenant_enabled_default' => 1
            ],
            [
                'slug' => 'elements',
                'name' => 'Elements',
                'description' => 'Bootstrap-based reusable elements (Heroes, FAQs, CTAs, etc.) with shortcode support',
                'version' => '1.0.0',
                'author' => 'MuseDock',
                'active' => 1,
                'public' => 0,
                'cms_enabled' => 1,
                'tenant_enabled_default' => 1
            ],
        ];

        foreach ($modules as $module) {
            $this->insertIfNotExists($module);
        }

        echo "    + Módulos base creados\n";
    }

    private function insertIfNotExists(array $data): void
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM modules WHERE slug = ?");
        $stmt->execute([$data['slug']]);

        if ($stmt->fetchColumn() == 0) {
            $columns = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            $stmt = $this->db->prepare("INSERT INTO modules ({$columns}) VALUES ({$placeholders})");
            $stmt->execute(array_values($data));
        }
    }
}
