<?php

/**
 * Migración: Añadir columnas para Two-Factor Authentication (2FA)
 *
 * Esta migración añade soporte para autenticación de dos factores
 * usando TOTP (compatible con Google Authenticator, Authy, etc.)
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

        // Añadir columnas a tabla users
        $this->addTwoFactorColumns('users', $driver);

        // Añadir columnas a tabla admins (administradores de tenant)
        $this->addTwoFactorColumns('admins', $driver);

        echo "  ✓ Columnas 2FA añadidas a users y admins\n";
    }

    /**
     * Revertir la migración
     */
    public function down(): void
    {
        $this->removeTwoFactorColumns('users');
        $this->removeTwoFactorColumns('admins');

        echo "  ✓ Columnas 2FA eliminadas de users y admins\n";
    }

    /**
     * Añadir columnas de 2FA a una tabla
     */
    private function addTwoFactorColumns(string $table, string $driver): void
    {
        // Verificar si la columna ya existe
        if ($this->columnExists($table, 'two_factor_enabled')) {
            echo "  → Columnas 2FA ya existen en {$table}, saltando...\n";
            return;
        }

        if ($driver === 'pgsql') {
            // PostgreSQL
            $this->execute("
                ALTER TABLE {$table}
                ADD COLUMN IF NOT EXISTS two_factor_enabled BOOLEAN DEFAULT FALSE,
                ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(64) NULL,
                ADD COLUMN IF NOT EXISTS two_factor_recovery_codes TEXT NULL,
                ADD COLUMN IF NOT EXISTS two_factor_enabled_at TIMESTAMP NULL,
                ADD COLUMN IF NOT EXISTS two_factor_last_used_at TIMESTAMP NULL
            ");
        } else {
            // MySQL / MariaDB
            $this->execute("
                ALTER TABLE {$table}
                ADD COLUMN two_factor_enabled TINYINT(1) DEFAULT 0,
                ADD COLUMN two_factor_secret VARCHAR(64) NULL,
                ADD COLUMN two_factor_recovery_codes TEXT NULL,
                ADD COLUMN two_factor_enabled_at DATETIME NULL,
                ADD COLUMN two_factor_last_used_at DATETIME NULL
            ");
        }

        // Crear índice para búsquedas rápidas
        $this->execute("CREATE INDEX idx_{$table}_2fa ON {$table} (two_factor_enabled)");
    }

    /**
     * Eliminar columnas de 2FA
     */
    private function removeTwoFactorColumns(string $table): void
    {
        $columns = [
            'two_factor_enabled',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'two_factor_enabled_at',
            'two_factor_last_used_at'
        ];

        foreach ($columns as $column) {
            if ($this->columnExists($table, $column)) {
                $this->execute("ALTER TABLE {$table} DROP COLUMN {$column}");
            }
        }

        // Eliminar índice
        try {
            $this->execute("DROP INDEX idx_{$table}_2fa ON {$table}");
        } catch (\Exception $e) {
            // Ignorar si el índice no existe
        }
    }
};
