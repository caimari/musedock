<?php

use Screenart\Musedock\Database;

class AddAvatarToSuperAdminsTable_2025_11_12_140000
{
    public function up()
    {
        $pdo = Database::connect();

        // Añadir columna avatar a la tabla super_admins
        $pdo->exec("
            ALTER TABLE super_admins
            ADD COLUMN avatar VARCHAR(255) NULL AFTER password
        ");

        echo "✓ Columna 'avatar' añadida a la tabla super_admins\n";
    }

    public function down()
    {
        $pdo = Database::connect();

        $pdo->exec("
            ALTER TABLE super_admins
            DROP COLUMN avatar
        ");

        echo "✓ Columna 'avatar' eliminada de la tabla super_admins\n";
    }
}
