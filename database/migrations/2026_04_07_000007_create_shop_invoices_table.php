<?php
/**
 * Migration: Create shop_invoices table
 * Compatible with: MySQL/MariaDB + PostgreSQL
 */

use Screenart\Musedock\Database;

class CreateShopInvoicesTable_2026_04_07_000007
{
    public function up()
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `shop_invoices` (
                    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `tenant_id` int(11) DEFAULT NULL,
                    `order_id` int(10) unsigned DEFAULT NULL,
                    `customer_id` int(10) unsigned NOT NULL,
                    `subscription_id` int(10) unsigned DEFAULT NULL,
                    `invoice_number` varchar(30) NOT NULL,
                    `stripe_invoice_id` varchar(255) DEFAULT NULL,
                    `subtotal` int(11) NOT NULL DEFAULT 0,
                    `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
                    `tax_amount` int(11) NOT NULL DEFAULT 0,
                    `total` int(11) NOT NULL DEFAULT 0,
                    `currency` varchar(3) NOT NULL DEFAULT 'eur',
                    `status` varchar(20) NOT NULL DEFAULT 'draft',
                    `issued_at` timestamp DEFAULT NULL,
                    `paid_at` timestamp DEFAULT NULL,
                    `due_at` timestamp DEFAULT NULL,
                    `pdf_url` varchar(500) DEFAULT NULL,
                    `factubase_id` varchar(100) DEFAULT NULL COMMENT 'For VeriFactu integration',
                    `metadata` text DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_invoice_number` (`tenant_id`, `invoice_number`),
                    KEY `idx_shop_invoices_tenant` (`tenant_id`),
                    KEY `idx_shop_invoices_order` (`order_id`),
                    KEY `idx_shop_invoices_customer` (`customer_id`),
                    KEY `idx_shop_invoices_status` (`status`),
                    KEY `idx_shop_invoices_stripe` (`stripe_invoice_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS shop_invoices (
                    id SERIAL PRIMARY KEY,
                    tenant_id INTEGER DEFAULT NULL,
                    order_id INTEGER DEFAULT NULL,
                    customer_id INTEGER NOT NULL,
                    subscription_id INTEGER DEFAULT NULL,
                    invoice_number VARCHAR(30) NOT NULL,
                    stripe_invoice_id VARCHAR(255) DEFAULT NULL,
                    subtotal INTEGER NOT NULL DEFAULT 0,
                    tax_rate NUMERIC(5,2) NOT NULL DEFAULT 0.00,
                    tax_amount INTEGER NOT NULL DEFAULT 0,
                    total INTEGER NOT NULL DEFAULT 0,
                    currency VARCHAR(3) NOT NULL DEFAULT 'eur',
                    status VARCHAR(20) NOT NULL DEFAULT 'draft',
                    issued_at TIMESTAMP DEFAULT NULL,
                    paid_at TIMESTAMP DEFAULT NULL,
                    due_at TIMESTAMP DEFAULT NULL,
                    pdf_url VARCHAR(500) DEFAULT NULL,
                    factubase_id VARCHAR(100) DEFAULT NULL,
                    metadata TEXT DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT NULL
                )
            ");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS unique_invoice_number ON shop_invoices (tenant_id, invoice_number)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shop_invoices_tenant ON shop_invoices (tenant_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shop_invoices_order ON shop_invoices (order_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shop_invoices_customer ON shop_invoices (customer_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shop_invoices_status ON shop_invoices (status)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shop_invoices_stripe ON shop_invoices (stripe_invoice_id)");
        }
    }

    public function down()
    {
        $pdo = Database::connect();
        $pdo->exec("DROP TABLE IF EXISTS shop_invoices");
    }
}
