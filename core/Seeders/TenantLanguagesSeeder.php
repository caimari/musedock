<?php

namespace Screenart\Musedock\Seeders;

use Screenart\Musedock\Database;

class TenantLanguagesSeeder
{
    /**
     * Seed idiomas base para un tenant específico
     * Por defecto: Español e Inglés
     *
     * @param int|null $tenantId ID del tenant, o null para todos los tenants activos
     */
    public function run($tenantId = null)
    {
        $pdo = Database::connect();

        // Si no se especifica tenant, obtener todos los tenants activos
        if ($tenantId === null) {
            $stmt = $pdo->query("SELECT id FROM tenants WHERE status = 'active'");
            $tenants = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } else {
            $tenants = [$tenantId];
        }

        foreach ($tenants as $tid) {
            $this->seedTenantLanguages($tid);
        }
    }

    /**
     * Seed idiomas para un tenant específico
     */
    private function seedTenantLanguages($tenantId)
    {
        $pdo = Database::connect();

        // Verificar si el tenant ya tiene idiomas
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM languages WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($result['count'] > 0) {
            echo "⚠ Tenant {$tenantId} ya tiene idiomas configurados. Saltando...\\n";
            return;
        }

        echo "🌍 Agregando idiomas base (ES, EN) al tenant {$tenantId}...\\n";

        // Idiomas por defecto
        $languages = [
            [
                'code' => 'es',
                'name' => 'Español',
                'active' => 1,
                'order_position' => 0
            ],
            [
                'code' => 'en',
                'name' => 'English',
                'active' => 1,
                'order_position' => 1
            ]
        ];

        $stmt = $pdo->prepare("
            INSERT INTO languages (tenant_id, code, name, active, order_position, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        foreach ($languages as $lang) {
            $stmt->execute([
                $tenantId,
                $lang['code'],
                $lang['name'],
                $lang['active'],
                $lang['order_position']
            ]);
        }

        // Establecer español como idioma por defecto del tenant
        $this->setDefaultLanguage($tenantId, 'es');

        echo "✓ Idiomas base agregados al tenant {$tenantId}\\n";
    }

    /**
     * Establece el idioma por defecto del tenant
     */
    private function setDefaultLanguage($tenantId, $langCode)
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        // Nombre de columna compatible con ambos drivers
        $keyColumn = $driver === 'mysql' ? '`key`' : '"key"';

        // Verificar si ya existe la configuración
        $stmt = $pdo->prepare("
            SELECT id FROM tenant_settings
            WHERE tenant_id = ? AND {$keyColumn} = 'default_lang'
        ");
        $stmt->execute([$tenantId]);
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($existing) {
            // Actualizar
            $stmt = $pdo->prepare("
                UPDATE tenant_settings
                SET value = ?
                WHERE tenant_id = ? AND {$keyColumn} = 'default_lang'
            ");
            $stmt->execute([$langCode, $tenantId]);
        } else {
            // Insertar
            $stmt = $pdo->prepare("
                INSERT INTO tenant_settings (tenant_id, {$keyColumn}, value, created_at)
                VALUES (?, 'default_lang', ?, NOW())
            ");
            $stmt->execute([$tenantId, $langCode]);
        }

        echo "✓ Idioma por defecto establecido: {$langCode}\\n";
    }

    /**
     * Método auxiliar para agregar idiomas adicionales a un tenant
     *
     * @param int $tenantId ID del tenant
     * @param array $languages Array de idiomas [['code' => 'fr', 'name' => 'Français'], ...]
     */
    public function addLanguages($tenantId, array $languages)
    {
        $pdo = Database::connect();

        // Obtener la siguiente posición
        $stmt = $pdo->prepare("
            SELECT COALESCE(MAX(order_position), -1) + 1 as next_pos
            FROM languages
            WHERE tenant_id = ?
        ");
        $stmt->execute([$tenantId]);
        $nextPos = $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            INSERT INTO languages (tenant_id, code, name, active, order_position, created_at)
            VALUES (?, ?, ?, 1, ?, NOW())
        ");

        foreach ($languages as $lang) {
            $stmt->execute([
                $tenantId,
                $lang['code'],
                $lang['name'],
                $nextPos++
            ]);
            echo "✓ Idioma {$lang['name']} ({$lang['code']}) agregado al tenant {$tenantId}\\n";
        }
    }
}
