<?php

/**
 * Migration: Create Custom Forms Tables
 *
 * Creates the necessary tables for the custom forms module:
 * - custom_forms: Main forms storage
 * - custom_form_fields: Fields/elements within forms
 * - custom_form_submissions: Form submissions data
 * - custom_form_settings: Global settings for the module
 *
 * Compatible with: MySQL/MariaDB + PostgreSQL
 */
class CreateCustomFormsTables_2025_12_01_000000
{
    public function up()
    {
        $pdo = \Screenart\Musedock\Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $this->upMySQL($pdo);
        } else {
            $this->upPostgreSQL($pdo);
        }

        error_log("CustomForms: Tables created successfully");
    }

    private function upMySQL($pdo)
    {
        // Tabla principal de formularios
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `custom_forms` (
                `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id` int(11) UNSIGNED DEFAULT NULL COMMENT 'NULL = formulario global',
                `name` varchar(255) NOT NULL,
                `slug` varchar(255) NOT NULL,
                `description` text,
                `submit_button_text` varchar(100) DEFAULT 'Enviar',
                `success_message` text,
                `error_message` text,
                `redirect_url` varchar(500) DEFAULT NULL COMMENT 'URL de redirección tras envío',
                `email_to` varchar(500) DEFAULT NULL COMMENT 'Emails separados por coma',
                `email_subject` varchar(255) DEFAULT NULL,
                `email_from_name` varchar(100) DEFAULT NULL,
                `email_from_email` varchar(255) DEFAULT NULL,
                `email_reply_to` varchar(255) DEFAULT NULL,
                `send_confirmation_email` tinyint(1) DEFAULT 0,
                `confirmation_email_subject` varchar(255) DEFAULT NULL,
                `confirmation_email_message` text,
                `store_submissions` tinyint(1) DEFAULT 1,
                `enable_recaptcha` tinyint(1) DEFAULT 0,
                `form_class` varchar(255) DEFAULT NULL COMMENT 'Clases CSS adicionales',
                `settings` json DEFAULT NULL COMMENT 'Configuración adicional JSON',
                `is_active` tinyint(1) DEFAULT 1,
                `submissions_count` int(11) UNSIGNED DEFAULT 0,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_slug_tenant` (`slug`, `tenant_id`),
                KEY `idx_tenant_id` (`tenant_id`),
                KEY `idx_is_active` (`is_active`),
                KEY `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Tabla de campos del formulario
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `custom_form_fields` (
                `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `form_id` int(11) UNSIGNED NOT NULL,
                `field_type` varchar(50) NOT NULL COMMENT 'text, email, textarea, select, etc.',
                `field_name` varchar(100) NOT NULL COMMENT 'Nombre interno del campo',
                `field_label` varchar(255) NOT NULL COMMENT 'Etiqueta visible',
                `placeholder` varchar(255) DEFAULT NULL,
                `default_value` text,
                `help_text` varchar(500) DEFAULT NULL COMMENT 'Texto de ayuda bajo el campo',
                `options` json DEFAULT NULL COMMENT 'Opciones para select, radio, checkbox_group',
                `validation_rules` json DEFAULT NULL COMMENT 'Reglas de validación',
                `is_required` tinyint(1) DEFAULT 0,
                `min_length` int(11) DEFAULT NULL,
                `max_length` int(11) DEFAULT NULL,
                `min_value` decimal(15,2) DEFAULT NULL,
                `max_value` decimal(15,2) DEFAULT NULL,
                `pattern` varchar(500) DEFAULT NULL COMMENT 'Regex para validación',
                `error_message` varchar(500) DEFAULT NULL COMMENT 'Mensaje de error personalizado',
                `field_class` varchar(255) DEFAULT NULL COMMENT 'Clases CSS adicionales',
                `wrapper_class` varchar(255) DEFAULT NULL COMMENT 'Clases CSS del contenedor',
                `width` varchar(20) DEFAULT 'full' COMMENT 'full, half, third, quarter',
                `conditional_logic` json DEFAULT NULL COMMENT 'Lógica condicional',
                `sort_order` int(11) DEFAULT 0,
                `is_active` tinyint(1) DEFAULT 1,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_field_form` (`form_id`, `field_name`),
                KEY `idx_form_id` (`form_id`),
                KEY `idx_sort_order` (`sort_order`),
                KEY `idx_field_type` (`field_type`),
                CONSTRAINT `fk_form_fields_form`
                    FOREIGN KEY (`form_id`)
                    REFERENCES `custom_forms` (`id`)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Tabla de envíos/submissions
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `custom_form_submissions` (
                `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `form_id` int(11) UNSIGNED NOT NULL,
                `data` json NOT NULL COMMENT 'Datos del envío en JSON',
                `ip_address` varchar(45) DEFAULT NULL,
                `user_agent` varchar(500) DEFAULT NULL,
                `referrer_url` varchar(500) DEFAULT NULL,
                `page_url` varchar(500) DEFAULT NULL COMMENT 'URL donde se envió el formulario',
                `user_id` int(11) UNSIGNED DEFAULT NULL COMMENT 'Usuario autenticado si aplica',
                `email_sent` tinyint(1) DEFAULT 0,
                `email_sent_at` datetime DEFAULT NULL,
                `confirmation_sent` tinyint(1) DEFAULT 0,
                `is_read` tinyint(1) DEFAULT 0,
                `is_starred` tinyint(1) DEFAULT 0,
                `is_spam` tinyint(1) DEFAULT 0,
                `notes` text COMMENT 'Notas internas',
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_form_id` (`form_id`),
                KEY `idx_created_at` (`created_at`),
                KEY `idx_is_read` (`is_read`),
                KEY `idx_is_starred` (`is_starred`),
                KEY `idx_is_spam` (`is_spam`),
                CONSTRAINT `fk_submissions_form`
                    FOREIGN KEY (`form_id`)
                    REFERENCES `custom_forms` (`id`)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Tabla de configuraciones del módulo
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `custom_form_settings` (
                `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id` int(11) UNSIGNED DEFAULT NULL COMMENT 'NULL = configuración global',
                `setting_key` varchar(100) NOT NULL,
                `setting_value` text,
                `setting_type` enum('string','int','bool','json','array') DEFAULT 'string',
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_setting_tenant` (`setting_key`, `tenant_id`),
                KEY `idx_tenant_id` (`tenant_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Insertar configuraciones por defecto
        $this->insertDefaultSettings($pdo, 'mysql');
    }

    private function upPostgreSQL($pdo)
    {
        // Tabla principal de formularios
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS custom_forms (
                id SERIAL PRIMARY KEY,
                tenant_id INTEGER DEFAULT NULL,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                description TEXT,
                submit_button_text VARCHAR(100) DEFAULT 'Enviar',
                success_message TEXT,
                error_message TEXT,
                redirect_url VARCHAR(500) DEFAULT NULL,
                email_to VARCHAR(500) DEFAULT NULL,
                email_subject VARCHAR(255) DEFAULT NULL,
                email_from_name VARCHAR(100) DEFAULT NULL,
                email_from_email VARCHAR(255) DEFAULT NULL,
                email_reply_to VARCHAR(255) DEFAULT NULL,
                send_confirmation_email SMALLINT DEFAULT 0,
                confirmation_email_subject VARCHAR(255) DEFAULT NULL,
                confirmation_email_message TEXT,
                store_submissions SMALLINT DEFAULT 1,
                enable_recaptcha SMALLINT DEFAULT 0,
                form_class VARCHAR(255) DEFAULT NULL,
                settings JSONB DEFAULT NULL,
                is_active SMALLINT DEFAULT 1,
                submissions_count INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (slug, tenant_id)
            )
        ");

        // Crear índices para custom_forms
        $pdo->exec("CREATE INDEX IF NOT EXISTS custom_forms_idx_tenant_id ON custom_forms(tenant_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS custom_forms_idx_is_active ON custom_forms(is_active)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS custom_forms_idx_created_at ON custom_forms(created_at)");

        // Agregar comentarios
        $pdo->exec("COMMENT ON COLUMN custom_forms.tenant_id IS 'NULL = formulario global'");
        $pdo->exec("COMMENT ON COLUMN custom_forms.redirect_url IS 'URL de redirección tras envío'");
        $pdo->exec("COMMENT ON COLUMN custom_forms.email_to IS 'Emails separados por coma'");
        $pdo->exec("COMMENT ON COLUMN custom_forms.form_class IS 'Clases CSS adicionales'");
        $pdo->exec("COMMENT ON COLUMN custom_forms.settings IS 'Configuración adicional JSON'");

        // Tabla de campos del formulario
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS custom_form_fields (
                id SERIAL PRIMARY KEY,
                form_id INTEGER NOT NULL,
                field_type VARCHAR(50) NOT NULL,
                field_name VARCHAR(100) NOT NULL,
                field_label VARCHAR(255) NOT NULL,
                placeholder VARCHAR(255) DEFAULT NULL,
                default_value TEXT,
                help_text VARCHAR(500) DEFAULT NULL,
                options JSONB DEFAULT NULL,
                validation_rules JSONB DEFAULT NULL,
                is_required SMALLINT DEFAULT 0,
                min_length INTEGER DEFAULT NULL,
                max_length INTEGER DEFAULT NULL,
                min_value DECIMAL(15,2) DEFAULT NULL,
                max_value DECIMAL(15,2) DEFAULT NULL,
                pattern VARCHAR(500) DEFAULT NULL,
                error_message VARCHAR(500) DEFAULT NULL,
                field_class VARCHAR(255) DEFAULT NULL,
                wrapper_class VARCHAR(255) DEFAULT NULL,
                width VARCHAR(20) DEFAULT 'full',
                conditional_logic JSONB DEFAULT NULL,
                sort_order INTEGER DEFAULT 0,
                is_active SMALLINT DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (form_id, field_name),
                CONSTRAINT fk_form_fields_form
                    FOREIGN KEY (form_id)
                    REFERENCES custom_forms (id)
                    ON DELETE CASCADE
            )
        ");

        // Crear índices para custom_form_fields
        $pdo->exec("CREATE INDEX IF NOT EXISTS custom_form_fields_idx_form_id ON custom_form_fields(form_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS custom_form_fields_idx_sort_order ON custom_form_fields(sort_order)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS custom_form_fields_idx_field_type ON custom_form_fields(field_type)");

        // Agregar comentarios para custom_form_fields
        $pdo->exec("COMMENT ON COLUMN custom_form_fields.field_type IS 'text, email, textarea, select, etc.'");
        $pdo->exec("COMMENT ON COLUMN custom_form_fields.field_name IS 'Nombre interno del campo'");
        $pdo->exec("COMMENT ON COLUMN custom_form_fields.field_label IS 'Etiqueta visible'");
        $pdo->exec("COMMENT ON COLUMN custom_form_fields.help_text IS 'Texto de ayuda bajo el campo'");
        $pdo->exec("COMMENT ON COLUMN custom_form_fields.options IS 'Opciones para select, radio, checkbox_group'");
        $pdo->exec("COMMENT ON COLUMN custom_form_fields.validation_rules IS 'Reglas de validación'");
        $pdo->exec("COMMENT ON COLUMN custom_form_fields.pattern IS 'Regex para validación'");
        $pdo->exec("COMMENT ON COLUMN custom_form_fields.error_message IS 'Mensaje de error personalizado'");
        $pdo->exec("COMMENT ON COLUMN custom_form_fields.field_class IS 'Clases CSS adicionales'");
        $pdo->exec("COMMENT ON COLUMN custom_form_fields.wrapper_class IS 'Clases CSS del contenedor'");
        $pdo->exec("COMMENT ON COLUMN custom_form_fields.width IS 'full, half, third, quarter'");
        $pdo->exec("COMMENT ON COLUMN custom_form_fields.conditional_logic IS 'Lógica condicional'");

        // Tabla de envíos/submissions
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS custom_form_submissions (
                id SERIAL PRIMARY KEY,
                form_id INTEGER NOT NULL,
                data JSONB NOT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent VARCHAR(500) DEFAULT NULL,
                referrer_url VARCHAR(500) DEFAULT NULL,
                page_url VARCHAR(500) DEFAULT NULL,
                user_id INTEGER DEFAULT NULL,
                email_sent SMALLINT DEFAULT 0,
                email_sent_at TIMESTAMP DEFAULT NULL,
                confirmation_sent SMALLINT DEFAULT 0,
                is_read SMALLINT DEFAULT 0,
                is_starred SMALLINT DEFAULT 0,
                is_spam SMALLINT DEFAULT 0,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_submissions_form
                    FOREIGN KEY (form_id)
                    REFERENCES custom_forms (id)
                    ON DELETE CASCADE
            )
        ");

        // Crear índices para custom_form_submissions
        $pdo->exec("CREATE INDEX IF NOT EXISTS custom_form_submissions_idx_form_id ON custom_form_submissions(form_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS custom_form_submissions_idx_created_at ON custom_form_submissions(created_at)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS custom_form_submissions_idx_is_read ON custom_form_submissions(is_read)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS custom_form_submissions_idx_is_starred ON custom_form_submissions(is_starred)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS custom_form_submissions_idx_is_spam ON custom_form_submissions(is_spam)");

        // Agregar comentarios para custom_form_submissions
        $pdo->exec("COMMENT ON COLUMN custom_form_submissions.data IS 'Datos del envío en JSON'");
        $pdo->exec("COMMENT ON COLUMN custom_form_submissions.page_url IS 'URL donde se envió el formulario'");
        $pdo->exec("COMMENT ON COLUMN custom_form_submissions.user_id IS 'Usuario autenticado si aplica'");
        $pdo->exec("COMMENT ON COLUMN custom_form_submissions.notes IS 'Notas internas'");

        // Tabla de configuraciones del módulo
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS custom_form_settings (
                id SERIAL PRIMARY KEY,
                tenant_id INTEGER DEFAULT NULL,
                setting_key VARCHAR(100) NOT NULL,
                setting_value TEXT,
                setting_type VARCHAR(20) DEFAULT 'string',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CHECK (setting_type IN ('string', 'int', 'bool', 'json', 'array')),
                UNIQUE (setting_key, tenant_id)
            )
        ");

        // Crear índices para custom_form_settings
        $pdo->exec("CREATE INDEX IF NOT EXISTS custom_form_settings_idx_tenant_id ON custom_form_settings(tenant_id)");

        // Agregar comentario
        $pdo->exec("COMMENT ON COLUMN custom_form_settings.tenant_id IS 'NULL = configuración global'");

        // Insertar configuraciones por defecto
        $this->insertDefaultSettings($pdo, 'pgsql');
    }

    private function insertDefaultSettings($pdo, $driver)
    {
        $defaultSettings = [
            ['default_success_message', 'Gracias por tu envío. Te contactaremos pronto.', 'string'],
            ['default_error_message', 'Hubo un error al enviar el formulario. Por favor, intenta de nuevo.', 'string'],
            ['enable_recaptcha', '0', 'bool'],
            ['recaptcha_site_key', '', 'string'],
            ['recaptcha_secret_key', '', 'string'],
            ['max_file_size_mb', '5', 'int'],
            ['allowed_file_types', 'pdf,doc,docx,jpg,jpeg,png,gif', 'string'],
            ['store_submissions', '1', 'bool'],
            ['send_email_notifications', '1', 'bool'],
            ['default_from_email', '', 'string'],
            ['default_from_name', '', 'string'],
            ['honeypot_enabled', '1', 'bool'],
            ['submissions_per_page', '25', 'int'],
        ];

        if ($driver === 'mysql') {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO custom_form_settings
                (tenant_id, setting_key, setting_value, setting_type)
                VALUES (NULL, ?, ?, ?)
            ");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO custom_form_settings
                (tenant_id, setting_key, setting_value, setting_type)
                VALUES (NULL, ?, ?, ?)
                ON CONFLICT (setting_key, tenant_id) DO NOTHING
            ");
        }

        foreach ($defaultSettings as $setting) {
            $stmt->execute($setting);
        }
    }

    public function down()
    {
        $pdo = \Screenart\Musedock\Database::connect();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            // MySQL: Deshabilitar foreign keys temporalmente
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $pdo->exec("DROP TABLE IF EXISTS `custom_form_submissions`");
            $pdo->exec("DROP TABLE IF EXISTS `custom_form_fields`");
            $pdo->exec("DROP TABLE IF EXISTS `custom_form_settings`");
            $pdo->exec("DROP TABLE IF EXISTS `custom_forms`");
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        } else {
            // PostgreSQL: CASCADE elimina dependencias automáticamente
            $pdo->exec("DROP TABLE IF EXISTS custom_form_submissions CASCADE");
            $pdo->exec("DROP TABLE IF EXISTS custom_form_fields CASCADE");
            $pdo->exec("DROP TABLE IF EXISTS custom_form_settings CASCADE");
            $pdo->exec("DROP TABLE IF EXISTS custom_forms CASCADE");
        }

        error_log("CustomForms: Tables dropped successfully");
    }
}
