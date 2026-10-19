<?php
use Screenart\Musedock\Database;

/**
 * Columnas que el código usa y que ninguna migración creaba (en musedock.com se añadieron por otro camino).
 * Las instalaciones antiguas o nuevas no las tenían: p. ej. /blog daba 500 por falta de blog_posts.post_type
 * (muselayer.com, MariaDB, 2026-10-19). Solo añade lo que falta; PostgreSQL y MySQL/MariaDB.
 */
class AddColumnsMissingFromMigrations_2026_10_19_220000
{
    /** tabla => [columna => [definición PostgreSQL, definición MySQL]] */
    private const COLUMNS = [
        'blog_posts' => [
            'post_type' => ["VARCHAR(20) NOT NULL DEFAULT 'post'", "VARCHAR(20) NOT NULL DEFAULT 'post'"],
            'facebook_posted_at' => ['TIMESTAMP NULL', 'DATETIME NULL DEFAULT NULL'],
            'facebook_post_id' => ['VARCHAR(100) NULL', 'VARCHAR(100) NULL DEFAULT NULL'],
            'facebook_permalink' => ['VARCHAR(500) NULL', 'VARCHAR(500) NULL DEFAULT NULL'],
        ],
        'api_keys' => [
            'auth_type' => ["VARCHAR(10) NOT NULL DEFAULT 'token'", "VARCHAR(10) NOT NULL DEFAULT 'token'"],
            'oauth_client_id' => ['VARCHAR(255) NULL', 'VARCHAR(255) NULL DEFAULT NULL'],
        ],
        'modules' => [
            'show_in_dashboard' => ['SMALLINT NOT NULL DEFAULT 0', 'TINYINT(1) NOT NULL DEFAULT 0'],
        ],
        'superadmin_plugins' => [
            'show_in_dashboard' => ['SMALLINT NOT NULL DEFAULT 1', 'TINYINT(1) NOT NULL DEFAULT 1'],
        ],
        'tenants' => [
            'group_id' => ['INTEGER NULL', 'INT NULL DEFAULT NULL'],
            'is_client_domain' => ['SMALLINT NOT NULL DEFAULT 0', 'TINYINT(1) NOT NULL DEFAULT 0'],
            'label_color' => ["VARCHAR(16) NOT NULL DEFAULT ''", "VARCHAR(16) NOT NULL DEFAULT ''"],
        ],
        'web_analytics' => [
            'bot_kind' => ['VARCHAR(20) NULL', 'VARCHAR(20) NULL DEFAULT NULL'],
            'bot_name' => ['VARCHAR(80) NULL', 'VARCHAR(80) NULL DEFAULT NULL'],
        ],
    ];

    /** Columnas DNS comunes a tenants, alias, pedidos y redirecciones (registro único de cuentas DNS) */
    private const DNS_TABLES = ['tenants', 'domain_aliases', 'domain_orders', 'domain_redirects'];
    private const DNS_COLUMNS = [
        'dns_provider' => ['VARCHAR(50) NULL', 'VARCHAR(50) NULL DEFAULT NULL'],
        'dns_mode' => ['VARCHAR(50) NULL', 'VARCHAR(50) NULL DEFAULT NULL'],
        'dns_account_ref' => ['VARCHAR(255) NULL', 'VARCHAR(255) NULL DEFAULT NULL'],
        'dns_metadata' => ['TEXT NULL', 'TEXT NULL'],
        'dns_configured_at' => ['TIMESTAMP NULL', 'DATETIME NULL DEFAULT NULL'],
    ];

    public function up(): void
    {
        $pdo = Database::connect();
        $pg = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql';
        $schemaSql = $pg ? 'current_schema()' : 'DATABASE()';

        $columns = self::COLUMNS;
        foreach (self::DNS_TABLES as $t) {
            $columns[$t] = ($columns[$t] ?? []) + self::DNS_COLUMNS;
        }

        $tableExists = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = {$schemaSql} AND table_name = ?");
        $columnExists = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = {$schemaSql} AND table_name = ? AND column_name = ?");

        foreach ($columns as $table => $cols) {
            $tableExists->execute([$table]);
            if (!$tableExists->fetchColumn()) {
                continue; // tabla de un módulo o plugin no instalado
            }
            foreach ($cols as $col => [$pgDef, $myDef]) {
                $columnExists->execute([$table, $col]);
                if ($columnExists->fetchColumn()) {
                    continue;
                }
                $pdo->exec($pg
                    ? "ALTER TABLE {$table} ADD COLUMN {$col} {$pgDef}"
                    : "ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$myDef}");
            }
        }
    }

    public function down(): void { /* Se conservan las columnas. */ }
}
