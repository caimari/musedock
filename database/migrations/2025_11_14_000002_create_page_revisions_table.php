<?php

/**
 * Migración: Crear tabla page_revisions
 * Fecha: 2025-11-14
 * Descripción: Sistema de versiones/historial para páginas
 * Permite: historial completo, comparar versiones, revertir cambios, auditoría
 */

use Screenart\Musedock\Database;

class CreatePageRevisionsTable_2025_11_14_000002
{
    public function up()
    {
        $pdo = Database::connect();

        try {
            // Verificar si la tabla ya existe
            $stmt = $pdo->query("SHOW TABLES LIKE 'page_revisions'");
            $exists = $stmt->fetch();

            if ($exists) {
                echo "⚠ Tabla 'page_revisions' ya existe\n";
                return;
            }

            // Crear tabla de revisiones de páginas
            $pdo->exec("
                CREATE TABLE page_revisions (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    page_id INT NOT NULL,
                    tenant_id INT NULL COMMENT 'NULL para páginas de superadmin',

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
                    slider_image VARCHAR(255) NULL,

                    -- Metadata que puede cambiar (JSON)
                    meta_data JSON NULL COMMENT 'SEO, visibilidad, layout, custom fields, etc.',

                    -- Status en ese momento
                    status ENUM('draft', 'published', 'scheduled', 'trash') NULL,

                    -- Información de auditoría
                    created_at DATETIME NOT NULL,
                    ip_address VARCHAR(45) NULL,
                    user_agent TEXT NULL,

                    -- Resumen de cambios
                    changes_summary VARCHAR(255) NULL COMMENT 'Descripción breve de los cambios',

                    -- Índices para optimización
                    INDEX idx_page_id (page_id),
                    INDEX idx_tenant_id (tenant_id),
                    INDEX idx_created_at (created_at),
                    INDEX idx_revision_type (revision_type),
                    INDEX idx_user_id (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Historial completo de revisiones de páginas'
            ");

            echo "✓ Tabla 'page_revisions' creada exitosamente\n";

            // Agregar columna revision_count a pages para optimización
            $stmt = $pdo->query("SHOW COLUMNS FROM pages LIKE 'revision_count'");
            $columnExists = $stmt->fetch();

            if (!$columnExists) {
                $pdo->exec("
                    ALTER TABLE pages
                    ADD COLUMN revision_count INT DEFAULT 0
                    COMMENT 'Contador de revisiones (cache)'
                ");
                echo "✓ Columna 'revision_count' agregada a 'pages'\n";
            }

        } catch (Exception $e) {
            echo "✗ Error al crear tabla page_revisions: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    public function down()
    {
        $pdo = Database::connect();

        try {
            // Eliminar tabla
            $stmt = $pdo->query("SHOW TABLES LIKE 'page_revisions'");
            $exists = $stmt->fetch();

            if ($exists) {
                $pdo->exec("DROP TABLE page_revisions");
                echo "✓ Tabla 'page_revisions' eliminada\n";
            } else {
                echo "⚠ Tabla 'page_revisions' no existe\n";
            }

            // Eliminar columna revision_count
            $stmt = $pdo->query("SHOW COLUMNS FROM pages LIKE 'revision_count'");
            $columnExists = $stmt->fetch();

            if ($columnExists) {
                $pdo->exec("ALTER TABLE pages DROP COLUMN revision_count");
                echo "✓ Columna 'revision_count' eliminada de 'pages'\n";
            }

        } catch (Exception $e) {
            echo "✗ Error al revertir migración: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
}
