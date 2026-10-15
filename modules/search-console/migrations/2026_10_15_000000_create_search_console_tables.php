<?php
/**
 * Migration: módulo Search Console
 *
 * - gsc_credentials: cuentas de servicio de Google (JSON cifrado). owner_type
 *   'platform' = la del dueño del CMS (tenant_id NULL); 'tenant' = la propia de un
 *   tenant cliente (solo la usa ese tenant).
 * - gsc_properties: propiedades de Search Console ligadas a un tenant y a la
 *   credencial con la que se leen. Los datos nunca se mezclan entre credenciales.
 * - gsc_*_daily: métricas por día y por cada combinación de dimensiones tal cual
 *   las da Google (no se suman niveles distintos entre sí).
 * - gsc_sync_runs: historial de sincronizaciones.
 * - Permisos search_console.view / search_console.manage (plantilla + copia por tenant).
 *
 * Generated at: 2026_10_15_000000
 * Compatible with: PostgreSQL + MySQL/MariaDB
 */

use Screenart\Musedock\Database;

class CreateSearchConsoleTables_2026_10_15_000000
{
    private const PERMISSIONS = [
        ['search_console.view', 'Ver Search Console', 'Ver clics, impresiones, consultas y oportunidades SEO del sitio.'],
        ['search_console.manage', 'Gestionar Search Console', 'Conectar o cambiar la cuenta de servicio de Google propia del sitio.'],
    ];

    public function up()
    {
        $pdo = Database::connect();
        $pg = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';

        $id = $pg ? 'BIGSERIAL PRIMARY KEY' : 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        $fk = $pg ? 'BIGINT' : 'BIGINT UNSIGNED';
        $ts = $pg ? 'TIMESTAMP' : 'DATETIME';
        $txt = $pg ? 'TEXT' : 'MEDIUMTEXT';
        $real = $pg ? 'DOUBLE PRECISION' : 'DOUBLE';
        $engine = $pg ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec("CREATE TABLE IF NOT EXISTS gsc_credentials (
            id {$id},
            owner_type VARCHAR(16) NOT NULL,
            tenant_id INTEGER NULL,
            client_email VARCHAR(255) NOT NULL,
            project_id VARCHAR(255) NULL,
            key_encrypted {$txt} NOT NULL,
            last_test_at {$ts} NULL,
            last_test_ok SMALLINT NULL,
            last_error VARCHAR(500) NULL,
            created_by VARCHAR(255) NULL,
            created_at {$ts} NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at {$ts} NOT NULL DEFAULT CURRENT_TIMESTAMP
        ){$engine}");
        $this->index($pdo, 'gsc_credentials_owner_uq', 'gsc_credentials', '(owner_type, tenant_id)', true);

        $pdo->exec("CREATE TABLE IF NOT EXISTS gsc_properties (
            id {$id},
            credential_id {$fk} NOT NULL,
            tenant_id INTEGER NULL,
            domain VARCHAR(255) NOT NULL,
            site_url VARCHAR(500) NOT NULL,
            permission_level VARCHAR(40) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            verification_note VARCHAR(500) NULL,
            last_sync_at {$ts} NULL,
            last_sync_status VARCHAR(20) NULL,
            last_error VARCHAR(500) NULL,
            data_from DATE NULL,
            data_until DATE NULL,
            created_at {$ts} NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at {$ts} NOT NULL DEFAULT CURRENT_TIMESTAMP
        ){$engine}");
        $this->index($pdo, 'gsc_properties_cred_site_uq', 'gsc_properties', '(credential_id, site_url)', true);
        $this->index($pdo, 'gsc_properties_tenant_idx', 'gsc_properties', '(tenant_id)');

        $metrics = "clicks INTEGER NOT NULL DEFAULT 0, impressions INTEGER NOT NULL DEFAULT 0, ctr {$real} NOT NULL DEFAULT 0, position {$real} NOT NULL DEFAULT 0";
        $pdo->exec("CREATE TABLE IF NOT EXISTS gsc_site_daily (property_id {$fk} NOT NULL, date DATE NOT NULL, {$metrics}, PRIMARY KEY (property_id, date)){$engine}");
        $pdo->exec("CREATE TABLE IF NOT EXISTS gsc_page_daily (property_id {$fk} NOT NULL, date DATE NOT NULL, page VARCHAR(2000) NOT NULL, {$metrics}){$engine}");
        $pdo->exec("CREATE TABLE IF NOT EXISTS gsc_query_daily (property_id {$fk} NOT NULL, date DATE NOT NULL, query VARCHAR(1000) NOT NULL, page VARCHAR(2000) NOT NULL, {$metrics}){$engine}");
        $pdo->exec("CREATE TABLE IF NOT EXISTS gsc_country_daily (property_id {$fk} NOT NULL, date DATE NOT NULL, country VARCHAR(8) NOT NULL, {$metrics}, PRIMARY KEY (property_id, date, country)){$engine}");
        $pdo->exec("CREATE TABLE IF NOT EXISTS gsc_device_daily (property_id {$fk} NOT NULL, date DATE NOT NULL, device VARCHAR(16) NOT NULL, {$metrics}, PRIMARY KEY (property_id, date, device)){$engine}");
        $this->index($pdo, 'gsc_page_daily_prop_date_idx', 'gsc_page_daily', '(property_id, date)');
        $this->index($pdo, 'gsc_query_daily_prop_date_idx', 'gsc_query_daily', '(property_id, date)');

        $pdo->exec("CREATE TABLE IF NOT EXISTS gsc_sync_runs (
            id {$id},
            property_id {$fk} NOT NULL,
            started_at {$ts} NOT NULL DEFAULT CURRENT_TIMESTAMP,
            finished_at {$ts} NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'running',
            date_from DATE NULL,
            date_until DATE NULL,
            rows_stored INTEGER NOT NULL DEFAULT 0,
            attempts SMALLINT NOT NULL DEFAULT 1,
            error VARCHAR(1000) NULL
        ){$engine}");
        $this->index($pdo, 'gsc_sync_runs_prop_idx', 'gsc_sync_runs', '(property_id, started_at)');

        $this->permissions($pdo);
        echo "✓ Search Console tables and permissions ready\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        foreach (['gsc_sync_runs', 'gsc_device_daily', 'gsc_country_daily', 'gsc_query_daily', 'gsc_page_daily', 'gsc_site_daily', 'gsc_properties', 'gsc_credentials'] as $table) {
            $pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
        foreach (self::PERMISSIONS as [$slug]) {
            $pdo->prepare("DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE slug = ?)")->execute([$slug]);
            $pdo->prepare("DELETE FROM user_permissions WHERE permission_slug = ?")->execute([$slug]);
            $pdo->prepare("DELETE FROM permissions WHERE slug = ?")->execute([$slug]);
        }
    }

