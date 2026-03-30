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

class AddCompanyRegNumberToDomainContacts_2025_12_22_100000
{
    public function up(): void
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $isPostgres = ($driver === 'pgsql');

        echo "Adding company_reg_number column to domain_contacts table...\n";

        // Check if column already exists
        if ($isPostgres) {
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

        if ($stmt && $stmt->fetch()) {
            echo "✓ Column company_reg_number already exists in domain_contacts table\n";
            return;
        }

        if ($isPostgres) {
            $pdo->exec("
                ALTER TABLE domain_contacts
                ADD COLUMN company_reg_number VARCHAR(50) NULL
            ");

            $pdo->exec("
                COMMENT ON COLUMN domain_contacts.company_reg_number IS
                'Company registration number (CIF/NIF) - required for .ES domains when registrant is a company'
            ");
        } else {
            $pdo->exec("
                ALTER TABLE domain_contacts
                ADD COLUMN company_reg_number VARCHAR(50) NULL
                COMMENT 'Company registration number (CIF/NIF) - required for .ES domains when registrant is a company'
                AFTER company
            ");
        }

        echo "✓ Added company_reg_number column to domain_contacts table\n";
    }

    public function down(): void
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $isPostgres = ($driver === 'pgsql');

        if ($isPostgres) {
            $pdo->exec("ALTER TABLE domain_contacts DROP COLUMN IF EXISTS company_reg_number");
        } else {
            try {
                $pdo->exec("ALTER TABLE domain_contacts DROP COLUMN company_reg_number");
            } catch (\PDOException $e) {
                // Column may not exist; ignore.
            }
        }

        echo "✓ domain_contacts.company_reg_number removed\n";
    }
}

