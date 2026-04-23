<?php

/**
 * Migration: columnas de Facebook Page en instagram_connections.
 *
 * Al conectar una cuenta de Instagram Business, Meta permite vincular
 * opcionalmente la Página de Facebook que esté asociada. Guardamos:
 *   - facebook_page_id       ID de la Página
 *   - facebook_page_name     Nombre amigable (cache)
 *   - facebook_page_token    Page access token (NO caduca — a diferencia
 *                            del IG user token que son 60 días)
 *   - facebook_user_token    Token del usuario que autorizó (se usa sólo
 *                            para poder listar/renovar page tokens)
 *   - facebook_enabled       Flag on/off para habilitar publicación en FB
 */
class FacebookPageConnection_2026_04_12_200000
{
    public function up()
    {
        $pdo = \Screenart\Musedock\Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $cols = $this->listColumns($pdo, $driver, 'instagram_connections');

        $toAdd = [
            'facebook_page_id'    => 'VARCHAR(64) DEFAULT NULL',
            'facebook_page_name'  => 'VARCHAR(255) DEFAULT NULL',
            'facebook_page_token' => 'TEXT DEFAULT NULL',
            'facebook_user_token' => 'TEXT DEFAULT NULL',
            'facebook_enabled'    => ($driver === 'mysql' ? 'TINYINT(1) DEFAULT 0' : 'BOOLEAN DEFAULT FALSE'),
        ];

        foreach ($toAdd as $name => $def) {
            if (in_array($name, $cols, true)) continue;
            if ($driver === 'mysql') {
                $pdo->exec("ALTER TABLE `instagram_connections` ADD COLUMN `{$name}` {$def}");
            } else {
                $pdo->exec("ALTER TABLE instagram_connections ADD COLUMN IF NOT EXISTS {$name} {$def}");
            }
        }
        error_log('SocialPublisher: facebook_* columns added to instagram_connections');
    }

    private function listColumns(\PDO $pdo, string $driver, string $table): array
    {
        if ($driver === 'mysql') {
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
            return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'Field');
        }
        $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name = ?");
        $stmt->execute([$table]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}