    private function index(PDO $pdo, string $name, string $table, string $columns, bool $unique = false): void
    {
        $kind = $unique ? 'UNIQUE INDEX' : 'INDEX';
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $pdo->exec("CREATE {$kind} IF NOT EXISTS {$name} ON {$table} {$columns}");
            return;
        }
        $exists = $pdo->prepare("SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1");
        $exists->execute([$table, $name]);
        if (!$exists->fetchColumn()) {
            $pdo->exec("CREATE {$kind} {$name} ON {$table} {$columns}");
        }
    }

    private function permissions(PDO $pdo): void
    {
        $tenantIds = $pdo->query("SELECT DISTINCT tenant_id FROM permissions WHERE tenant_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
        $global = $pdo->prepare("SELECT id FROM permissions WHERE slug = ? AND tenant_id IS NULL LIMIT 1");
        $perTenant = $pdo->prepare("SELECT id FROM permissions WHERE slug = ? AND tenant_id = ? LIMIT 1");
        $insert = $pdo->prepare("INSERT INTO permissions (slug, name, description, category, tenant_id, scope, created_at, updated_at) VALUES (?, ?, ?, 'SEO', ?, 'tenant', NOW(), NOW())");

        foreach (self::PERMISSIONS as [$slug, $name, $description]) {
            $global->execute([$slug]);
            if (!$global->fetchColumn()) {
                $insert->execute([$slug, $name, $description, null]);
            }
            foreach ($tenantIds as $tenantId) {
                $perTenant->execute([$slug, (int) $tenantId]);
                if (!$perTenant->fetchColumn()) {
                    $insert->execute([$slug, $name, $description, (int) $tenantId]);
                }
            }
        }
    }
}
