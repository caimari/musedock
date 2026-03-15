<?php
/**
 * Cross-Publisher Superadmin Plugin - Migration Install
 *
 * Crea las tablas necesarias para el sistema centralizado de cross-publishing.
 * Compatible con MySQL/MariaDB y PostgreSQL.
 *
 * Tablas creadas:
 * - domain_groups: Grupos editoriales de dominios
 * - cross_publish_global_settings: Configuración global del plugin
 * - cross_publish_queue: Cola de publicaciones (reutilizada si ya existe)
 * - cross_publish_relations: Relaciones source↔target (reutilizada si ya existe)
 * - cross_publish_logs: Historial de operaciones (reutilizada si ya existe)
 *
 * También añade columna group_id a la tabla tenants.
 */

use Screenart\Musedock\Database;

class CrossPublisherAdminInstall
{
    public function up(): array
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $results = [];

        try {
            // 1. Tabla domain_groups
            if (!$this->tableExists($pdo, 'domain_groups')) {
                if ($driver === 'mysql') {
                    $pdo->exec("
                        CREATE TABLE `domain_groups` (
                            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                            `name` VARCHAR(255) NOT NULL,
                            `description` TEXT DEFAULT NULL,
                            `default_language` VARCHAR(10) DEFAULT 'es',
                            `auto_sync_enabled` TINYINT(1) DEFAULT 0,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            PRIMARY KEY (`id`),
                            KEY `idx_name` (`name`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                } else {
                    $pdo->exec("
                        CREATE TABLE domain_groups (
                            id SERIAL PRIMARY KEY,
                            name VARCHAR(255) NOT NULL,
                            description TEXT,
                            default_language VARCHAR(10) DEFAULT 'es',
                            auto_sync_enabled BOOLEAN DEFAULT FALSE,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    $pdo->exec("CREATE INDEX domain_groups_idx_name ON domain_groups(name)");
                }
                $results[] = "Table domain_groups created";
            } else {
                $results[] = "Table domain_groups already exists";
            }

            // 2. Columna group_id en tenants
            if (!$this->columnExists($pdo, 'tenants', 'group_id')) {
                if ($driver === 'mysql') {
                    $pdo->exec("ALTER TABLE `tenants` ADD COLUMN `group_id` INT UNSIGNED DEFAULT NULL");
                    $pdo->exec("CREATE INDEX `idx_tenants_group_id` ON `tenants`(`group_id`)");
                } else {
                    $pdo->exec("ALTER TABLE tenants ADD COLUMN group_id INTEGER DEFAULT NULL");
                    $pdo->exec("CREATE INDEX idx_tenants_group_id ON tenants(group_id)");
                }
                $results[] = "Column group_id added to tenants";
            } else {
                $results[] = "Column tenants.group_id already exists";
            }

            // 3. Tabla cross_publish_global_settings
            if (!$this->tableExists($pdo, 'cross_publish_global_settings')) {
                if ($driver === 'mysql') {
                    $pdo->exec("
                        CREATE TABLE `cross_publish_global_settings` (
                            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                            `ai_provider_id` INT DEFAULT NULL,
                            `auto_translate` TINYINT(1) DEFAULT 0,
                            `default_target_status` VARCHAR(20) DEFAULT 'draft',
                            `include_featured_image` TINYINT(1) DEFAULT 1,
                            `include_categories` TINYINT(1) DEFAULT 1,
                            `include_tags` TINYINT(1) DEFAULT 1,
                            `add_canonical_link` TINYINT(1) DEFAULT 1,
                            `add_source_credit` TINYINT(1) DEFAULT 1,
                            `source_credit_template` TEXT DEFAULT NULL,
                            `sync_cron_interval` INT DEFAULT 15,
                            `sync_enabled` TINYINT(1) DEFAULT 1,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            PRIMARY KEY (`id`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                } else {
                    $pdo->exec("
                        CREATE TABLE cross_publish_global_settings (
                            id SERIAL PRIMARY KEY,
                            ai_provider_id INTEGER,
                            auto_translate BOOLEAN DEFAULT FALSE,
                            default_target_status VARCHAR(20) DEFAULT 'draft',
                            include_featured_image BOOLEAN DEFAULT TRUE,
                            include_categories BOOLEAN DEFAULT TRUE,
                            include_tags BOOLEAN DEFAULT TRUE,
                            add_canonical_link BOOLEAN DEFAULT TRUE,
                            add_source_credit BOOLEAN DEFAULT TRUE,
                            source_credit_template TEXT,
                            sync_cron_interval INTEGER DEFAULT 15,
                            sync_enabled BOOLEAN DEFAULT TRUE,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                }
                // Insertar fila default
                $pdo->exec("INSERT INTO cross_publish_global_settings (source_credit_template) VALUES ('Publicado originalmente en <a href=\"{source_url}\">{source_name}</a>')");
                $results[] = "Table cross_publish_global_settings created with defaults";
            } else {
                $results[] = "Table cross_publish_global_settings already exists";
            }

            // 4. Tabla cross_publish_queue (reusar si existe del plugin tenant)
            if (!$this->tableExists($pdo, 'cross_publish_queue')) {
                if ($driver === 'mysql') {
                    $pdo->exec("
                        CREATE TABLE `cross_publish_queue` (
                            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                            `source_tenant_id` INT NOT NULL,
                            `source_post_id` INT NOT NULL,
                            `target_tenant_id` INT NOT NULL,
                            `status` VARCHAR(20) DEFAULT 'pending',
                            `translate` TINYINT(1) DEFAULT 0,
                            `adapt` TINYINT(1) DEFAULT 0,
                            `source_language` VARCHAR(10) DEFAULT NULL,
                            `target_language` VARCHAR(10) DEFAULT NULL,
                            `custom_prompt` TEXT DEFAULT NULL,
                            `ai_provider_id` INT DEFAULT NULL,
                            `target_status` VARCHAR(20) DEFAULT 'draft',
                            `result_post_id` INT DEFAULT NULL,
                            `tokens_used` INT DEFAULT 0,
                            `error_message` TEXT DEFAULT NULL,
                            `attempts` INT DEFAULT 0,
                            `created_by` INT DEFAULT NULL,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            `processed_at` TIMESTAMP NULL DEFAULT NULL,
                            PRIMARY KEY (`id`),
                            KEY `idx_status` (`status`),
                            KEY `idx_source` (`source_tenant_id`, `source_post_id`),
                            KEY `idx_target` (`target_tenant_id`),
                            KEY `idx_created_at` (`created_at`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                } else {
                    $pdo->exec("
                        CREATE TABLE cross_publish_queue (
                            id SERIAL PRIMARY KEY,
                            source_tenant_id INTEGER NOT NULL,
                            source_post_id INTEGER NOT NULL,
                            target_tenant_id INTEGER NOT NULL,
                            status VARCHAR(20) DEFAULT 'pending',
                            translate BOOLEAN DEFAULT FALSE,
                            adapt BOOLEAN DEFAULT FALSE,
                            source_language VARCHAR(10),
                            target_language VARCHAR(10),
                            custom_prompt TEXT,
                            ai_provider_id INTEGER,
                            target_status VARCHAR(20) DEFAULT 'draft',
                            result_post_id INTEGER,
                            tokens_used INTEGER DEFAULT 0,
                            error_message TEXT,
                            attempts INTEGER DEFAULT 0,
                            created_by INTEGER,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            processed_at TIMESTAMP
                        )
                    ");
                    $pdo->exec("CREATE INDEX cross_publish_queue_idx_status ON cross_publish_queue(status)");
                    $pdo->exec("CREATE INDEX cross_publish_queue_idx_source ON cross_publish_queue(source_tenant_id, source_post_id)");
                    $pdo->exec("CREATE INDEX cross_publish_queue_idx_target ON cross_publish_queue(target_tenant_id)");
                    $pdo->exec("CREATE INDEX cross_publish_queue_idx_created_at ON cross_publish_queue(created_at)");
                }
                $results[] = "Table cross_publish_queue created";
            } else {
                $results[] = "Table cross_publish_queue already exists (reusing)";
            }

            // 5. Tabla cross_publish_relations
            if (!$this->tableExists($pdo, 'cross_publish_relations')) {
                if ($driver === 'mysql') {
                    $pdo->exec("
                        CREATE TABLE `cross_publish_relations` (
                            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                            `source_post_id` INT NOT NULL,
                            `source_tenant_id` INT NOT NULL,
                            `target_post_id` INT NOT NULL,
                            `target_tenant_id` INT NOT NULL,
                            `sync_enabled` TINYINT(1) DEFAULT 1,
                            `last_synced_at` TIMESTAMP NULL DEFAULT NULL,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            PRIMARY KEY (`id`),
                            UNIQUE KEY `unique_relation` (`source_post_id`, `source_tenant_id`, `target_tenant_id`),
                            KEY `idx_source` (`source_tenant_id`, `source_post_id`),
                            KEY `idx_target` (`target_tenant_id`, `target_post_id`),
                            KEY `idx_sync` (`sync_enabled`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                } else {
                    $pdo->exec("
                        CREATE TABLE cross_publish_relations (
                            id SERIAL PRIMARY KEY,
                            source_post_id INTEGER NOT NULL,
                            source_tenant_id INTEGER NOT NULL,
                            target_post_id INTEGER NOT NULL,
                            target_tenant_id INTEGER NOT NULL,
                            sync_enabled BOOLEAN DEFAULT TRUE,
                            last_synced_at TIMESTAMP,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            UNIQUE(source_post_id, source_tenant_id, target_tenant_id)
                        )
                    ");
                    $pdo->exec("CREATE INDEX cross_publish_relations_idx_source ON cross_publish_relations(source_tenant_id, source_post_id)");
                    $pdo->exec("CREATE INDEX cross_publish_relations_idx_target ON cross_publish_relations(target_tenant_id, target_post_id)");
                    $pdo->exec("CREATE INDEX cross_publish_relations_idx_sync ON cross_publish_relations(sync_enabled)");
                }
                $results[] = "Table cross_publish_relations created";
            } else {
                $results[] = "Table cross_publish_relations already exists (reusing)";
            }

            // 6. Tabla cross_publish_logs
            if (!$this->tableExists($pdo, 'cross_publish_logs')) {
                if ($driver === 'mysql') {
                    $pdo->exec("
                        CREATE TABLE `cross_publish_logs` (
                            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                            `queue_id` INT DEFAULT NULL,
                            `source_tenant_id` INT NOT NULL,
                            `source_post_id` INT DEFAULT NULL,
                            `target_tenant_id` INT DEFAULT NULL,
                            `target_post_id` INT DEFAULT NULL,
                            `action` VARCHAR(50) NOT NULL,
                            `status` VARCHAR(20) NOT NULL,
                            `tokens_used` INT DEFAULT 0,
                            `ai_cost` DECIMAL(10,6) DEFAULT 0,
                            `error_message` TEXT DEFAULT NULL,
                            `metadata` JSON DEFAULT NULL,
                            `created_by` INT DEFAULT NULL,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            PRIMARY KEY (`id`),
                            KEY `idx_source` (`source_tenant_id`),
                            KEY `idx_action` (`action`),
                            KEY `idx_created_at` (`created_at`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                } else {
                    $pdo->exec("
                        CREATE TABLE cross_publish_logs (
                            id SERIAL PRIMARY KEY,
                            queue_id INTEGER,
                            source_tenant_id INTEGER NOT NULL,
                            source_post_id INTEGER,
                            target_tenant_id INTEGER,
                            target_post_id INTEGER,
                            action VARCHAR(50) NOT NULL,
                            status VARCHAR(20) NOT NULL,
                            tokens_used INTEGER DEFAULT 0,
                            ai_cost DECIMAL(10,6) DEFAULT 0,
                            error_message TEXT,
                            metadata JSONB,
                            created_by INTEGER,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    $pdo->exec("CREATE INDEX cross_publish_logs_idx_source ON cross_publish_logs(source_tenant_id)");
                    $pdo->exec("CREATE INDEX cross_publish_logs_idx_action ON cross_publish_logs(action)");
                    $pdo->exec("CREATE INDEX cross_publish_logs_idx_created_at ON cross_publish_logs(created_at)");
                }
                $results[] = "Table cross_publish_logs created";
            } else {
                $results[] = "Table cross_publish_logs already exists (reusing)";
            }

            return ['success' => true, 'results' => $results];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'results' => $results];
        }
    }

    public function down(): array
    {
        $pdo = Database::connect();
        $results = [];

        // Solo eliminar tablas propias del plugin centralizado
        $tables = ['cross_publish_global_settings', 'domain_groups'];

        foreach ($tables as $table) {
            try {
                $pdo->exec("DROP TABLE IF EXISTS {$table}");
                $results[] = "Table {$table} dropped";
            } catch (\Exception $e) {
                $results[] = "Error dropping {$table}: " . $e->getMessage();
            }
        }

        // Quitar columna group_id de tenants
        try {
            $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            if ($driver === 'mysql') {
                $pdo->exec("ALTER TABLE `tenants` DROP COLUMN `group_id`");
            } else {
                $pdo->exec("ALTER TABLE tenants DROP COLUMN IF EXISTS group_id");
            }
            $results[] = "Column group_id removed from tenants";
        } catch (\Exception $e) {
            $results[] = "Error removing group_id: " . $e->getMessage();
        }

        return ['success' => true, 'results' => $results];
    }

    private function tableExists(\PDO $pdo, string $table): bool
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            return $stmt->rowCount() > 0;
        } else {
            $stmt = $pdo->prepare("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ?)");
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        }
    }

    private function columnExists(\PDO $pdo, string $table, string $column): bool
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
            $stmt->execute([$column]);
            return $stmt->rowCount() > 0;
        } else {
            $stmt = $pdo->prepare("SELECT EXISTS (SELECT FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? AND column_name = ?)");
            $stmt->execute([$table, $column]);
            return (bool) $stmt->fetchColumn();
        }
    }
}
