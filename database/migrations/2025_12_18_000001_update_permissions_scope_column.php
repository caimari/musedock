<?php

/**
 * Migración: Actualizar columna scope en permissions
 *
 * Cambia los valores de scope de 'global'/'tenant' a 'superadmin'/'tenant'
 * para mejor claridad sobre qué permisos son del panel superadmin vs tenant.
 *
 * Compatible con MySQL y PostgreSQL
 */
class UpdatePermissionsScopeColumn
{
    public function up(): void
    {
        $pdo = \Screenart\Musedock\Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            // MySQL: Modificar el ENUM para incluir 'superadmin'
            $pdo->exec("ALTER TABLE permissions MODIFY COLUMN scope ENUM('superadmin', 'tenant') DEFAULT 'tenant'");

            // Actualizar valores existentes: 'global' ya no existe, los NULL se convierten en 'tenant'
            $pdo->exec("UPDATE permissions SET scope = 'tenant' WHERE scope IS NULL");
        } else {
            // PostgreSQL: Usar VARCHAR en lugar de ENUM para flexibilidad
            // Primero verificar si la columna ya existe
            $stmt = $pdo->query("
                SELECT column_name
                FROM information_schema.columns
                WHERE table_name = 'permissions' AND column_name = 'scope'
            ");

            if ($stmt->fetch()) {
                // La columna existe, actualizarla
                $pdo->exec("ALTER TABLE permissions ALTER COLUMN scope TYPE VARCHAR(20)");
                $pdo->exec("ALTER TABLE permissions ALTER COLUMN scope SET DEFAULT 'tenant'");
            } else {
                // La columna no existe, crearla
                $pdo->exec("ALTER TABLE permissions ADD COLUMN scope VARCHAR(20) DEFAULT 'tenant'");
            }

            // Actualizar valores existentes
            $pdo->exec("UPDATE permissions SET scope = 'tenant' WHERE scope IS NULL OR scope = ''");
            $pdo->exec("UPDATE permissions SET scope = 'superadmin' WHERE scope = 'global'");
        }

        echo "   ✓ Columna scope actualizada para soportar 'superadmin'/'tenant'\n";
    }

    public function down(): void
    {
        $pdo = \Screenart\Musedock\Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            // Revertir: volver a 'global'/'tenant'
            $pdo->exec("UPDATE permissions SET scope = 'global' WHERE scope = 'superadmin'");
            $pdo->exec("ALTER TABLE permissions MODIFY COLUMN scope ENUM('global', 'tenant') DEFAULT 'tenant'");
        } else {
            $pdo->exec("UPDATE permissions SET scope = 'global' WHERE scope = 'superadmin'");
        }

        echo "   ✓ Columna scope revertida a 'global'/'tenant'\n";
    }
}
