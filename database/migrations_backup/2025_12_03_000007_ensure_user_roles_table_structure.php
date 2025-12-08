<?php

/**
 * Migración: Asegurar estructura de tabla user_roles
 *
 * Esta migración verifica y actualiza la tabla user_roles para:
 * 1. Crear la tabla si no existe
 * 2. Agregar columna user_type para distinguir super_admin, admin, user
 * 3. Permitir asignar roles a super_admins con is_root=0
 */

use Screenart\Musedock\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        echo "════════════════════════════════════════════════════════════\n";
        echo " MIGRACIÓN: Estructura tabla user_roles\n";
        echo "════════════════════════════════════════════════════════════\n\n";

        $driver = $this->getDriverName();

        // Verificar si la tabla user_roles existe usando el método de la clase base
        if (!$this->tableExists('user_roles')) {
            echo "Creando tabla user_roles...\n";

            if ($driver === 'pgsql') {
                $this->execute("
                    CREATE TABLE IF NOT EXISTS user_roles (
                        id SERIAL PRIMARY KEY,
                        user_id INT NOT NULL,
                        user_type VARCHAR(50) NOT NULL DEFAULT 'user',
                        role_id INT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE (user_id, user_type, role_id)
                    )
                ");
                $this->execute("CREATE INDEX IF NOT EXISTS idx_user_roles_user ON user_roles (user_id, user_type)");
                $this->execute("CREATE INDEX IF NOT EXISTS idx_user_roles_role ON user_roles (role_id)");
            } else {
                $this->execute("
                    CREATE TABLE IF NOT EXISTS user_roles (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        user_id INT NOT NULL,
                        user_type VARCHAR(50) NOT NULL DEFAULT 'user' COMMENT 'Tipo: super_admin, admin, user',
                        role_id INT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY unique_user_role (user_id, user_type, role_id),
                        INDEX idx_user_id_type (user_id, user_type),
                        INDEX idx_role_id (role_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            }

            echo "  ✓ Tabla user_roles creada\n\n";
        } else {
            echo "Tabla user_roles ya existe, verificando columna user_type...\n";

            // Verificar si existe columna user_type
            if (!$this->columnExists('user_roles', 'user_type')) {
                echo "  → Agregando columna user_type...\n";

                if ($driver === 'pgsql') {
                    $this->execute("
                        ALTER TABLE user_roles
                        ADD COLUMN IF NOT EXISTS user_type VARCHAR(50) NOT NULL DEFAULT 'user'
                    ");
                } else {
                    $this->execute("
                        ALTER TABLE user_roles
                        ADD COLUMN user_type VARCHAR(50) NOT NULL DEFAULT 'user'
                        COMMENT 'Tipo: super_admin, admin, user'
                        AFTER user_id
                    ");
                }

                echo "  ✓ Columna user_type agregada\n";
            } else {
                echo "  ✓ Columna user_type ya existe\n";
            }
        }

        // Verificar si existe tabla role_permissions
        if (!$this->tableExists('role_permissions')) {
            echo "Creando tabla role_permissions...\n";

            if ($driver === 'pgsql') {
                $this->execute("
                    CREATE TABLE IF NOT EXISTS role_permissions (
                        id SERIAL PRIMARY KEY,
                        role_id INT NOT NULL,
                        permission_id INT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE (role_id, permission_id)
                    )
                ");
            } else {
                $this->execute("
                    CREATE TABLE IF NOT EXISTS role_permissions (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        role_id INT NOT NULL,
                        permission_id INT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY unique_role_permission (role_id, permission_id),
                        INDEX idx_role_id (role_id),
                        INDEX idx_permission_id (permission_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            }

            echo "  ✓ Tabla role_permissions creada\n";
        } else {
            echo "  ✓ Tabla role_permissions ya existe\n";
        }

        echo "\n════════════════════════════════════════════════════════════\n";
        echo " ✓ Migración completada\n";
        echo "════════════════════════════════════════════════════════════\n\n";
        echo "NOTA: Para asignar roles a un super_admin sin is_root:\n";
        echo "  INSERT INTO user_roles (user_id, user_type, role_id)\n";
        echo "  VALUES (<super_admin_id>, 'super_admin', <role_id>)\n";
    }

    public function down(): void
    {
        // No eliminamos la tabla, solo quitamos la columna user_type si fue agregada
        if ($this->columnExists('user_roles', 'user_type')) {
            $this->execute("ALTER TABLE user_roles DROP COLUMN user_type");
            echo "✓ Columna user_type eliminada de user_roles\n";
        }
    }
};
