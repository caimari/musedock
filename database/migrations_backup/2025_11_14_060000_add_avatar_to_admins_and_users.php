<?php

/**
 * Migración: Agregar columna avatar a tablas admins y users
 * Fecha: 2025-11-14
 *
 * Agrega la columna 'avatar' a las tablas admins y users
 * para permitir que todos los tipos de usuarios tengan avatar.
 */

use Screenart\Musedock\Database;

class AddAvatarToAdminsAndUsers_2025_11_14_060000
{
    public function up()
    {
        $pdo = Database::connect();

        echo "════════════════════════════════════════════════════════════\n";
        echo " MIGRACIÓN: Agregar Avatar a Admins y Users\n";
        echo "════════════════════════════════════════════════════════════\n\n";

        try {
            // Verificar si la columna ya existe en admins
            $stmt = $pdo->query("SHOW COLUMNS FROM admins LIKE 'avatar'");
            $adminHasAvatar = $stmt->rowCount() > 0;

            if (!$adminHasAvatar) {
                echo "→ Agregando columna 'avatar' a tabla 'admins'...\n";
                $pdo->exec("
                    ALTER TABLE admins
                    ADD COLUMN avatar VARCHAR(255) NULL DEFAULT NULL
                    AFTER email
                ");
                echo "  ✓ Columna 'avatar' agregada a 'admins'\n\n";
            } else {
                echo "  ℹ Columna 'avatar' ya existe en tabla 'admins'\n\n";
            }

            // Verificar si la columna ya existe en users
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'avatar'");
            $userHasAvatar = $stmt->rowCount() > 0;

            if (!$userHasAvatar) {
                echo "→ Agregando columna 'avatar' a tabla 'users'...\n";
                $pdo->exec("
                    ALTER TABLE users
                    ADD COLUMN avatar VARCHAR(255) NULL DEFAULT NULL
                    AFTER email
                ");
                echo "  ✓ Columna 'avatar' agregada a 'users'\n\n";
            } else {
                echo "  ℹ Columna 'avatar' ya existe en tabla 'users'\n\n";
            }

            echo "════════════════════════════════════════════════════════════\n";
            echo " ✓ MIGRACIÓN COMPLETADA EXITOSAMENTE\n";
            echo "════════════════════════════════════════════════════════════\n";

        } catch (\PDOException $e) {
            echo "\n✗ Error en la migración: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    public function down()
    {
        $pdo = Database::connect();

        echo "════════════════════════════════════════════════════════════\n";
        echo " REVERTIR: Eliminar Avatar de Admins y Users\n";
        echo "════════════════════════════════════════════════════════════\n\n";

        try {
            // Eliminar columna de admins
            $stmt = $pdo->query("SHOW COLUMNS FROM admins LIKE 'avatar'");
            if ($stmt->rowCount() > 0) {
                echo "→ Eliminando columna 'avatar' de tabla 'admins'...\n";
                $pdo->exec("ALTER TABLE admins DROP COLUMN avatar");
                echo "  ✓ Columna 'avatar' eliminada de 'admins'\n\n";
            }

            // Eliminar columna de users
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'avatar'");
            if ($stmt->rowCount() > 0) {
                echo "→ Eliminando columna 'avatar' de tabla 'users'...\n";
                $pdo->exec("ALTER TABLE users DROP COLUMN avatar");
                echo "  ✓ Columna 'avatar' eliminada de 'users'\n\n";
            }

            echo "════════════════════════════════════════════════════════════\n";
            echo " ✓ REVERSIÓN COMPLETADA EXITOSAMENTE\n";
            echo "════════════════════════════════════════════════════════════\n";

        } catch (\PDOException $e) {
            echo "\n✗ Error al revertir: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
}
