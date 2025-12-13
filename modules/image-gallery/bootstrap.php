<?php

/**
 * Image Gallery Module Bootstrap
 *
 * Inicializa el módulo de galerías de imágenes
 */

namespace ImageGallery;

// Evitar carga múltiple
if (defined('IMAGE_GALLERY_LOADED')) {
    return;
}

define('IMAGE_GALLERY_LOADED', true);
define('IMAGE_GALLERY_PATH', __DIR__);
define('IMAGE_GALLERY_VERSION', '1.0.0');

require_once __DIR__ . '/../module-menu-helper.php';

// ============================================================================
// VERIFICAR E INSTALAR TABLAS
// ============================================================================

try {
    $pdo = \Screenart\Musedock\Database::connect();

    // Verificar si las tablas existen
    $stmt = $pdo->query("SHOW TABLES LIKE 'image_galleries'");
    $tableExists = $stmt->fetch();

    if (!$tableExists) {
        // Ejecutar migración automática
        error_log("ImageGallery: Tables not found, running migration...");

        $migrationFile = __DIR__ . '/migrations/2025_12_01_000000_create_image_galleries_tables.php';

        if (file_exists($migrationFile)) {
            require_once $migrationFile;

            $migration = new \CreateImageGalleriesTables_2025_12_01_000000();
            $migration->up();

            error_log("ImageGallery: Migration completed successfully");
        } else {
            error_log("ImageGallery: Migration file not found: " . $migrationFile);
        }
    }

} catch (\Exception $e) {
    error_log("ImageGallery: Error during auto-installation: " . $e->getMessage());
}

// ============================================================================
// CARGAR DEPENDENCIAS
// ============================================================================

// Cargar helpers (incluye funciones de traducción y shortcodes)
require_once __DIR__ . '/helpers.php';

// Cargar modelos
require_once __DIR__ . '/models/Gallery.php';
require_once __DIR__ . '/models/GalleryImage.php';
require_once __DIR__ . '/models/GallerySetting.php';

// Cargar controladores - Superadmin
require_once __DIR__ . '/controllers/Superadmin/GalleryController.php';
require_once __DIR__ . '/controllers/Superadmin/ImageController.php';

// Cargar controladores - Tenant
require_once __DIR__ . '/controllers/Tenant/GalleryController.php';
require_once __DIR__ . '/controllers/Tenant/ImageController.php';

// ============================================================================
// REGISTRAR RUTAS
// ============================================================================

// Las rutas se cargan automáticamente desde routes.php por el sistema de módulos

// ============================================================================
// REGISTRAR PROCESADOR DE SHORTCODES
// ============================================================================

// El procesador de shortcodes ya se registra en helpers.php
// Aquí aseguramos que esté disponible globalmente

if (!function_exists('apply_gallery_shortcodes')) {
    /**
     * Aplica shortcodes de galería a un contenido
     * Función de conveniencia para usar desde templates
     *
     * @param string $content
     * @return string
     */
    function apply_gallery_shortcodes(string $content): string
    {
        return process_gallery_shortcodes($content);
    }
}

// ============================================================================
// HOOKS DE CONTENIDO
// ============================================================================

// Registrar el filtro de contenido para que se procese automáticamente
// en páginas y posts del CMS

// Registrar filtro para procesar shortcodes en el contenido
add_filter('the_content', function ($content) {
    return process_gallery_shortcodes($content);
}, 10);

// ============================================================================
// CREAR DIRECTORIOS NECESARIOS
// ============================================================================

$uploadDirs = [
    $_SERVER['DOCUMENT_ROOT'] . '/uploads/galleries',
    __DIR__ . '/../../../public/modules/image-gallery/css',
    __DIR__ . '/../../../public/modules/image-gallery/js',
    __DIR__ . '/../../../public/modules/image-gallery/images',
];

foreach ($uploadDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// ============================================================================
// REGISTRO COMPLETADO
// ============================================================================

error_log("ImageGallery: Module loaded successfully (v" . IMAGE_GALLERY_VERSION . ")");

\register_module_admin_menu([
    'module_slug'    => 'image-gallery',
    'menu_slug'      => 'appearance-image-gallery',
    'title'          => 'Galerías',
    'superadmin_url' => '{admin_path}/image-gallery',
    'tenant_url'     => '{admin_path}/image-gallery',
    'parent_slug'    => 'appearance',
    'icon'           => 'images',
    'icon_type'      => 'bi',
    'order'          => 5,
    'permission'     => 'image_gallery.manage',
]);
