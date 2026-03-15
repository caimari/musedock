<?php
/**
 * Cross-Publisher Plugin - Migration Install
 *
 * Crea todas las tablas necesarias para el plugin.
 * Compatible con MySQL/MariaDB y PostgreSQL.
 *
 * Tablas creadas:
 * - cross_publish_network: Red de tenants que pueden compartir contenido
 * - cross_publish_settings: Configuración por tenant
 * - cross_publish_queue: Cola de publicaciones pendientes
 * - cross_publish_relations: Relaciones entre posts (original <-> derivados)
 * - cross_publish_logs: Historial de operaciones
 */

use Screenart\Musedock\Database;

class CrossPublisherInstall
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
            // 1. Tabla cross_publish_network
            if (!$this->tableExists($pdo, 'cross_publish_network')) {
                if ($driver === 'mysql') {
                    $pdo->exec("
                        CREATE TABLE `cross_publish_network` (
                            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                            `tenant_id` INT NOT NULL,
                            `network_key` VARCHAR(64) NOT NULL COMMENT 'Clave compartida de la red editorial',
                            `display_name` VARCHAR(255) DEFAULT NULL COMMENT 'Nombre para mostrar en selectores',
                            `language` VARCHAR(10) DEFAULT 'es' COMMENT 'Idioma principal del tenant',
                            `adaptation_prompt` TEXT DEFAULT NULL COMMENT 'Prompt para adaptar contenido entrante',
                            `default_status` VARCHAR(20) DEFAULT 'draft',
                            `ai_provider_id` INT DEFAULT NULL,
                            `enabled` TINYINT(1) DEFAULT 1,
                            `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            PRIMARY KEY (`id`),
                            UNIQUE KEY `unique_tenant` (`tenant_id`),
                            KEY `idx_network_key` (`network_key`),
                            KEY `idx_enabled` (`enabled`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                } else {
                    $pdo->exec("
                        CREATE TABLE cross_publish_network (
                            id SERIAL PRIMARY KEY,
                            tenant_id INTEGER NOT NULL UNIQUE,
                            network_key VARCHAR(64) NOT NULL,
                            display_name VARCHAR(255),
                            language VARCHAR(10) DEFAULT 'es',
                            adaptation_prompt TEXT,
                            default_status VARCHAR(20) DEFAULT 'draft',
                            ai_provider_id INTEGER,
                            enabled BOOLEAN DEFAULT TRUE,
                            joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                    $pdo->exec("CREATE INDEX cross_publish_network_idx_network_key ON cross_publish_network(network_key)");
                    $pdo->exec("CREATE INDEX cross_publish_network_idx_enabled ON cross_publish_network(enabled)");
                }
                $results[] = "Table cross_publish_network created";
            } else {
                $results[] = "Table cross_publish_network already exists";
            }

            // 2. Tabla cross_publish_settings
            if (!$this->tableExists($pdo, 'cross_publish_settings')) {
                if ($driver === 'mysql') {
                    $pdo->exec("
                        CREATE TABLE `cross_publish_settings` (
                            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                            `tenant_id` INT NOT NULL,
                            `default_ai_provider_id` INT DEFAULT NULL,
                            `default_target_status` VARCHAR(20) DEFAULT 'draft',
                            `auto_translate` TINYINT(1) DEFAULT 1,
                            `auto_adapt` TINYINT(1) DEFAULT 0,
                            `include_categories` TINYINT(1) DEFAULT 0,
                            `include_tags` TINYINT(1) DEFAULT 0,
                            `include_featured_image` TINYINT(1) DEFAULT 1,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            PRIMARY KEY (`id`),
                            UNIQUE KEY `unique_tenant` (`tenant_id`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                } else {
                    $pdo->exec("
                        CREATE TABLE cross_publish_settings (
                            id SERIAL PRIMARY KEY,
                            tenant_id INTEGER NOT NULL UNIQUE,
                            default_ai_provider_id INTEGER,
                            default_target_status VARCHAR(20) DEFAULT 'draft',
                            auto_translate BOOLEAN DEFAULT TRUE,
                            auto_adapt BOOLEAN DEFAULT FALSE,
                            include_categories BOOLEAN DEFAULT FALSE,
                            include_tags BOOLEAN DEFAULT FALSE,
                            include_featured_image BOOLEAN DEFAULT TRUE,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");
                }
                $results[] = "Table cross_publish_settings created";
            } else {
                $results[] = "Table cross_publish_settings already exists";
            }

            // 3. Tabla cross_publish_queue
            if (!$this->tableExists($pdo, 'cross_publish_queue')) {
                if ($driver === 'mysql') {
                    $pdo->exec("
                        CREATE TABLE `cross_publish_queue` (
                            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                            `source_tenant_id` INT NOT NULL,
                            `source_post_id` INT NOT NULL,
                            `target_tenant_id` INT NOT NULL,
                            `status` VARCHAR(20) DEFAULT 'pending' COMMENT 'pending, processing, completed, failed, cancelled',
                            `translate` TINYINT(1) DEFAULT 0,
                            `adapt` TINYINT(1) DEFAULT 0,
                            `source_language` VARCHAR(10) DEFAULT NULL,
                            `target_language` VARCHAR(10) DEFAULT NULL,
                            `custom_prompt` TEXT DEFAULT NULL,
                            `ai_provider_id` INT DEFAULT NULL,
                            `target_status` VARCHAR(20) DEFAULT 'draft',
                            `result_post_id` INT DEFAULT NULL COMMENT 'ID del post creado en destino',
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
                $results[] = "Table cross_publish_queue already exists";
            }

            // 4. Tabla cross_publish_relations
            if (!$this->tableExists($pdo, 'cross_publish_relations')) {
                if ($driver === 'mysql') {
                    $pdo->exec("
                        CREATE TABLE `cross_publish_relations` (
                            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                            `source_post_id` INT NOT NULL,
                            `source_tenant_id` INT NOT NULL,
                            `target_post_id` INT NOT NULL,
                            `target_tenant_id` INT NOT NULL,
                            `sync_enabled` TINYINT(1) DEFAULT 1 COMMENT 'Si se sincroniza al editar el original',
                            `last_synced_at` TIMESTAMP NULL DEFAULT NULL,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            PRIMARY KEY (`id`),
                            UNIQUE KEY `unique_relation` (`source_post_id`, `source_tenant_id`, `target_tenant_id`),
                            KEY `idx_source` (`source_tenant_id`, `source_post_id`),
                            KEY `idx_target` (`target_tenant_id`, `target_post_id`)
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
                }
                $results[] = "Table cross_publish_relations created";
            } else {
                $results[] = "Table cross_publish_relations already exists";
            }

            // 5. Tabla cross_publish_logs
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
                            `action` VARCHAR(50) NOT NULL COMMENT 'publish, translate, adapt, sync, error',
                            `status` VARCHAR(20) NOT NULL COMMENT 'success, failed',
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
                $results[] = "Table cross_publish_logs already exists";
            }

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
            'cross_publish_logs',
            'cross_publish_relations',
            'cross_publish_queue',
            'cross_publish_settings',
            'cross_publish_network'
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
    require_once __DIR__ . '/../../../../../core/bootstrap.php';

    $migration = new CrossPublisherInstall();

    $action = $argv[1] ?? 'up';

    if ($action === 'down') {
        $result = $migration->down();
    } else {
        $result = $migration->up();
    }

    echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
}
