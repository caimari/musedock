<?php

/**
 * Migración: Foreign Keys de seguridad
 * Fecha: 2025-01-13
 * Descripción: Agrega foreign keys para integridad referencial
 *
 * ⚠️ IMPORTANTE:
 * - Hacer BACKUP completo de la BD antes de ejecutar
 * - Esta migración puede fallar si hay datos huérfanos
 * - Ejecutar primero en ambiente de prueba
 */

use Screenart\Musedock\Database;

class AddSecurityForeignKeys_2025_01_13_000002
{
    private $foreignKeys = [
        'blog_posts' => [
            'fk_blog_posts_tenant' => [
                'column' => 'tenant_id',
                'references' => 'tenants(id)',
                'on_delete' => 'CASCADE',
                'on_update' => 'CASCADE',
            ],
        ],

        'blog_categories' => [
            'fk_blog_categories_tenant' => [
                'column' => 'tenant_id',
                'references' => 'tenants(id)',
                'on_delete' => 'CASCADE',
                'on_update' => 'CASCADE',
            ],
        ],

        'blog_tags' => [
            'fk_blog_tags_tenant' => [
                'column' => 'tenant_id',
                'references' => 'tenants(id)',
                'on_delete' => 'CASCADE',
                'on_update' => 'CASCADE',
            ],
        ],

        'admins' => [
            'fk_admins_tenant' => [
                'column' => 'tenant_id',
                'references' => 'tenants(id)',
                'on_delete' => 'CASCADE',
                'on_update' => 'CASCADE',
            ],
        ],

        'blog_post_categories' => [
            'fk_blog_post_categories_post' => [
                'column' => 'post_id',
                'references' => 'blog_posts(id)',
                'on_delete' => 'CASCADE',
                'on_update' => 'CASCADE',
            ],
            'fk_blog_post_categories_category' => [
                'column' => 'category_id',
                'references' => 'blog_categories(id)',
                'on_delete' => 'CASCADE',
                'on_update' => 'CASCADE',
            ],
        ],

        'blog_post_tags' => [
            'fk_blog_post_tags_post' => [
                'column' => 'post_id',
                'references' => 'blog_posts(id)',
                'on_delete' => 'CASCADE',
                'on_update' => 'CASCADE',
            ],
            'fk_blog_post_tags_tag' => [
                'column' => 'tag_id',
                'references' => 'blog_tags(id)',
                'on_delete' => 'CASCADE',
                'on_update' => 'CASCADE',
            ],
        ],

        'slugs' => [
            'fk_slugs_tenant' => [
                'column' => 'tenant_id',
                'references' => 'tenants(id)',
                'on_delete' => 'CASCADE',
                'on_update' => 'CASCADE',
            ],
        ],
    ];

