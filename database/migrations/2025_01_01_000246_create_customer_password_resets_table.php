<?php

use Screenart\Musedock\Database;

/**
 * Migración: Crear tabla customer_password_resets
 *
 * Almacena tokens de recuperación de contraseña para customers
 * - Tokens tienen expiración de 1 hora
 * - Se eliminan automáticamente después de ser usados
 */
class CreateCustomerPasswordResetsTable_2025_01_01_000246
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `customer_password_resets` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `customer_id` INT UNSIGNED NOT NULL,
                `token` VARCHAR(255) NOT NULL,
                `email` VARCHAR(255) NOT NULL,
                `expires_at` DATETIME NOT NULL,
                `used_at` DATETIME NULL DEFAULT NULL,
                `ip_address` VARCHAR(45) NULL,
                `user_agent` VARCHAR(500) NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_token` (`token`),
                INDEX `idx_email` (`email`),
                INDEX `idx_expires_at` (`expires_at`),
                FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        } else {
            // PostgreSQL
            $pdo->exec("CREATE TABLE IF NOT EXISTS customer_password_resets (
                id SERIAL PRIMARY KEY,
                customer_id INTEGER NOT NULL,
                token VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                used_at TIMESTAMP NULL DEFAULT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(500) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
            );
            CREATE INDEX IF NOT EXISTS idx_customer_password_resets_token ON customer_password_resets(token);
            CREATE INDEX IF NOT EXISTS idx_customer_password_resets_email ON customer_password_resets(email);
            CREATE INDEX IF NOT EXISTS idx_customer_password_resets_expires ON customer_password_resets(expires_at);");
        }

        echo "✅ Tabla customer_password_resets creada exitosamente\n";
    }

    public function down()
    {
        $pdo = Database::connect();
        $pdo->exec("DROP TABLE IF EXISTS customer_password_resets;");
        echo "✅ Tabla customer_password_resets eliminada\n";
    }
}
