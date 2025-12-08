<?php

/**
 * Migración: Agregar columna avatar a tabla admins
 * Fecha: 2025-11-13
 * Descripción: Añade soporte para avatares de usuarios admin (tenant)
 */

use Screenart\Musedock\Database;

class AddAvatarToAdminsTable_2025_11_13_010000
{
    public function up()
    {
        $pdo = Database::connect();

        try {
            // Verificar si la columna ya existe
            $stmt = $pdo->query("SHOW COLUMNS FROM admins LIKE 'avatar'");
            $exists = $stmt->fetch();

            if (!$exists) {
                $pdo->exec("
                    ALTER TABLE admins
                    ADD COLUMN avatar VARCHAR(255) NULL
                    AFTER name
                ");
                echo "✓ Columna 'avatar' agregada a tabla 'admins'\n";
            } else {
                echo "⚠ Columna 'avatar' ya existe en tabla 'admins'\n";
            }
        } catch (Exception $e) {
            echo "✗ Error al agregar columna avatar: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    public function down()
    {
        $pdo = Database::connect();

        try {
            // Verificar si la columna existe antes de eliminarla
            $stmt = $pdo->query("SHOW COLUMNS FROM admins LIKE 'avatar'");
            $exists = $stmt->fetch();

            if ($exists) {
                $pdo->exec("ALTER TABLE admins DROP COLUMN avatar");
                echo "✓ Columna 'avatar' eliminada de tabla 'admins'\n";
            } else {
                echo "⚠ Columna 'avatar' no existe en tabla 'admins'\n";
            }
        } catch (Exception $e) {
            echo "✗ Error al eliminar columna avatar: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
}