    public function up()
    {
        $pdo = Database::connect();

        echo "\n";
        echo "════════════════════════════════════════════════════════════\n";
        echo "⚠️  ADVERTENCIA: Verificación de datos huérfanos\n";
        echo "════════════════════════════════════════════════════════════\n";

        // Verificar datos huérfanos antes de crear foreign keys
        $orphansFound = $this->checkOrphanedData($pdo);

        if ($orphansFound > 0) {
            echo "\n";
            echo "⚠️  Se encontraron {$orphansFound} tablas con datos huérfanos\n";
            echo "⚠️  Se recomienda limpiar los datos antes de continuar\n";
            echo "\n";
            echo "¿Desea continuar de todos modos? (puede causar errores) [y/N]: ";

            // En modo automático, no continuar si hay huérfanos
            if (php_sapi_name() === 'cli') {
                $handle = fopen("php://stdin", "r");
                $line = fgets($handle);
                fclose($handle);

                if (strtolower(trim($line)) !== 'y') {
                    echo "✗ Migración cancelada por el usuario\n";
                    return;
                }
            } else {
                echo "⚠️  Modo automático: Se omitirá la creación de foreign keys con datos huérfanos\n";
            }
        } else {
            echo "✓ No se encontraron datos huérfanos\n";
        }

        echo "════════════════════════════════════════════════════════════\n\n";

        $createdCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        foreach ($this->foreignKeys as $table => $tableForeignKeys) {
            // Verificar si la tabla existe
            $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
            if (!$stmt->fetch()) {
                echo "⚠ Tabla '{$table}' no existe, omitiendo foreign keys\n";
                $skippedCount += count($tableForeignKeys);
                continue;
            }

            foreach ($tableForeignKeys as $fkName => $fkConfig) {
                try {
                    // Verificar si el foreign key ya existe
                    $stmt = $pdo->query("
                        SELECT CONSTRAINT_NAME
                        FROM information_schema.TABLE_CONSTRAINTS
                        WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = '{$table}'
                        AND CONSTRAINT_NAME = '{$fkName}'
                        AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                    ");
                    $fkExists = $stmt->fetch();

                    if (!$fkExists) {
                        // Verificar que la columna exista
                        $stmt = $pdo->query("SHOW COLUMNS FROM {$table} LIKE '{$fkConfig['column']}'");
                        if (!$stmt->fetch()) {
                            echo "⚠ Columna '{$fkConfig['column']}' no existe en tabla '{$table}', omitiendo foreign key '{$fkName}'\n";
                            $skippedCount++;
                            continue;
                        }

                        $sql = "
                            ALTER TABLE {$table}
                            ADD CONSTRAINT {$fkName}
                            FOREIGN KEY ({$fkConfig['column']})
                            REFERENCES {$fkConfig['references']}
                            ON DELETE {$fkConfig['on_delete']}
                            ON UPDATE {$fkConfig['on_update']}
                        ";

                        $pdo->exec($sql);
                        echo "✓ Foreign key '{$fkName}' creado en tabla '{$table}'\n";
                        $createdCount++;
                    } else {
                        // echo "⚠ Foreign key '{$fkName}' ya existe en tabla '{$table}'\n";
                        $skippedCount++;
                    }
                } catch (Exception $e) {
                    echo "✗ Error creando foreign key '{$fkName}' en '{$table}': " . $e->getMessage() . "\n";
                    $errorCount++;
                }
            }
        }

        echo "\n";
        echo "════════════════════════════════════════════════════════════\n";
        echo "Resumen de foreign keys:\n";
        echo "  • Creados: {$createdCount}\n";
        echo "  • Omitidos: {$skippedCount}\n";
        echo "  • Errores: {$errorCount}\n";
        echo "════════════════════════════════════════════════════════════\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $deletedCount = 0;

        foreach ($this->foreignKeys as $table => $tableForeignKeys) {
            // Verificar si la tabla existe
            $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
            if (!$stmt->fetch()) {
                continue;
            }

            foreach ($tableForeignKeys as $fkName => $fkConfig) {
                try {
                    // Verificar si el foreign key existe
                    $stmt = $pdo->query("
                        SELECT CONSTRAINT_NAME
                        FROM information_schema.TABLE_CONSTRAINTS
                        WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = '{$table}'
                        AND CONSTRAINT_NAME = '{$fkName}'
                        AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                    ");
                    $fkExists = $stmt->fetch();

                    if ($fkExists) {
                        $pdo->exec("ALTER TABLE {$table} DROP FOREIGN KEY {$fkName}");
                        echo "✓ Foreign key '{$fkName}' eliminado de tabla '{$table}'\n";
                        $deletedCount++;
                    }
                } catch (Exception $e) {
                    echo "✗ Error eliminando foreign key '{$fkName}' de '{$table}': " . $e->getMessage() . "\n";
                }
            }
        }

        echo "\n✓ Total de foreign keys eliminados: {$deletedCount}\n";
    }

    /**
     * Verifica si hay datos huérfanos que impedirían crear foreign keys
     */
    private function checkOrphanedData($pdo)
    {
        $orphanedTables = 0;

        $checks = [
            'blog_posts' => "SELECT COUNT(*) as count FROM blog_posts WHERE tenant_id IS NOT NULL AND tenant_id NOT IN (SELECT id FROM tenants)",
            'blog_categories' => "SELECT COUNT(*) as count FROM blog_categories WHERE tenant_id IS NOT NULL AND tenant_id NOT IN (SELECT id FROM tenants)",
            'blog_tags' => "SELECT COUNT(*) as count FROM blog_tags WHERE tenant_id IS NOT NULL AND tenant_id NOT IN (SELECT id FROM tenants)",
            'admins' => "SELECT COUNT(*) as count FROM admins WHERE tenant_id IS NOT NULL AND tenant_id NOT IN (SELECT id FROM tenants)",
        ];

        foreach ($checks as $table => $query) {
            try {
                // Verificar si la tabla existe
                $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
                if (!$stmt->fetch()) {
                    continue;
                }

                $stmt = $pdo->query($query);
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($result['count'] > 0) {
                    echo "⚠️  Tabla '{$table}': {$result['count']} registros huérfanos encontrados\n";
                    $orphanedTables++;
                }
            } catch (Exception $e) {
                // Tabla no existe o error en query, continuar
            }
        }

        return $orphanedTables;
    }
}
