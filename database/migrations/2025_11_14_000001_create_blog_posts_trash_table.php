<?php

/**
 * Migración: Crear tabla blog_posts_trash
 * Fecha: 2025-11-14
 * Descripción: Papelera temporal para blog posts eliminados
 * Permite: recuperar posts eliminados, auto-limpieza después de 30 días
 */

use Screenart\Musedock\Database;

class CreateBlogPostsTrashTable_2025_11_14_000001
{
    public function up()
    {
        $pdo = Database::connect();

        echo "════════════════════════════════════════════════════════════\n";
        echo " MIGRACIÓN: Sistema de Versiones - Blog Posts Trash\n";
        echo "════════════════════════════════════════════════════════════\n\n";

        try {
            // Verificar si la tabla ya existe
            $stmt = $pdo->query("SHOW TABLES LIKE 'blog_posts_trash'");
            $exists = $stmt->fetch();

            if ($exists) {
                echo "⚠ Tabla 'blog_posts_trash' ya existe\n";
            } else {

            // Crear tabla de papelera
            $pdo->exec("
                CREATE TABLE blog_posts_trash (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    post_id INT NOT NULL,
                    tenant_id INT NULL,

                    -- Usuario que eliminó
                    deleted_by INT NOT NULL,
                    deleted_by_name VARCHAR(100) NULL COMMENT 'Cache del nombre',
                    deleted_by_type ENUM('user', 'admin', 'superadmin') DEFAULT 'admin',

                    -- Timestamps
                    deleted_at DATETIME NOT NULL,
                    scheduled_permanent_delete DATETIME NULL COMMENT 'Auto-eliminar después de 30 días',

                    -- Metadata adicional
                    reason VARCHAR(255) NULL COMMENT 'Razón de eliminación (opcional)',
                    ip_address VARCHAR(45) NULL,

                    -- Índices
                    INDEX idx_post_id (post_id),
                    INDEX idx_tenant_id (tenant_id),
                    INDEX idx_deleted_at (deleted_at),
                    INDEX idx_scheduled_delete (scheduled_permanent_delete),
                    INDEX idx_deleted_by (deleted_by)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Papelera temporal de blog posts (recuperable por 30 días)'
            ");

            echo "✓ Tabla 'blog_posts_trash' creada exitosamente\n";
            }

        } catch (Exception $e) {
            echo "✗ Error al crear tabla blog_posts_trash: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    public function down()
    {
        $pdo = Database::connect();

        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'blog_posts_trash'");
            $exists = $stmt->fetch();

            if ($exists) {
                $pdo->exec("DROP TABLE blog_posts_trash");
                echo "✓ Tabla 'blog_posts_trash' eliminada\n";
            } else {
                echo "⚠ Tabla 'blog_posts_trash' no existe\n";
            }

        } catch (Exception $e) {
            echo "✗ Error al revertir migración: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
}
