<?php
/**
 * Migration: Fix pages unique slug constraint to include tenant_id
 * This allows the same slug to exist in different tenants/global
 *
 * Compatible with: MySQL/MariaDB + PostgreSQL
 */

use Screenart\Musedock\Database;

class FixPagesUniqueSlugConstraint_2025_12_13_120000
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        echo "Actualizando restricción UNIQUE de slug en tabla pages...\n";

        if ($driver === 'mysql') {
            // MySQL: Eliminar índice único antiguo y crear uno nuevo con tenant_id
            try {
                // Verificar si existe el índice 'slug'
                $stmt = $pdo->query("SHOW INDEX FROM pages WHERE Key_name = 'slug'");
                if ($stmt->fetch()) {
                    $pdo->exec("ALTER TABLE pages DROP INDEX `slug`");
                    echo "✓ Índice 'slug' eliminado\n";
                }
            } catch (\PDOException $e) {
                echo "⚠ No se pudo eliminar índice 'slug': " . $e->getMessage() . "\n";
            }

            // Crear nuevo índice único que incluye tenant_id
            try {
                $pdo->exec("CREATE UNIQUE INDEX `unique_tenant_slug` ON pages (`tenant_id`, `slug`)");
                echo "✓ Nuevo índice 'unique_tenant_slug' creado (tenant_id, slug)\n";
            } catch (\PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                    echo "⚠ El índice 'unique_tenant_slug' ya existe\n";
                } else {
                    throw $e;
                }
            }
        } else {
            // PostgreSQL
            try {
                // Eliminar la restricción única antigua
                $pdo->exec("ALTER TABLE pages DROP CONSTRAINT IF EXISTS pages_slug_key");
                echo "✓ Restricción 'pages_slug_key' eliminada\n";
            } catch (\PDOException $e) {
                echo "⚠ No se pudo eliminar restricción: " . $e->getMessage() . "\n";
            }

            // Crear nuevo índice único que incluye tenant_id
            // NOTA: Para PostgreSQL, NULL se trata como valor distinto, así que usamos COALESCE
            try {
                $pdo->exec("CREATE UNIQUE INDEX unique_tenant_slug ON pages (COALESCE(tenant_id, 0), slug)");
                echo "✓ Nuevo índice 'unique_tenant_slug' creado\n";
            } catch (\PDOException $e) {
                if (strpos($e->getMessage(), 'already exists') !== false) {
                    echo "⚠ El índice 'unique_tenant_slug' ya existe\n";
                } else {
                    throw $e;
                }
            }
        }

        echo "✓ Migración completada - Ahora el mismo slug puede existir en diferentes tenants\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        echo "Revirtiendo cambios en restricción de slug...\n";

        if ($driver === 'mysql') {
            try {
                $pdo->exec("ALTER TABLE pages DROP INDEX `unique_tenant_slug`");
                $pdo->exec("CREATE UNIQUE INDEX `slug` ON pages (`slug`)");
                echo "✓ Índice original restaurado\n";
            } catch (\PDOException $e) {
                echo "⚠ Error al revertir: " . $e->getMessage() . "\n";
            }
        } else {
            try {
                $pdo->exec("DROP INDEX IF EXISTS unique_tenant_slug");
                $pdo->exec("ALTER TABLE pages ADD CONSTRAINT pages_slug_key UNIQUE (slug)");
                echo "✓ Restricción original restaurada\n";
            } catch (\PDOException $e) {
                echo "⚠ Error al revertir: " . $e->getMessage() . "\n";
            }
        }
    }
}
