<?php

/**
 * Migration: Create Image Galleries Tables
 *
 * Creates the necessary tables for the image gallery module:
 * - image_galleries: Main gallery storage
 * - gallery_images: Individual images within galleries
 * - gallery_settings: Global settings for the module
 *
 * Compatible with: MySQL/MariaDB + PostgreSQL
 */
class CreateImageGalleriesTables_2025_12_01_000000
{
    public function up()
    {
        $pdo = \Screenart\Musedock\Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $this->upMySQL($pdo);
        } else {
            $this->upPostgreSQL($pdo);
        }

        error_log("ImageGallery: Tables created successfully");
    }

    private function upMySQL($pdo)
    {
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
        $this->insertDefaultSettings($pdo, 'mysql');
    }

    private function upPostgreSQL($pdo)
    {
        // Tabla principal de galerías
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS image_galleries (
                id SERIAL PRIMARY KEY,
                tenant_id INTEGER DEFAULT NULL,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                description TEXT,
                thumbnail_url VARCHAR(500) DEFAULT NULL,
                layout_type VARCHAR(20) DEFAULT 'grid',
                columns SMALLINT DEFAULT 3,
                gap SMALLINT DEFAULT 10,
                settings JSONB DEFAULT NULL,
                is_active SMALLINT DEFAULT 1,
                featured SMALLINT DEFAULT 0,
                sort_order INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CHECK (layout_type IN ('grid', 'masonry', 'carousel', 'lightbox', 'justified')),
                UNIQUE (slug, tenant_id)
            )
        ");

        // Crear índices para image_galleries
        $pdo->exec("CREATE INDEX IF NOT EXISTS image_galleries_idx_tenant_id ON image_galleries(tenant_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS image_galleries_idx_is_active ON image_galleries(is_active)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS image_galleries_idx_featured ON image_galleries(featured)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS image_galleries_idx_layout_type ON image_galleries(layout_type)");

        // Agregar comentarios
        $pdo->exec("COMMENT ON COLUMN image_galleries.tenant_id IS 'NULL = galería global'");
        $pdo->exec("COMMENT ON COLUMN image_galleries.columns IS 'Número de columnas (1-6)'");
        $pdo->exec("COMMENT ON COLUMN image_galleries.gap IS 'Espacio entre imágenes en px'");
        $pdo->exec("COMMENT ON COLUMN image_galleries.settings IS 'Configuración adicional JSON'");

        // Tabla de imágenes de las galerías
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS gallery_images (
                id SERIAL PRIMARY KEY,
                gallery_id INTEGER NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                file_hash VARCHAR(64) DEFAULT NULL,
                file_size INTEGER DEFAULT 0,
                mime_type VARCHAR(100) DEFAULT NULL,
                image_url VARCHAR(500) NOT NULL,
                thumbnail_url VARCHAR(500) DEFAULT NULL,
                medium_url VARCHAR(500) DEFAULT NULL,
                title VARCHAR(255) DEFAULT NULL,
                alt_text VARCHAR(255) DEFAULT NULL,
                caption TEXT,
                link_url VARCHAR(500) DEFAULT NULL,
                link_target VARCHAR(10) DEFAULT '_self',
                width INTEGER DEFAULT NULL,
                height INTEGER DEFAULT NULL,
                sort_order INTEGER DEFAULT 0,
                is_active SMALLINT DEFAULT 1,
                metadata JSONB DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CHECK (link_target IN ('_self', '_blank')),
                CONSTRAINT fk_gallery_images_gallery
                    FOREIGN KEY (gallery_id)
                    REFERENCES image_galleries (id)
                    ON DELETE CASCADE
            )
        ");

        // Crear índices para gallery_images
        $pdo->exec("CREATE INDEX IF NOT EXISTS gallery_images_idx_gallery_id ON gallery_images(gallery_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS gallery_images_idx_sort_order ON gallery_images(sort_order)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS gallery_images_idx_is_active ON gallery_images(is_active)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS gallery_images_idx_file_hash ON gallery_images(file_hash)");

        // Agregar comentarios para gallery_images
        $pdo->exec("COMMENT ON COLUMN gallery_images.file_name IS 'Nombre original del archivo'");
        $pdo->exec("COMMENT ON COLUMN gallery_images.file_path IS 'Ruta interna del archivo'");
        $pdo->exec("COMMENT ON COLUMN gallery_images.file_hash IS 'Hash SHA256 para verificación'");
        $pdo->exec("COMMENT ON COLUMN gallery_images.file_size IS 'Tamaño en bytes'");
        $pdo->exec("COMMENT ON COLUMN gallery_images.image_url IS 'URL pública permanente'");
        $pdo->exec("COMMENT ON COLUMN gallery_images.thumbnail_url IS 'URL de miniatura'");
        $pdo->exec("COMMENT ON COLUMN gallery_images.medium_url IS 'URL de tamaño medio'");
        $pdo->exec("COMMENT ON COLUMN gallery_images.alt_text IS 'Texto alternativo para SEO'");
        $pdo->exec("COMMENT ON COLUMN gallery_images.caption IS 'Descripción de la imagen'");
        $pdo->exec("COMMENT ON COLUMN gallery_images.link_url IS 'URL de enlace opcional'");
        $pdo->exec("COMMENT ON COLUMN gallery_images.width IS 'Ancho original en px'");
        $pdo->exec("COMMENT ON COLUMN gallery_images.height IS 'Alto original en px'");
        $pdo->exec("COMMENT ON COLUMN gallery_images.metadata IS 'Metadatos EXIF y adicionales'");

        // Tabla de configuraciones de galería por tenant
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS gallery_settings (
                id SERIAL PRIMARY KEY,
                tenant_id INTEGER DEFAULT NULL,
                setting_key VARCHAR(100) NOT NULL,
                setting_value TEXT,
                setting_type VARCHAR(20) DEFAULT 'string',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CHECK (setting_type IN ('string', 'int', 'bool', 'json', 'array')),
                UNIQUE (setting_key, tenant_id)
            )
        ");

        // Crear índices para gallery_settings
        $pdo->exec("CREATE INDEX IF NOT EXISTS gallery_settings_idx_tenant_id ON gallery_settings(tenant_id)");

        // Agregar comentarios para gallery_settings
        $pdo->exec("COMMENT ON COLUMN gallery_settings.tenant_id IS 'NULL = configuración global'");

        // Insertar configuraciones por defecto
        $this->insertDefaultSettings($pdo, 'pgsql');
    }

    private function insertDefaultSettings($pdo, $driver)
    {
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

        if ($driver === 'mysql') {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO gallery_settings
                (tenant_id, setting_key, setting_value, setting_type)
                VALUES (NULL, ?, ?, ?)
            ");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO gallery_settings
                (tenant_id, setting_key, setting_value, setting_type)
                VALUES (NULL, ?, ?, ?)
                ON CONFLICT (setting_key, tenant_id) DO NOTHING
            ");
        }

        foreach ($defaultSettings as $setting) {
            $stmt->execute($setting);
        }
    }

    public function down()
    {
        $pdo = \Screenart\Musedock\Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            // MySQL: Deshabilitar foreign keys temporalmente
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $pdo->exec("DROP TABLE IF EXISTS `gallery_images`");
            $pdo->exec("DROP TABLE IF EXISTS `gallery_settings`");
            $pdo->exec("DROP TABLE IF EXISTS `image_galleries`");
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        } else {
            // PostgreSQL: CASCADE elimina dependencias automáticamente
            $pdo->exec("DROP TABLE IF EXISTS gallery_images CASCADE");
            $pdo->exec("DROP TABLE IF EXISTS gallery_settings CASCADE");
            $pdo->exec("DROP TABLE IF EXISTS image_galleries CASCADE");
        }

        error_log("ImageGallery: Tables dropped successfully");
    }
}
