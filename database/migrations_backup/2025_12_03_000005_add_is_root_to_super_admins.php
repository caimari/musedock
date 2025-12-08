<?php

/**
 * Migración: Añadir columna is_root a tabla super_admins
 *
 * El super_admin con is_root=1 tiene acceso total (bypass de permisos)
 * Los demás super_admins respetan los permisos de sus roles asignados
 */

use Screenart\Musedock\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $driver = $this->getDriverName();

        // Verificar si la columna ya existe
        if ($this->columnExists('super_admins', 'is_root')) {
            echo "  → Columna is_root ya existe en super_admins, saltando...\n";
            return;
        }

        if ($driver === 'pgsql') {
            $this->execute("
                ALTER TABLE super_admins
                ADD COLUMN IF NOT EXISTS is_root BOOLEAN DEFAULT FALSE
            ");
        } else {
            // MySQL / MariaDB
            $this->execute("
                ALTER TABLE super_admins
                ADD COLUMN is_root TINYINT(1) DEFAULT 0 AFTER role
            ");
        }

        // Marcar el primer super_admin (ID más bajo) como root
        $this->execute("
            UPDATE super_admins
            SET is_root = 1
            WHERE id = (SELECT min_id FROM (SELECT MIN(id) as min_id FROM super_admins) as t)
        ");

        // Crear índice
        $this->execute("CREATE INDEX idx_super_admins_is_root ON super_admins (is_root)");

        echo "  ✓ Columna is_root añadida a super_admins\n";
        echo "  ✓ Primer super_admin marcado como root\n";
    }

    public function down(): void
    {
        if ($this->columnExists('super_admins', 'is_root')) {
            // Eliminar índice
            try {
                $this->execute("DROP INDEX idx_super_admins_is_root ON super_admins");
            } catch (\Exception $e) {
                // Ignorar si no existe
            }

            $this->execute("ALTER TABLE super_admins DROP COLUMN is_root");
            echo "  ✓ Columna is_root eliminada de super_admins\n";
        }
    }
};
