<?php
/**
 * Migration: Add company_reg_number column to domain_contacts table
 *
 * This column stores the company registration number (CIF/NIF) which is
 * required for .ES domains when the registrant is a company.
 *
 * Compatible with both PostgreSQL and MySQL/MariaDB
 */

use Screenart\Musedock\Database;

// Get database connection
$pdo = Database::connect();

// Detect database driver
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

echo "Adding company_reg_number column to domain_contacts table...\n";

try {
    // Check if column already exists
    if ($driver === 'pgsql') {
        $stmt = $pdo->query("
            SELECT column_name
            FROM information_schema.columns
            WHERE table_name = 'domain_contacts'
            AND column_name = 'company_reg_number'
        ");
    } else {
        $stmt = $pdo->query("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'domain_contacts'
            AND COLUMN_NAME = 'company_reg_number'
        ");
    }

    if ($stmt->fetch()) {
        echo "✓ Column company_reg_number already exists in domain_contacts table\n";
    } else {
        // Add the column
        if ($driver === 'pgsql') {
            $pdo->exec("
                ALTER TABLE domain_contacts
                ADD COLUMN company_reg_number VARCHAR(50) NULL
            ");

            // Add comment
            $pdo->exec("
                COMMENT ON COLUMN domain_contacts.company_reg_number IS 'Company registration number (CIF/NIF) - required for .ES domains when registrant is a company'
            ");
        } else {
            // MySQL/MariaDB
            $pdo->exec("
                ALTER TABLE domain_contacts
                ADD COLUMN company_reg_number VARCHAR(50) NULL
                COMMENT 'Company registration number (CIF/NIF) - required for .ES domains when registrant is a company'
                AFTER company
            ");
        }

        echo "✓ Added company_reg_number column to domain_contacts table\n";
    }

} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    throw $e;
}
