<?php

/**
 * Migración: Añadir columnas 2FA a tabla super_admins
 */

use Screenart\Musedock\Database\Migration;

return new class extends Migration
{
    /**
     * Ejecutar la migración
     */
    public function up(): void
    {
        $driver = $this->getDriverName();

        // Verificar si la columna ya existe
        if ($this->columnExists('super_admins', 'two_factor_enabled')) {
            echo "  → Columnas 2FA ya existen en super_admins, saltando...\n";
            return;
        }

        if ($driver === 'pgsql') {
            // PostgreSQL
            $this->execute("
                ALTER TABLE super_admins
                ADD COLUMN IF NOT EXISTS two_factor_enabled BOOLEAN DEFAULT FALSE,
                ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(64) NULL,
                ADD COLUMN IF NOT EXISTS two_factor_recovery_codes TEXT NULL,
                ADD COLUMN IF NOT EXISTS two_factor_enabled_at TIMESTAMP NULL,
                ADD COLUMN IF NOT EXISTS two_factor_last_used_at TIMESTAMP NULL
            ");
        } else {
            // MySQL / MariaDB
            $this->execute("
                ALTER TABLE super_admins
                ADD COLUMN two_factor_enabled TINYINT(1) DEFAULT 0,
                ADD COLUMN two_factor_secret VARCHAR(64) NULL,
                ADD COLUMN two_factor_recovery_codes TEXT NULL,
                ADD COLUMN two_factor_enabled_at DATETIME NULL,
                ADD COLUMN two_factor_last_used_at DATETIME NULL
            ");
        }

        // Crear índice para búsquedas rápidas
        $this->execute("CREATE INDEX idx_super_admins_2fa ON super_admins (two_factor_enabled)");

        echo "  ✓ Columnas 2FA añadidas a super_admins\n";
    }

    /**
     * Revertir la migración
     */
    public function down(): void
    {
        $columns = [
            'two_factor_enabled',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'two_factor_enabled_at',
            'two_factor_last_used_at'
        ];

        foreach ($columns as $column) {
            if ($this->columnExists('super_admins', $column)) {
                $this->execute("ALTER TABLE super_admins DROP COLUMN {$column}");
            }
        }

        // Eliminar índice
        try {
            $this->execute("DROP INDEX idx_super_admins_2fa ON super_admins");
        } catch (\Exception $e) {
            // Ignorar si el índice no existe
        }

        echo "  ✓ Columnas 2FA eliminadas de super_admins\n";
    }
};
