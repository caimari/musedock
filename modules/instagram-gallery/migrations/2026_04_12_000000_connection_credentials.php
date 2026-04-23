<?php

/**
 * Migration: mover las credenciales de la app de Meta a cada conexión.
 *
 * Antes: `instagram_settings` guardaba `instagram_app_id`,
 * `instagram_app_secret`, `instagram_redirect_uri` como 3 filas globales
 * por tenant. Implicaba que todas las cuentas de un tenant compartían
 * la misma app de Meta.
 *
 * Ahora: cada `instagram_connections` tiene SUS propias credenciales.
 * Así cada cuenta puede usar una app distinta (o reutilizar la misma
 * pegándola dos veces). Además simplifica la UI: una sola pantalla con
 * una tarjeta por cuenta, cada una con su bloque de credenciales.
 */
class ConnectionCredentials_2026_04_12_000000
{
    public function up()
    {
        $pdo = \Screenart\Musedock\Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $this->addColumnsIfMissingMySQL($pdo);
        } else {
            $this->addColumnsIfMissingPostgres($pdo);
        }

        // Copiar credenciales ya guardadas en instagram_settings a cada conexión del mismo tenant.
        $stmt = $pdo->query("SELECT id, tenant_id FROM instagram_connections");
        $conns = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $sel = $pdo->prepare("SELECT setting_key, setting_value FROM instagram_settings WHERE tenant_id <=> ? AND setting_key IN ('instagram_app_id','instagram_app_secret','instagram_redirect_uri')");
        // "<=>" es de MySQL. En PostgreSQL usamos IS NOT DISTINCT FROM.
        if ($driver !== 'mysql') {
            $sel = $pdo->prepare("SELECT setting_key, setting_value FROM instagram_settings WHERE tenant_id IS NOT DISTINCT FROM ? AND setting_key IN ('instagram_app_id','instagram_app_secret','instagram_redirect_uri')");
        }
        $upd = $pdo->prepare("UPDATE instagram_connections SET app_id = ?, app_secret = ?, redirect_uri = ? WHERE id = ?");

        foreach ($conns as $c) {
            $sel->execute([$c['tenant_id']]);
            $settings = [];
            foreach ($sel->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            if (!empty($settings)) {
                $upd->execute([
                    $settings['instagram_app_id'] ?? null,
                    $settings['instagram_app_secret'] ?? null,
                    $settings['instagram_redirect_uri'] ?? null,
                    $c['id'],
                ]);
            }
        }

        error_log('InstagramGallery: connection credentials migrated');
    }

    private function addColumnsIfMissingMySQL(\PDO $pdo): void
    {
        $cols = $this->listColumnsMySQL($pdo, 'instagram_connections');
        if (!in_array('app_id', $cols, true)) {
            $pdo->exec("ALTER TABLE instagram_connections ADD COLUMN app_id VARCHAR(64) DEFAULT NULL AFTER user_id");
        }
        if (!in_array('app_secret', $cols, true)) {
            $pdo->exec("ALTER TABLE instagram_connections ADD COLUMN app_secret VARCHAR(255) DEFAULT NULL AFTER app_id");
        }
        if (!in_array('redirect_uri', $cols, true)) {
            $pdo->exec("ALTER TABLE instagram_connections ADD COLUMN redirect_uri VARCHAR(500) DEFAULT NULL AFTER app_secret");
        }
    }

    private function addColumnsIfMissingPostgres(\PDO $pdo): void
    {
        $pdo->exec("ALTER TABLE instagram_connections ADD COLUMN IF NOT EXISTS app_id VARCHAR(64) DEFAULT NULL");
        $pdo->exec("ALTER TABLE instagram_connections ADD COLUMN IF NOT EXISTS app_secret VARCHAR(255) DEFAULT NULL");
        $pdo->exec("ALTER TABLE instagram_connections ADD COLUMN IF NOT EXISTS redirect_uri VARCHAR(500) DEFAULT NULL");
    }

    private function listColumnsMySQL(\PDO $pdo, string $table): array
    {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $out[] = $row['Field'];
        }
        return $out;
    }
}
