<?php
/**
 * News Aggregator Plugin - Migration Install
 *
 * Crea todas las tablas necesarias para el plugin.
 * Compatible con MySQL/MariaDB y PostgreSQL.
 *
 * Tablas creadas:
 * - news_aggregator_sources: Fuentes de noticias configuradas
 * - news_aggregator_settings: Configuración por tenant
 * - news_aggregator_items: Noticias capturadas
 * - news_aggregator_logs: Historial de operaciones
 */

use Screenart\Musedock\Database;

class NewsAggregatorInstall
{
    /**
     * Ejecutar migración
     */
    public function up(): array
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $results = [];

        try {
            // 1. Tabla news_aggregator_settings
            if (!$this->tableExists($pdo, 'news_aggregator_settings')) {
                if ($driver === 'mysql') {
                    $pdo->exec("
                        CREATE TABLE `news_aggregator_settings` (
                            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                            `tenant_id` INT NOT NULL,
                            `ai_provider_id` INT DEFAULT NULL,
                            `output_language` VARCHAR(10) DEFAULT 'es',
                            `rewrite_prompt` TEXT DEFAULT NULL COMMENT 'Prompt para reescribir noticias',
                            `default_category_id` INT DEFAULT NULL,
                            `auto_rewrite` TINYINT(1) DEFAULT 1 COMMENT 'Reescribir automáticamente al capturar',
                            `duplicate_check_days` INT DEFAULT 7 COMMENT 'Días hacia atrás para verificar duplicados',
                            `enabled` TINYINT(1) DEFAULT 1,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            PRIMARY KEY (`id`),
                            UNIQUE KEY `unique_tenant` (`tenant_id`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                } else {
                    $pdo->exec("
                        CREATE TABLE news_aggregator_settings (
                            id SERIAL PRIMARY KEY,
                            tenant_id INTEGER NOT NULL UNIQUE,
                            ai_provider_id INTEGER,
                            output_language VARCHAR(10) DEFAULT 'es',
                            rewrite_prompt TEXT,
                            default_category_id INTEGER,
                            auto_rewrite BOOLEAN DEFAULT TRUE,
                            duplicate_check_days INTEGER DEFAULT 7,
                            enabled BOOLEAN DEFAULT TRUE,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                }
                $results[] = "Table news_aggregator_settings created";
            } else {
                $results[] = "Table news_aggregator_settings already exists";
            }

            // 2. Tabla news_aggregator_sources
            if (!$this->tableExists($pdo, 'news_aggregator_sources')) {
                if ($driver === 'mysql') {
                    $pdo->exec("
                        CREATE TABLE `news_aggregator_sources` (
                            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                            `tenant_id` INT NOT NULL,
                            `name` VARCHAR(255) NOT NULL COMMENT 'Nombre descriptivo de la fuente',
                            `source_type` VARCHAR(50) NOT NULL DEFAULT 'rss' COMMENT 'rss, newsapi, gnews, mediastack',
                            `url` VARCHAR(500) DEFAULT NULL COMMENT 'URL del feed RSS o endpoint',
                            `api_key` VARCHAR(255) DEFAULT NULL COMMENT 'API key si es necesaria',
                            `keywords` TEXT DEFAULT NULL COMMENT 'Palabras clave separadas por coma',
                            `categories` TEXT DEFAULT NULL COMMENT 'Categorías a monitorear',
                            `language` VARCHAR(10) DEFAULT NULL COMMENT 'Idioma de la fuente',
                            `fetch_interval` INT DEFAULT 3600 COMMENT 'Intervalo en segundos',
                            `max_articles` INT DEFAULT 10 COMMENT 'Máximo de artículos por fetch',
                            `enabled` TINYINT(1) DEFAULT 1,
                            `last_fetch_at` TIMESTAMP NULL DEFAULT NULL,
                            `last_fetch_count` INT DEFAULT 0,
                            `fetch_error` TEXT DEFAULT NULL,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            PRIMARY KEY (`id`),
                            KEY `idx_tenant_enabled` (`tenant_id`, `enabled`),
                            KEY `idx_last_fetch` (`last_fetch_at`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                } else {
                    $pdo->exec("
                        CREATE TABLE news_aggregator_sources (
                            id SERIAL PRIMARY KEY,
                            tenant_id INTEGER NOT NULL,
                            name VARCHAR(255) NOT NULL,
                            source_type VARCHAR(50) NOT NULL DEFAULT 'rss',
                            url VARCHAR(500),
                            api_key VARCHAR(255),
                            keywords TEXT,
                            categories TEXT,
                            language VARCHAR(10),
                            fetch_interval INTEGER DEFAULT 3600,
                            max_articles INTEGER DEFAULT 10,
                            enabled BOOLEAN DEFAULT TRUE,
                            last_fetch_at TIMESTAMP,
                            last_fetch_count INTEGER DEFAULT 0,
                            fetch_error TEXT,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    $pdo->exec("CREATE INDEX news_aggregator_sources_idx_tenant_enabled ON news_aggregator_sources(tenant_id, enabled)");
                    $pdo->exec("CREATE INDEX news_aggregator_sources_idx_last_fetch ON news_aggregator_sources(last_fetch_at)");
                }
                $results[] = "Table news_aggregator_sources created";
            } else {
                $results[] = "Table news_aggregator_sources already exists";
            }

            // 3. Tabla news_aggregator_items
            if (!$this->tableExists($pdo, 'news_aggregator_items')) {
                if ($driver === 'mysql') {
                    $pdo->exec("
                        CREATE TABLE `news_aggregator_items` (
                            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                            `tenant_id` INT NOT NULL,
                            `source_id` INT UNSIGNED NOT NULL,
                            `original_title` VARCHAR(500) NOT NULL,
                            `original_content` TEXT DEFAULT NULL,
                            `original_url` VARCHAR(500) NOT NULL,
                            `original_published_at` TIMESTAMP NULL DEFAULT NULL,
                            `original_author` VARCHAR(255) DEFAULT NULL,
                            `original_image_url` VARCHAR(500) DEFAULT NULL COMMENT 'Referencia, NO se descarga',
                            `rewritten_title` VARCHAR(500) DEFAULT NULL,
                            `rewritten_content` TEXT DEFAULT NULL,
                            `rewritten_excerpt` TEXT DEFAULT NULL,
                            `status` VARCHAR(20) DEFAULT 'pending' COMMENT 'pending, processing, ready, approved, rejected, published',
                            `tokens_used` INT DEFAULT 0,
                            `content_hash` VARCHAR(64) DEFAULT NULL COMMENT 'Hash para detectar duplicados',
                            `created_post_id` INT DEFAULT NULL COMMENT 'ID del blog_post si se convirtió',
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            `processed_at` TIMESTAMP NULL DEFAULT NULL,
                            `reviewed_by` INT DEFAULT NULL,
                            `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
                            PRIMARY KEY (`id`),
                            UNIQUE KEY `unique_hash` (`tenant_id`, `content_hash`),
                            KEY `idx_tenant_status` (`tenant_id`, `status`),
                            KEY `idx_source` (`source_id`),
                            KEY `idx_created_at` (`created_at`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                } else {
                    $pdo->exec("
                        CREATE TABLE news_aggregator_items (
                            id SERIAL PRIMARY KEY,
                            tenant_id INTEGER NOT NULL,
                            source_id INTEGER NOT NULL,
                            original_title VARCHAR(500) NOT NULL,
                            original_content TEXT,
                            original_url VARCHAR(500) NOT NULL,
                            original_published_at TIMESTAMP,
                            original_author VARCHAR(255),
                            original_image_url VARCHAR(500),
                            rewritten_title VARCHAR(500),
                            rewritten_content TEXT,
                            rewritten_excerpt TEXT,
                            status VARCHAR(20) DEFAULT 'pending',
                            tokens_used INTEGER DEFAULT 0,
                            content_hash VARCHAR(64),
                            created_post_id INTEGER,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            processed_at TIMESTAMP,
                            reviewed_by INTEGER,
                            reviewed_at TIMESTAMP,
                            UNIQUE(tenant_id, content_hash)
                        )
                    ");
                    $pdo->exec("CREATE INDEX news_aggregator_items_idx_tenant_status ON news_aggregator_items(tenant_id, status)");
                    $pdo->exec("CREATE INDEX news_aggregator_items_idx_source ON news_aggregator_items(source_id)");
                    $pdo->exec("CREATE INDEX news_aggregator_items_idx_created_at ON news_aggregator_items(created_at)");
                }
                $results[] = "Table news_aggregator_items created";
            } else {
                $results[] = "Table news_aggregator_items already exists";
            }

            // 4. Tabla news_aggregator_logs
            if (!$this->tableExists($pdo, 'news_aggregator_logs')) {
                if ($driver === 'mysql') {
                    $pdo->exec("
                        CREATE TABLE `news_aggregator_logs` (
                            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                            `tenant_id` INT NOT NULL,
                            `source_id` INT UNSIGNED DEFAULT NULL,
                            `item_id` INT UNSIGNED DEFAULT NULL,
                            `action` VARCHAR(50) NOT NULL COMMENT 'fetch, rewrite, approve, reject, publish',
                            `status` VARCHAR(20) NOT NULL COMMENT 'success, failed',
                            `items_count` INT DEFAULT 0,
                            `tokens_used` INT DEFAULT 0,
                            `error_message` TEXT DEFAULT NULL,
                            `metadata` JSON DEFAULT NULL,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            PRIMARY KEY (`id`),
                            KEY `idx_tenant` (`tenant_id`),
                            KEY `idx_action` (`action`),
                            KEY `idx_created_at` (`created_at`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                } else {
                    $pdo->exec("
                        CREATE TABLE news_aggregator_logs (
                            id SERIAL PRIMARY KEY,
                            tenant_id INTEGER NOT NULL,
                            source_id INTEGER,
                            item_id INTEGER,
                            action VARCHAR(50) NOT NULL,
                            status VARCHAR(20) NOT NULL,
                            items_count INTEGER DEFAULT 0,
                            tokens_used INTEGER DEFAULT 0,
                            error_message TEXT,
                            metadata JSONB,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    $pdo->exec("CREATE INDEX news_aggregator_logs_idx_tenant ON news_aggregator_logs(tenant_id)");
                    $pdo->exec("CREATE INDEX news_aggregator_logs_idx_action ON news_aggregator_logs(action)");
                    $pdo->exec("CREATE INDEX news_aggregator_logs_idx_created_at ON news_aggregator_logs(created_at)");
                }
                $results[] = "Table news_aggregator_logs created";
            } else {
                $results[] = "Table news_aggregator_logs already exists";
            }

            // 5. Tabla news_aggregator_clusters
            if (!$this->tableExists($pdo, 'news_aggregator_clusters')) {
                if ($driver === 'mysql') {
                    $pdo->exec("
                        CREATE TABLE `news_aggregator_clusters` (
                            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                            `tenant_id` INT NOT NULL,
                            `title` TEXT NOT NULL,
                            `title_normalized` TEXT NOT NULL,
                            `source_count` INT DEFAULT 1,
                            `status` VARCHAR(20) DEFAULT 'pending',
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            PRIMARY KEY (`id`),
                            KEY `idx_tenant` (`tenant_id`),
                            KEY `idx_tenant_status` (`tenant_id`, `status`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                } else {
                    $pdo->exec("
                        CREATE TABLE news_aggregator_clusters (
                            id SERIAL PRIMARY KEY,
                            tenant_id INTEGER NOT NULL,
                            title TEXT NOT NULL,
                            title_normalized TEXT NOT NULL,
                            source_count INTEGER DEFAULT 1,
                            status VARCHAR(20) DEFAULT 'pending',
                            created_at TIMESTAMP DEFAULT NOW(),
                            updated_at TIMESTAMP DEFAULT NOW()
                        )
                    ");
                    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_clusters_tenant ON news_aggregator_clusters(tenant_id)");
                    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_clusters_status ON news_aggregator_clusters(tenant_id, status)");
                }
                $results[] = "Table news_aggregator_clusters created";
            } else {
                $results[] = "Table news_aggregator_clusters already exists";
            }

            // 6. Tabla news_aggregator_source_feeds
            if (!$this->tableExists($pdo, 'news_aggregator_source_feeds')) {
                if ($driver === 'mysql') {
                    $pdo->exec("
                        CREATE TABLE `news_aggregator_source_feeds` (
                            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                            `source_id` INT UNSIGNED NOT NULL,
                            `name` VARCHAR(255) NOT NULL,
                            `url` VARCHAR(500) NOT NULL,
                            `enabled` TINYINT(1) DEFAULT 1,
                            `last_fetch_at` TIMESTAMP NULL DEFAULT NULL,
                            `last_fetch_count` INT DEFAULT 0,
                            `fetch_error` TEXT DEFAULT NULL,
                            `sort_order` INT DEFAULT 0,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            PRIMARY KEY (`id`),
                            KEY `idx_source` (`source_id`),
                            KEY `idx_source_enabled` (`source_id`, `enabled`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                } else {
                    $pdo->exec("
                        CREATE TABLE news_aggregator_source_feeds (
                            id SERIAL PRIMARY KEY,
                            source_id INTEGER NOT NULL,
                            name VARCHAR(255) NOT NULL,
                            url VARCHAR(500) NOT NULL,
                            enabled BOOLEAN DEFAULT TRUE,
                            last_fetch_at TIMESTAMP,
                            last_fetch_count INTEGER DEFAULT 0,
                            fetch_error TEXT,
                            sort_order INTEGER DEFAULT 0,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_source_feeds_source ON news_aggregator_source_feeds(source_id)");
                    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_source_feeds_enabled ON news_aggregator_source_feeds(source_id, enabled)");
                }
                $results[] = "Table news_aggregator_source_feeds created";
            } else {
                $results[] = "Table news_aggregator_source_feeds already exists";
            }

            // 7. Columnas adicionales (safe for existing installs)
            $this->addColumnIfNotExists($pdo, $driver, 'news_aggregator_items', 'source_tags', 'TEXT DEFAULT NULL');
            $this->addColumnIfNotExists($pdo, $driver, 'news_aggregator_items', 'media_keywords', 'TEXT DEFAULT NULL');
            $this->addColumnIfNotExists($pdo, $driver, 'news_aggregator_items', 'cluster_id', 'INTEGER DEFAULT NULL');
            $this->addColumnIfNotExists($pdo, $driver, 'news_aggregator_items', 'feed_id', 'INTEGER DEFAULT NULL');
            $this->addColumnIfNotExists($pdo, $driver, 'news_aggregator_items', 'ai_categories', 'TEXT DEFAULT NULL');
            $this->addColumnIfNotExists($pdo, $driver, 'news_aggregator_items', 'ai_tags', 'TEXT DEFAULT NULL');
            $this->addColumnIfNotExists($pdo, $driver, 'news_aggregator_sources', 'attribution_mode', "VARCHAR(20) DEFAULT 'rewrite'");
            $this->addColumnIfNotExists($pdo, $driver, 'news_aggregator_sources', 'exclude_rewrite', 'BOOLEAN DEFAULT FALSE');
            $this->addColumnIfNotExists($pdo, $driver, 'news_aggregator_sources', 'processing_type', "VARCHAR(20) DEFAULT 'direct'");
            $this->addColumnIfNotExists($pdo, $driver, 'news_aggregator_sources', 'min_sources_for_publish', 'INTEGER DEFAULT 2');
            $this->addColumnIfNotExists($pdo, $driver, 'news_aggregator_clusters', 'source_id', 'INTEGER DEFAULT NULL');
            $this->addColumnIfNotExists($pdo, $driver, 'news_aggregator_settings', 'mode', "VARCHAR(20) DEFAULT 'simple'");
            $this->addColumnIfNotExists($pdo, $driver, 'news_aggregator_settings', 'auto_approve', 'BOOLEAN DEFAULT FALSE');
            $this->addColumnIfNotExists($pdo, $driver, 'news_aggregator_settings', 'auto_publish', 'BOOLEAN DEFAULT FALSE');
            $this->addColumnIfNotExists($pdo, $driver, 'news_aggregator_settings', 'publish_status', "VARCHAR(20) DEFAULT 'draft'");

            // Indexes
            try {
                if ($driver === 'pgsql') {
                    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_items_cluster ON news_aggregator_items(cluster_id)");
                    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_items_feed ON news_aggregator_items(feed_id)");
                    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_clusters_source ON news_aggregator_clusters(source_id)");
                } else {
                    $stmt = $pdo->prepare("SHOW INDEX FROM news_aggregator_items WHERE Key_name = 'idx_items_cluster'");
                    $stmt->execute();
                    if ($stmt->rowCount() === 0) {
                        $pdo->exec("ALTER TABLE news_aggregator_items ADD INDEX idx_items_cluster (cluster_id)");
                    }
                    $stmt = $pdo->prepare("SHOW INDEX FROM news_aggregator_items WHERE Key_name = 'idx_items_feed'");
                    $stmt->execute();
                    if ($stmt->rowCount() === 0) {
                        $pdo->exec("ALTER TABLE news_aggregator_items ADD INDEX idx_items_feed (feed_id)");
                    }
                    $stmt = $pdo->prepare("SHOW INDEX FROM news_aggregator_clusters WHERE Key_name = 'idx_clusters_source'");
                    $stmt->execute();
                    if ($stmt->rowCount() === 0) {
                        $pdo->exec("ALTER TABLE news_aggregator_clusters ADD INDEX idx_clusters_source (source_id)");
                    }
                }
            } catch (\Exception $e) {
                // Ignore index creation errors
            }

            $results[] = "Additional columns and indexes verified";

            return ['success' => true, 'results' => $results];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'results' => $results];
        }
    }

    /**
     * Revertir migración
     */
    public function down(): array
    {
        $pdo = Database::connect();
        $results = [];

        $tables = [
            'news_aggregator_logs',
            'news_aggregator_source_feeds',
            'news_aggregator_clusters',
            'news_aggregator_items',
            'news_aggregator_sources',
            'news_aggregator_settings'
        ];

        foreach ($tables as $table) {
            try {
                $pdo->exec("DROP TABLE IF EXISTS {$table}");
                $results[] = "Table {$table} dropped";
            } catch (\Exception $e) {
                $results[] = "Error dropping {$table}: " . $e->getMessage();
            }
        }

        return ['success' => true, 'results' => $results];
    }

    /**
     * Añadir columna si no existe (seguro para producción)
     */
    private function addColumnIfNotExists(\PDO $pdo, string $driver, string $table, string $column, string $definition): void
    {
        try {
            if ($driver === 'pgsql') {
                $pdo->exec("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS {$column} {$definition}");
            } else {
                // MySQL: check if column exists
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM information_schema.columns
                    WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
                ");
                $stmt->execute([$table, $column]);
                if ($stmt->fetchColumn() == 0) {
                    $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
                }
            }
        } catch (\Exception $e) {
            // Silently ignore — column may already exist
        }
    }

    /**
     * Verificar si una tabla existe
     */
    private function tableExists(\PDO $pdo, string $table): bool
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            return $stmt->rowCount() > 0;
        } else {
            $stmt = $pdo->prepare("
                SELECT EXISTS (
                    SELECT FROM information_schema.tables
                    WHERE table_schema = 'public'
                    AND table_name = ?
                )
            ");
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        }
    }
}

// Ejecutar si se llama directamente
if (php_sapi_name() === 'cli' || (isset($argv) && basename($argv[0]) === basename(__FILE__))) {
    require_once __DIR__ . '/../../../../../../core/bootstrap.php';

    $migration = new NewsAggregatorInstall();

    $action = $argv[1] ?? 'up';

    if ($action === 'down') {
        $result = $migration->down();
    } else {
        $result = $migration->up();
    }

    echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
}
