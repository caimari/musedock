<?php

/**
 * Migration: Create Instagram Gallery Tables
 *
 * Creates the necessary tables for the Instagram gallery module:
 * - instagram_connections: Instagram account connections
 * - instagram_posts: Cached Instagram posts
 * - instagram_settings: Global settings for the module
 *
 * Compatible with: MySQL/MariaDB + PostgreSQL
 */
class CreateInstagramGalleryTables_2025_12_14_000000
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

        error_log("InstagramGallery: Tables created successfully");
    }

    private function upMySQL($pdo)
    {
        // Tabla de conexiones de Instagram
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `instagram_connections` (
                `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id` int(11) UNSIGNED DEFAULT NULL COMMENT 'NULL = conexión de SuperAdmin',
                `user_id` int(11) UNSIGNED DEFAULT NULL COMMENT 'ID del usuario que conectó',
                `instagram_user_id` varchar(255) NOT NULL COMMENT 'ID de usuario de Instagram',
                `username` varchar(255) NOT NULL COMMENT 'Username de Instagram',
                `profile_picture` varchar(500) DEFAULT NULL,
                `access_token` text NOT NULL COMMENT 'Token de acceso (encriptado)',
                `refresh_token` text DEFAULT NULL COMMENT 'Token de refresco',
                `token_expires_at` datetime NOT NULL COMMENT 'Fecha de expiración del token',
                `is_active` tinyint(1) DEFAULT 1,
                `last_synced_at` datetime DEFAULT NULL COMMENT 'Última sincronización de posts',
                `last_error` text DEFAULT NULL COMMENT 'Último error de sincronización',
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_instagram_user_tenant` (`instagram_user_id`, `tenant_id`),
                KEY `idx_tenant_id` (`tenant_id`),
                KEY `idx_is_active` (`is_active`),
                KEY `idx_token_expires_at` (`token_expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Tabla de posts de Instagram (cache)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `instagram_posts` (
                `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `connection_id` int(11) UNSIGNED NOT NULL,
                `instagram_id` varchar(255) NOT NULL COMMENT 'ID único del post en Instagram',
                `media_type` enum('IMAGE','VIDEO','CAROUSEL_ALBUM') DEFAULT 'IMAGE',
                `media_url` varchar(500) DEFAULT NULL COMMENT 'URL de la imagen/video',
                `thumbnail_url` varchar(500) DEFAULT NULL COMMENT 'URL del thumbnail (para videos)',
                `permalink` varchar(500) NOT NULL COMMENT 'URL pública del post en Instagram',
                `caption` text DEFAULT NULL COMMENT 'Descripción del post',
                `timestamp` datetime DEFAULT NULL COMMENT 'Fecha de publicación en Instagram',
                `like_count` int(11) UNSIGNED DEFAULT NULL,
                `comments_count` int(11) UNSIGNED DEFAULT NULL,
                `is_active` tinyint(1) DEFAULT 1,
                `cached_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT 'Última actualización del cache',
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_instagram_id` (`instagram_id`),
                KEY `idx_connection_id` (`connection_id`),
                KEY `idx_is_active` (`is_active`),
                KEY `idx_timestamp` (`timestamp`),
                KEY `idx_media_type` (`media_type`),
                CONSTRAINT `fk_instagram_posts_connection`
                    FOREIGN KEY (`connection_id`)
                    REFERENCES `instagram_connections` (`id`)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Tabla de configuraciones de Instagram por tenant
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `instagram_settings` (
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
        // Tabla de conexiones de Instagram
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS instagram_connections (
                id SERIAL PRIMARY KEY,
                tenant_id INTEGER DEFAULT NULL,
                user_id INTEGER DEFAULT NULL,
                instagram_user_id VARCHAR(255) NOT NULL,
                username VARCHAR(255) NOT NULL,
                profile_picture VARCHAR(500) DEFAULT NULL,
                access_token TEXT NOT NULL,
                refresh_token TEXT DEFAULT NULL,
                token_expires_at TIMESTAMP NOT NULL,
                is_active SMALLINT DEFAULT 1,
                last_synced_at TIMESTAMP DEFAULT NULL,
                last_error TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (instagram_user_id, tenant_id)
            )
        ");

        // Crear índices para instagram_connections
        $pdo->exec("CREATE INDEX IF NOT EXISTS instagram_connections_idx_tenant_id ON instagram_connections(tenant_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS instagram_connections_idx_is_active ON instagram_connections(is_active)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS instagram_connections_idx_token_expires_at ON instagram_connections(token_expires_at)");

        // Agregar comentarios
        $pdo->exec("COMMENT ON COLUMN instagram_connections.tenant_id IS 'NULL = conexión de SuperAdmin'");
        $pdo->exec("COMMENT ON COLUMN instagram_connections.user_id IS 'ID del usuario que conectó'");
        $pdo->exec("COMMENT ON COLUMN instagram_connections.instagram_user_id IS 'ID de usuario de Instagram'");
        $pdo->exec("COMMENT ON COLUMN instagram_connections.username IS 'Username de Instagram'");
        $pdo->exec("COMMENT ON COLUMN instagram_connections.access_token IS 'Token de acceso (encriptado)'");
        $pdo->exec("COMMENT ON COLUMN instagram_connections.refresh_token IS 'Token de refresco'");
        $pdo->exec("COMMENT ON COLUMN instagram_connections.token_expires_at IS 'Fecha de expiración del token'");
        $pdo->exec("COMMENT ON COLUMN instagram_connections.last_synced_at IS 'Última sincronización de posts'");
        $pdo->exec("COMMENT ON COLUMN instagram_connections.last_error IS 'Último error de sincronización'");

        // Tabla de posts de Instagram (cache)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS instagram_posts (
                id SERIAL PRIMARY KEY,
                connection_id INTEGER NOT NULL,
                instagram_id VARCHAR(255) NOT NULL,
                media_type VARCHAR(20) DEFAULT 'IMAGE',
                media_url VARCHAR(500) DEFAULT NULL,
                thumbnail_url VARCHAR(500) DEFAULT NULL,
                permalink VARCHAR(500) NOT NULL,
                caption TEXT DEFAULT NULL,
                timestamp TIMESTAMP DEFAULT NULL,
                like_count INTEGER DEFAULT NULL,
                comments_count INTEGER DEFAULT NULL,
                is_active SMALLINT DEFAULT 1,
                cached_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CHECK (media_type IN ('IMAGE', 'VIDEO', 'CAROUSEL_ALBUM')),
                UNIQUE (instagram_id),
                CONSTRAINT fk_instagram_posts_connection
                    FOREIGN KEY (connection_id)
                    REFERENCES instagram_connections (id)
                    ON DELETE CASCADE
            )
        ");

        // Crear índices para instagram_posts
        $pdo->exec("CREATE INDEX IF NOT EXISTS instagram_posts_idx_connection_id ON instagram_posts(connection_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS instagram_posts_idx_is_active ON instagram_posts(is_active)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS instagram_posts_idx_timestamp ON instagram_posts(timestamp)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS instagram_posts_idx_media_type ON instagram_posts(media_type)");

        // Agregar comentarios para instagram_posts
        $pdo->exec("COMMENT ON COLUMN instagram_posts.instagram_id IS 'ID único del post en Instagram'");
        $pdo->exec("COMMENT ON COLUMN instagram_posts.media_url IS 'URL de la imagen/video'");
        $pdo->exec("COMMENT ON COLUMN instagram_posts.thumbnail_url IS 'URL del thumbnail (para videos)'");
        $pdo->exec("COMMENT ON COLUMN instagram_posts.permalink IS 'URL pública del post en Instagram'");
        $pdo->exec("COMMENT ON COLUMN instagram_posts.caption IS 'Descripción del post'");
        $pdo->exec("COMMENT ON COLUMN instagram_posts.timestamp IS 'Fecha de publicación en Instagram'");
        $pdo->exec("COMMENT ON COLUMN instagram_posts.cached_at IS 'Última actualización del cache'");

        // Tabla de configuraciones de Instagram por tenant
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS instagram_settings (
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

        // Crear índices para instagram_settings
        $pdo->exec("CREATE INDEX IF NOT EXISTS instagram_settings_idx_tenant_id ON instagram_settings(tenant_id)");

        // Agregar comentarios para instagram_settings
        $pdo->exec("COMMENT ON COLUMN instagram_settings.tenant_id IS 'NULL = configuración global'");

        // Insertar configuraciones por defecto
        $this->insertDefaultSettings($pdo, 'pgsql');
    }

    private function insertDefaultSettings($pdo, $driver)
    {
        $defaultSettings = [
            // Instagram API credentials (to be configured by SuperAdmin)
            ['instagram_app_id', '', 'string'],
            ['instagram_app_secret', '', 'string'],
            ['instagram_redirect_uri', '', 'string'],

            // Gallery display settings
            ['default_layout', 'grid', 'string'],
            ['default_columns', '3', 'int'],
            ['default_gap', '10', 'int'],
            ['max_posts_per_gallery', '50', 'int'],

            // Caching settings
            ['cache_duration_hours', '6', 'int'],
            ['auto_refresh_tokens', '1', 'bool'],
            ['token_refresh_threshold_days', '7', 'int'],

            // Display options
            ['show_captions', '1', 'bool'],
            ['caption_max_length', '150', 'int'],
            ['enable_lightbox', '1', 'bool'],
            ['enable_lazy_loading', '1', 'bool'],
            ['show_video_indicator', '1', 'bool'],
            ['show_carousel_indicator', '1', 'bool'],

            // Layout options
            ['hover_effect', 'zoom', 'string'],
            ['border_radius', '8', 'int'],
            ['image_aspect_ratio', '1:1', 'string'],
        ];

        if ($driver === 'mysql') {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO instagram_settings
                (tenant_id, setting_key, setting_value, setting_type)
                VALUES (NULL, ?, ?, ?)
            ");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO instagram_settings
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
            $pdo->exec("DROP TABLE IF EXISTS `instagram_posts`");
            $pdo->exec("DROP TABLE IF EXISTS `instagram_settings`");
            $pdo->exec("DROP TABLE IF EXISTS `instagram_connections`");
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        } else {
            // PostgreSQL: CASCADE elimina dependencias automáticamente
            $pdo->exec("DROP TABLE IF EXISTS instagram_posts CASCADE");
            $pdo->exec("DROP TABLE IF EXISTS instagram_settings CASCADE");
            $pdo->exec("DROP TABLE IF EXISTS instagram_connections CASCADE");
        }

        error_log("InstagramGallery: Tables dropped successfully");
    }
}
