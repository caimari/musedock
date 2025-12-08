<?php

/**
 * Migración: Añadir columna disk a tabla media_folders
 *
 * Permite tener carpetas independientes por disco de almacenamiento.
 * Cada disco (local, media, r2, s3) tendrá su propia estructura de carpetas.
 */

use Screenart\Musedock\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $driver = $this->getDriverName();

        // Verificar si la tabla existe
        if (!$this->tableExists('media_folders')) {
            echo "  → Tabla media_folders no existe, saltando...\n";
            return;
        }

        // Verificar si la columna ya existe
        if ($this->columnExists('media_folders', 'disk')) {
            echo "  → Columna disk ya existe en media_folders, saltando...\n";
            return;
        }

        // Añadir columna disk
        if ($driver === 'pgsql') {
            $this->execute("
                ALTER TABLE media_folders
                ADD COLUMN IF NOT EXISTS disk VARCHAR(50) DEFAULT 'media'
            ");
        } else {
            // MySQL / MariaDB
            $this->execute("
                ALTER TABLE media_folders
                ADD COLUMN disk VARCHAR(50) DEFAULT 'media' AFTER tenant_id
            ");
        }

        echo "  ✓ Columna disk añadida a media_folders\n";

        // Actualizar carpetas existentes: asignar 'media' a las que no tengan disco
        $this->execute("UPDATE media_folders SET disk = 'media' WHERE disk IS NULL");
        echo "  ✓ Carpetas existentes asignadas al disco 'media'\n";

        // Crear carpeta raíz para el disco 'local' si no existe
        // Primero verificamos si ya existe una raíz para 'local'
        $stmt = $this->query("SELECT COUNT(*) FROM media_folders WHERE disk = 'local' AND path = '/'");
        $count = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        if ($count === 0) {
            // Crear carpeta raíz para disco local
            $this->execute("
                INSERT INTO media_folders (tenant_id, parent_id, name, slug, path, disk, description, created_at, updated_at)
                VALUES (NULL, NULL, 'Root', 'root', '/', 'local', 'Carpeta raíz del disco local (legacy)', NOW(), NOW())
            ");
            echo "  ✓ Carpeta raíz creada para disco 'local'\n";
        }

        // Crear índice compuesto para búsquedas por disco y tenant
        try {
            $this->execute("CREATE INDEX idx_media_folders_disk ON media_folders (disk)");
            echo "  ✓ Índice idx_media_folders_disk creado\n";
        } catch (\Exception $e) {
            echo "  → Índice idx_media_folders_disk ya existe o no se pudo crear\n";
        }

        // Crear índice compuesto disk + tenant_id + parent_id para búsquedas jerárquicas
        try {
            $this->execute("CREATE INDEX idx_media_folders_disk_tenant ON media_folders (disk, tenant_id, parent_id)");
            echo "  ✓ Índice idx_media_folders_disk_tenant creado\n";
        } catch (\Exception $e) {
            echo "  → Índice idx_media_folders_disk_tenant ya existe o no se pudo crear\n";
        }
    }

    public function down(): void
    {
        if (!$this->tableExists('media_folders')) {
            return;
        }

        if ($this->columnExists('media_folders', 'disk')) {
            // Eliminar índices
            try {
                $this->execute("DROP INDEX idx_media_folders_disk ON media_folders");
            } catch (\Exception $e) {
                // Ignorar si no existe
            }

            try {
                $this->execute("DROP INDEX idx_media_folders_disk_tenant ON media_folders");
            } catch (\Exception $e) {
                // Ignorar si no existe
            }

            // Eliminar columna
            $this->execute("ALTER TABLE media_folders DROP COLUMN disk");
            echo "  ✓ Columna disk eliminada de media_folders\n";
        }
    }
};
