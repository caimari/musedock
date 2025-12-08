<?php

/**
 * Migration: Create Image Galleries Tables
 *
 * Creates the necessary tables for the image gallery module:
 * - image_galleries: Main gallery storage
 * - gallery_images: Individual images within galleries
 * - gallery_settings: Global settings for the module
 */
class CreateImageGalleriesTables_2025_12_01_000000
{
    public function up()
    {
        $pdo = \Screenart\Musedock\Database::connect();

        // Tabla principal de galerías
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `image_galleries` (
                `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id` int(11) UNSIGNED DEFAULT NULL COMMENT 'NULL = galería global',
                `name` varchar(255) NOT NULL,
                `slug` varchar(255) NOT NULL,
                `description` text,
                `thumbnail_url` varchar(500) DEFAULT NULL,
                `layout_type` enum('grid','masonry','carousel','lightbox','justified') DEFAULT 'grid',
                `columns` tinyint(1) DEFAULT 3 COMMENT 'Número de columnas (1-6)',
                `gap` smallint(5) DEFAULT 10 COMMENT 'Espacio entre imágenes en px',
                `settings` json DEFAULT NULL COMMENT 'Configuración adicional JSON',
                `is_active` tinyint(1) DEFAULT 1,
                `featured` tinyint(1) DEFAULT 0,
                `sort_order` int(11) DEFAULT 0,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_slug_tenant` (`slug`, `tenant_id`),
                KEY `idx_tenant_id` (`tenant_id`),
                KEY `idx_is_active` (`is_active`),
                KEY `idx_featured` (`featured`),
                KEY `idx_layout_type` (`layout_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Tabla de imágenes de las galerías
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `gallery_images` (
                `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `gallery_id` int(11) UNSIGNED NOT NULL,
                `file_name` varchar(255) NOT NULL COMMENT 'Nombre original del archivo',
                `file_path` varchar(500) NOT NULL COMMENT 'Ruta interna del archivo',
                `file_hash` varchar(64) DEFAULT NULL COMMENT 'Hash SHA256 para verificación',
                `file_size` int(11) UNSIGNED DEFAULT 0 COMMENT 'Tamaño en bytes',
                `mime_type` varchar(100) DEFAULT NULL,
                `image_url` varchar(500) NOT NULL COMMENT 'URL pública permanente',
                `thumbnail_url` varchar(500) DEFAULT NULL COMMENT 'URL de miniatura',
                `medium_url` varchar(500) DEFAULT NULL COMMENT 'URL de tamaño medio',
                `title` varchar(255) DEFAULT NULL,
                `alt_text` varchar(255) DEFAULT NULL COMMENT 'Texto alternativo para SEO',
                `caption` text COMMENT 'Descripción de la imagen',
                `link_url` varchar(500) DEFAULT NULL COMMENT 'URL de enlace opcional',
                `link_target` enum('_self','_blank') DEFAULT '_self',
                `width` int(11) UNSIGNED DEFAULT NULL COMMENT 'Ancho original en px',
                `height` int(11) UNSIGNED DEFAULT NULL COMMENT 'Alto original en px',
                `sort_order` int(11) DEFAULT 0,
                `is_active` tinyint(1) DEFAULT 1,
                `metadata` json DEFAULT NULL COMMENT 'Metadatos EXIF y adicionales',
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_gallery_id` (`gallery_id`),
                KEY `idx_sort_order` (`sort_order`),
                KEY `idx_is_active` (`is_active`),
                KEY `idx_file_hash` (`file_hash`),
                CONSTRAINT `fk_gallery_images_gallery`
                    FOREIGN KEY (`gallery_id`)
                    REFERENCES `image_galleries` (`id`)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Tabla de configuraciones de galería por tenant
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `gallery_settings` (
                `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id` int(11) UNSIGNED DEFAULT NULL COMMENT 'NULL = configuración global',
                `setting_key` varchar(100) NOT NULL,
                `setting_value` text,
                `setting_type` enum('string','int','bool','json','array') DEFAULT 'string',
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_setting_tenant` (`setting_key`, `tenant_id`),
                KEY `idx_tenant_id` (`tenant_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Insertar configuraciones por defecto
        $defaultSettings = [
            ['default_layout', 'grid', 'string'],
            ['default_columns', '3', 'int'],
            ['default_gap', '10', 'int'],
            ['max_upload_size_mb', '10', 'int'],
            ['allowed_extensions', 'jpg,jpeg,png,gif,webp,svg', 'string'],
            ['image_quality', '85', 'int'],
            ['auto_generate_thumbnails', '1', 'bool'],
            ['thumbnail_width', '150', 'int'],
            ['thumbnail_height', '150', 'int'],
            ['medium_width', '600', 'int'],
            ['medium_height', '600', 'int'],
            ['enable_lightbox', '1', 'bool'],
            ['enable_lazy_loading', '1', 'bool'],
            ['watermark_enabled', '0', 'bool'],
            ['watermark_text', '', 'string'],
            ['watermark_position', 'bottom-right', 'string'],
        ];

        $stmt = $pdo->prepare("
            INSERT IGNORE INTO `gallery_settings`
            (`tenant_id`, `setting_key`, `setting_value`, `setting_type`)
            VALUES (NULL, ?, ?, ?)
        ");

        foreach ($defaultSettings as $setting) {
            $stmt->execute($setting);
        }

        error_log("ImageGallery: Tables created successfully");
    }

    public function down()
    {
        $pdo = \Screenart\Musedock\Database::connect();

        // Eliminar en orden inverso por las foreign keys
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->exec("DROP TABLE IF EXISTS `gallery_images`");
        $pdo->exec("DROP TABLE IF EXISTS `gallery_settings`");
        $pdo->exec("DROP TABLE IF EXISTS `image_galleries`");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        error_log("ImageGallery: Tables dropped successfully");
    }
}
