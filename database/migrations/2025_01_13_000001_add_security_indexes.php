<?php

/**
 * Migración: Índices de seguridad y rendimiento
 * Fecha: 2025-01-13
 * Descripción: Agrega índices para mejorar rendimiento y seguridad
 */

use Screenart\Musedock\Database;

class AddSecurityIndexes_2025_01_13_000001
{
    private $indexes = [
        // Índices para blog_posts
        'blog_posts' => [
            'idx_blog_posts_tenant' => ['tenant_id'],
            'idx_blog_posts_status' => ['status'],
            'idx_blog_posts_published' => ['published_at'],
            'idx_blog_posts_slug' => ['slug'],
            'idx_blog_posts_tenant_status' => ['tenant_id', 'status'],
            'idx_blog_posts_deleted' => ['deleted_at'],
        ],

        // Índices para blog_categories
        'blog_categories' => [
            'idx_blog_categories_tenant' => ['tenant_id'],
            'idx_blog_categories_slug' => ['slug'],
            'idx_blog_categories_parent' => ['parent_id'],
        ],

        // Índices para blog_tags
        'blog_tags' => [
            'idx_blog_tags_tenant' => ['tenant_id'],
            'idx_blog_tags_slug' => ['slug'],
        ],

        // Índices para blog_post_categories
        'blog_post_categories' => [
            'idx_blog_post_categories_post' => ['post_id'],
            'idx_blog_post_categories_category' => ['category_id'],
        ],

        // Índices para blog_post_tags
        'blog_post_tags' => [
            'idx_blog_post_tags_post' => ['post_id'],
            'idx_blog_post_tags_tag' => ['tag_id'],
        ],

        // Índices para admins
        'admins' => [
            'idx_admins_tenant' => ['tenant_id'],
            'idx_admins_email' => ['email'],
        ],

        // Índices para users (si la tabla existe)
        'users' => [
            'idx_users_tenant' => ['tenant_id'],
            'idx_users_email' => ['email'],
        ],

        // Índices para slugs
        'slugs' => [
            'idx_slugs_tenant' => ['tenant_id'],
            'idx_slugs_module_reference' => ['module', 'reference_id'],
            'idx_slugs_slug' => ['slug'],
        ],

        // Índices para tenants
        'tenants' => [
            'idx_tenants_slug' => ['slug'],
            'idx_tenants_status' => ['status'],
        ],
    ];

    public function up()
    {
        $pdo = Database::connect();
        $createdCount = 0;
        $skippedCount = 0;

        foreach ($this->indexes as $table => $tableIndexes) {
            // Verificar si la tabla existe
            $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
            if (!$stmt->fetch()) {
                echo "⚠ Tabla '{$table}' no existe, omitiendo índices\n";
                $skippedCount += count($tableIndexes);
                continue;
            }

            foreach ($tableIndexes as $indexName => $columns) {
                try {
                    // Verificar si el índice ya existe
                    $stmt = $pdo->query("SHOW INDEX FROM {$table} WHERE Key_name = '{$indexName}'");
                    $indexExists = $stmt->fetch();

                    if (!$indexExists) {
                        // Verificar que las columnas existan
                        $columnsExist = true;
                        foreach ($columns as $column) {
                            $stmt = $pdo->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
                            if (!$stmt->fetch()) {
                                echo "⚠ Columna '{$column}' no existe en tabla '{$table}', omitiendo índice '{$indexName}'\n";
                                $columnsExist = false;
                                $skippedCount++;
                                break;
                            }
                        }

                        if ($columnsExist) {
                            $columnsList = implode(', ', $columns);
                            $pdo->exec("CREATE INDEX {$indexName} ON {$table}({$columnsList})");
                            echo "✓ Índice '{$indexName}' creado en tabla '{$table}'\n";
                            $createdCount++;
                        }
                    } else {
                        // echo "⚠ Índice '{$indexName}' ya existe en tabla '{$table}'\n";
                        $skippedCount++;
                    }
                } catch (Exception $e) {
                    echo "✗ Error creando índice '{$indexName}' en '{$table}': " . $e->getMessage() . "\n";
                }
            }
        }

        echo "\n";
        echo "════════════════════════════════════════════════════════════\n";
        echo "Resumen de índices:\n";
        echo "  • Creados: {$createdCount}\n";
        echo "  • Omitidos: {$skippedCount}\n";
        echo "════════════════════════════════════════════════════════════\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $deletedCount = 0;

        foreach ($this->indexes as $table => $tableIndexes) {
            // Verificar si la tabla existe
            $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
            if (!$stmt->fetch()) {
                continue;
            }

            foreach ($tableIndexes as $indexName => $columns) {
                try {
                    // Verificar si el índice existe
                    $stmt = $pdo->query("SHOW INDEX FROM {$table} WHERE Key_name = '{$indexName}'");
                    $indexExists = $stmt->fetch();

                    if ($indexExists) {
                        $pdo->exec("DROP INDEX {$indexName} ON {$table}");
                        echo "✓ Índice '{$indexName}' eliminado de tabla '{$table}'\n";
                        $deletedCount++;
                    }
                } catch (Exception $e) {
                    echo "✗ Error eliminando índice '{$indexName}' de '{$table}': " . $e->getMessage() . "\n";
                }
            }
        }

        echo "\n✓ Total de índices eliminados: {$deletedCount}\n";
    }
}
