<?php

/**
 * Migración: Agregar columnas para Soft Delete
 * Fecha: 2025-01-13
 * Descripción: Agrega columnas deleted_at y deleted_by para eliminación lógica
 *
 * IMPORTANTE: Después de ejecutar esta migración, todas las queries deben incluir:
 * WHERE deleted_at IS NULL
 *
 * Ejemplo:
 * SELECT * FROM blog_posts WHERE tenant_id = ? AND deleted_at IS NULL
 */

use Screenart\Musedock\Database;

class AddSoftDeleteColumns_2025_01_13_000000
{
    private $tables = [
        'blog_posts',
        'blog_categories',
        'blog_tags',
    ];

    public function up()
    {
        $pdo = Database::connect();

        foreach ($this->tables as $table) {
            try {
                // Verificar si las columnas ya existen
                $stmt = $pdo->query("SHOW COLUMNS FROM {$table} LIKE 'deleted_at'");
                $deletedAtExists = $stmt->fetch();

                $stmt = $pdo->query("SHOW COLUMNS FROM {$table} LIKE 'deleted_by'");
                $deletedByExists = $stmt->fetch();

                if (!$deletedAtExists && !$deletedByExists) {
                    $pdo->exec("
                        ALTER TABLE {$table}
                        ADD COLUMN deleted_at DATETIME NULL COMMENT 'Fecha y hora de eliminación lógica',
                        ADD COLUMN deleted_by INT NULL COMMENT 'ID del usuario que eliminó el registro'
                    ");
                    echo "✓ Columnas deleted_at y deleted_by agregadas a tabla '{$table}'\n";
                } elseif (!$deletedAtExists) {
                    $pdo->exec("
                        ALTER TABLE {$table}
                        ADD COLUMN deleted_at DATETIME NULL COMMENT 'Fecha y hora de eliminación lógica'
                    ");
                    echo "✓ Columna deleted_at agregada a tabla '{$table}'\n";
                } elseif (!$deletedByExists) {
                    $pdo->exec("
                        ALTER TABLE {$table}
                        ADD COLUMN deleted_by INT NULL COMMENT 'ID del usuario que eliminó el registro'
                    ");
                    echo "✓ Columna deleted_by agregada a tabla '{$table}'\n";
                } else {
                    echo "⚠ Columnas de soft delete ya existen en tabla '{$table}'\n";
                }

                // Crear índice para deleted_at si no existe
                $indexName = "idx_{$table}_deleted_at";
                $stmt = $pdo->query("SHOW INDEX FROM {$table} WHERE Key_name = '{$indexName}'");
                $indexExists = $stmt->fetch();

                if (!$indexExists) {
                    $pdo->exec("CREATE INDEX {$indexName} ON {$table}(deleted_at)");
                    echo "✓ Índice '{$indexName}' creado en tabla '{$table}'\n";
                } else {
                    echo "⚠ Índice '{$indexName}' ya existe en tabla '{$table}'\n";
                }

            } catch (Exception $e) {
                echo "✗ Error procesando tabla '{$table}': " . $e->getMessage() . "\n";
                // Continuar con la siguiente tabla en lugar de lanzar excepción
            }
        }

        echo "\n";
        echo "════════════════════════════════════════════════════════════\n";
        echo "⚠️  IMPORTANTE: Actualizar queries en el código\n";
        echo "════════════════════════════════════════════════════════════\n";
        echo "Todas las queries deben incluir: WHERE deleted_at IS NULL\n";
        echo "Ejemplo: SELECT * FROM blog_posts WHERE tenant_id = ? AND deleted_at IS NULL\n";
        echo "════════════════════════════════════════════════════════════\n";
    }

    public function down()
    {
        $pdo = Database::connect();

        foreach ($this->tables as $table) {
            try {
                // Verificar si las columnas existen
                $stmt = $pdo->query("SHOW COLUMNS FROM {$table} LIKE 'deleted_at'");
                $deletedAtExists = $stmt->fetch();

                $stmt = $pdo->query("SHOW COLUMNS FROM {$table} LIKE 'deleted_by'");
                $deletedByExists = $stmt->fetch();

                // Eliminar índice primero
                $indexName = "idx_{$table}_deleted_at";
                $stmt = $pdo->query("SHOW INDEX FROM {$table} WHERE Key_name = '{$indexName}'");
                $indexExists = $stmt->fetch();

                if ($indexExists) {
                    $pdo->exec("DROP INDEX {$indexName} ON {$table}");
                    echo "✓ Índice '{$indexName}' eliminado de tabla '{$table}'\n";
                }

                // Eliminar columnas
                if ($deletedAtExists && $deletedByExists) {
                    $pdo->exec("
                        ALTER TABLE {$table}
                        DROP COLUMN deleted_at,
                        DROP COLUMN deleted_by
                    ");
                    echo "✓ Columnas deleted_at y deleted_by eliminadas de tabla '{$table}'\n";
                } elseif ($deletedAtExists) {
                    $pdo->exec("ALTER TABLE {$table} DROP COLUMN deleted_at");
                    echo "✓ Columna deleted_at eliminada de tabla '{$table}'\n";
                } elseif ($deletedByExists) {
                    $pdo->exec("ALTER TABLE {$table} DROP COLUMN deleted_by");
                    echo "✓ Columna deleted_by eliminada de tabla '{$table}'\n";
                } else {
                    echo "⚠ Columnas de soft delete no existen en tabla '{$table}'\n";
                }

            } catch (Exception $e) {
                echo "✗ Error al revertir tabla '{$table}': " . $e->getMessage() . "\n";
            }
        }
    }
}
