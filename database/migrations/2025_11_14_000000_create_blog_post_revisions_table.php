<?php

/**
 * Migración: Crear tabla blog_post_revisions
 * Fecha: 2025-11-14
 * Descripción: Sistema de versiones/historial para blog posts
 * Permite: historial completo, comparar versiones, revertir cambios, auditoría
 *
 * BENEFICIOS vs Soft Delete:
 * ✅ Recuperar contenido eliminado
 * ✅ Historial completo de cambios
 * ✅ Ver quién editó qué y cuándo
 * ✅ Comparar versiones (diff)
 * ✅ Revertir a cualquier versión anterior
 * ✅ Auditoría completa
 */

use Screenart\Musedock\Database;

class CreateBlogPostRevisionsTable_2025_11_14_000000
{
    public function up()
    {
        $pdo = Database::connect();

        echo "════════════════════════════════════════════════════════════\n";
        echo " MIGRACIÓN: Sistema de Versiones - Blog Post Revisions\n";
        echo "════════════════════════════════════════════════════════════\n\n";

        try {
            // Verificar si la tabla ya existe
            $stmt = $pdo->query("SHOW TABLES LIKE 'blog_post_revisions'");
            $exists = $stmt->fetch();

            if ($exists) {
                echo "⚠ Tabla 'blog_post_revisions' ya existe\n";
            } else {
                // Crear tabla de revisiones
                $pdo->exec("
                    CREATE TABLE blog_post_revisions (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        post_id INT NOT NULL,
                        tenant_id INT NULL,

                        -- Usuario que hizo el cambio
                        user_id INT NULL,
                        user_name VARCHAR(100) NULL COMMENT 'Cache del nombre para auditoría',
                        user_type ENUM('user', 'admin', 'superadmin') DEFAULT 'admin',

                        -- Tipo de revisión
                        revision_type ENUM('autosave', 'manual', 'published', 'restored', 'scheduled', 'initial') NOT NULL DEFAULT 'manual',

                        -- Contenido completo en el momento de la revisión
                        title VARCHAR(255) NOT NULL,
                        slug VARCHAR(255) NULL,
                        content LONGTEXT NOT NULL,
                        excerpt TEXT NULL,
                        featured_image VARCHAR(255) NULL,

                        -- Metadata que puede cambiar (JSON)
                        meta_data JSON NULL COMMENT 'SEO, visibilidad, custom fields, etc.',

                        -- Status en ese momento
                        status ENUM('draft', 'published', 'scheduled', 'trash') NULL,

                        -- Información de auditoría
                        created_at DATETIME NOT NULL,
                        ip_address VARCHAR(45) NULL,
                        user_agent TEXT NULL,

                        -- Resumen de cambios
                        changes_summary VARCHAR(255) NULL COMMENT 'Descripción breve de los cambios',

                        -- Índices para optimización
                        INDEX idx_post_id (post_id),
                        INDEX idx_tenant_id (tenant_id),
                        INDEX idx_created_at (created_at),
                        INDEX idx_revision_type (revision_type),
                        INDEX idx_user_id (user_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Historial completo de revisiones de blog posts'
                ");

                echo "✓ Tabla 'blog_post_revisions' creada exitosamente\n";
            }

            // Agregar columna revision_count a blog_posts para optimización
            $stmt = $pdo->query("SHOW COLUMNS FROM blog_posts LIKE 'revision_count'");
            $columnExists = $stmt->fetch();

            if (!$columnExists) {
                $pdo->exec("
                    ALTER TABLE blog_posts
                    ADD COLUMN revision_count INT DEFAULT 0
                    COMMENT 'Contador de revisiones (cache)'
                ");
                echo "✓ Columna 'revision_count' agregada a 'blog_posts'\n";
            } else {
                echo "⚠ Columna 'revision_count' ya existe en 'blog_posts'\n";
            }

            echo "\n";
            echo "════════════════════════════════════════════════════════════\n";
            echo " ✓ Migración completada exitosamente\n";
            echo "════════════════════════════════════════════════════════════\n\n";

        } catch (Exception $e) {
            echo "\n";
            echo "✗ Error al crear tabla blog_post_revisions: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    public function down()
    {
        $pdo = Database::connect();

        echo "════════════════════════════════════════════════════════════\n";
        echo " ROLLBACK: Sistema de Versiones - Blog Post Revisions\n";
        echo "════════════════════════════════════════════════════════════\n\n";

        try {
            // Eliminar tabla
            $stmt = $pdo->query("SHOW TABLES LIKE 'blog_post_revisions'");
            $exists = $stmt->fetch();

            if ($exists) {
                $pdo->exec("DROP TABLE blog_post_revisions");
                echo "✓ Tabla 'blog_post_revisions' eliminada\n";
            } else {
                echo "⚠ Tabla 'blog_post_revisions' no existe\n";
            }

            // Eliminar columna revision_count
            $stmt = $pdo->query("SHOW COLUMNS FROM blog_posts LIKE 'revision_count'");
            $columnExists = $stmt->fetch();

            if ($columnExists) {
                $pdo->exec("ALTER TABLE blog_posts DROP COLUMN revision_count");
                echo "✓ Columna 'revision_count' eliminada de 'blog_posts'\n";
            } else {
                echo "⚠ Columna 'revision_count' no existe en 'blog_posts'\n";
            }

            echo "\n";
            echo "════════════════════════════════════════════════════════════\n";
            echo " ✓ Rollback completado exitosamente\n";
            echo "════════════════════════════════════════════════════════════\n\n";

        } catch (Exception $e) {
            echo "\n";
            echo "✗ Error al revertir migración: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
}
