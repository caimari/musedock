<?php
/**
 * News Aggregator Plugin - Migration: Add Source Feeds
 *
 * Adds per-source processing types (direct/verified) and a new
 * source_feeds table for storing multiple RSS URLs per source.
 *
 * Changes:
 * - new table: news_aggregator_source_feeds
 * - new columns on sources: processing_type, min_sources_for_publish
 * - new column on items: feed_id
 * - new column on clusters: source_id
 * - data migration: creates 1 feed per existing RSS source
 */

use Screenart\Musedock\Database;

class NewsAggregatorAddSourceFeeds
{
    public function up(): array
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $results = [];

        try {
            // 1. Add processing_type and min_sources_for_publish to sources
            $this->addColumnIfNotExists($pdo, $driver, 'news_aggregator_sources', 'processing_type', "VARCHAR(20) DEFAULT 'direct'");
            $this->addColumnIfNotExists($pdo, $driver, 'news_aggregator_sources', 'min_sources_for_publish', 'INTEGER DEFAULT 2');
            $results[] = "Added processing_type and min_sources_for_publish to sources";

            // 2. Create news_aggregator_source_feeds table
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

            // 3. Add feed_id to items
            $this->addColumnIfNotExists($pdo, $driver, 'news_aggregator_items', 'feed_id', 'INTEGER DEFAULT NULL');
            try {
                if ($driver === 'pgsql') {
                    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_items_feed ON news_aggregator_items(feed_id)");
                } else {
                    $stmt = $pdo->prepare("SHOW INDEX FROM news_aggregator_items WHERE Key_name = 'idx_items_feed'");
                    $stmt->execute();
                    if ($stmt->rowCount() === 0) {
                        $pdo->exec("ALTER TABLE news_aggregator_items ADD INDEX idx_items_feed (feed_id)");
                    }
                }
            } catch (\Exception $e) {
                // Ignore
            }
            $results[] = "Added feed_id to items";

            // 4. Add source_id to clusters
            $this->addColumnIfNotExists($pdo, $driver, 'news_aggregator_clusters', 'source_id', 'INTEGER DEFAULT NULL');
            try {
                if ($driver === 'pgsql') {
                    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_clusters_source ON news_aggregator_clusters(source_id)");
                } else {
                    $stmt = $pdo->prepare("SHOW INDEX FROM news_aggregator_clusters WHERE Key_name = 'idx_clusters_source'");
                    $stmt->execute();
                    if ($stmt->rowCount() === 0) {
                        $pdo->exec("ALTER TABLE news_aggregator_clusters ADD INDEX idx_clusters_source (source_id)");
                    }
                }
            } catch (\Exception $e) {
                // Ignore
            }
            $results[] = "Added source_id to clusters";

            // 5. Data migration: create 1 feed per existing RSS source
            $migrated = $this->migrateExistingSources($pdo);
            $results[] = "Migrated {$migrated} existing sources to source_feeds";

            return ['success' => true, 'results' => $results];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'results' => $results];
        }
    }

    /**
     * Migrate existing RSS sources: create 1 feed row per source
     */
    private function migrateExistingSources(\PDO $pdo): int
    {
        $migrated = 0;

        // Find RSS sources that have a URL but no feeds yet
        $stmt = $pdo->prepare("
            SELECT s.id, s.name, s.url
            FROM news_aggregator_sources s
            WHERE s.source_type = 'rss'
              AND s.url IS NOT NULL
              AND s.url != ''
              AND NOT EXISTS (
                  SELECT 1 FROM news_aggregator_source_feeds f WHERE f.source_id = s.id
              )
        ");
        $stmt->execute();
        $sources = $stmt->fetchAll(\PDO::FETCH_OBJ);

        $insertStmt = $pdo->prepare("
            INSERT INTO news_aggregator_source_feeds (source_id, name, url, enabled, sort_order)
            VALUES (?, ?, ?, TRUE, 0)
        ");

        foreach ($sources as $source) {
            $insertStmt->execute([$source->id, $source->name, $source->url]);
            $migrated++;
        }

        // Set all existing sources as 'direct' if not already set
        $pdo->exec("UPDATE news_aggregator_sources SET processing_type = 'direct' WHERE processing_type IS NULL");

        return $migrated;
    }

    /**
     * Add column if not exists (safe for production)
     */
    private function addColumnIfNotExists(\PDO $pdo, string $driver, string $table, string $column, string $definition): void
    {
        try {
            if ($driver === 'pgsql') {
                $pdo->exec("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS {$column} {$definition}");
            } else {
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
            // Silently ignore
        }
    }

    /**
     * Check if table exists
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
                    WHERE table_schema = 'public' AND table_name = ?
                )
            ");
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        }
    }
}

// Execute if called directly
if (php_sapi_name() === 'cli' || (isset($argv) && basename($argv[0]) === basename(__FILE__))) {
    require_once __DIR__ . '/../../../../../../core/bootstrap.php';

    $migration = new NewsAggregatorAddSourceFeeds();
    $result = $migration->up();
    echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
}
