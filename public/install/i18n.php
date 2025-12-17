<?php
/**
 * MuseDock CMS - Installer Translations
 */

$translations = [
    'en' => [
        'installation_wizard' => 'Installation Wizard',
        'title' => 'Installation Wizard',
        'step_requirements' => 'Requirements',
        'step_database' => 'Database',
        'step_site' => 'Site Setup',
        'step_admin' => 'Admin Account',
        'step_install' => 'Install',

        // Step 1
        'system_requirements' => 'System Requirements',
        'checking_requirements' => 'Checking requirements...',
        'composer_required' => 'Composer Dependencies Required',
        'composer_desc' => 'Composer dependencies are not installed. You have two options:',
        'auto_install' => 'Auto Install (if available)',
        'manual_instructions' => 'Manual Instructions',
        'manual_desc' => 'Connect via SSH and run:',
        'then_refresh' => 'Then refresh this page.',
        'recheck' => 'Re-check',
        'continue' => 'Continue',

        // Step 2
        'database_configuration' => 'Database Configuration',
        'database_driver' => 'Database Driver',
        'database_host' => 'Database Host',
        'database_port' => 'Database Port',
        'database_name' => 'Database Name',
        'database_user' => 'Database User',
        'database_password' => 'Database Password',
        'test_connection' => 'Test Connection',
        'back' => 'Back',

        // Step 3
        'site_configuration' => 'Site Configuration',
        'basic_information' => 'Basic Information',
        'site_name' => 'Site Name',
        'site_name_desc' => 'The name of your website (appears in title, emails, etc.)',
        'site_url' => 'Site URL',
        'site_url_desc' => 'Full URL including http:// or https:// (without trailing slash)',
        'default_language' => 'Default Language',
        'default_language_desc' => 'Primary language for the admin panel',
        'environment' => 'Environment',
        'environment_desc' => 'Use "Development" only for testing',
        'production' => 'Production',
        'development' => 'Development',
        'email_configuration' => 'Email Configuration',
        'email_from_address' => 'Email From Address',
        'email_from_address_desc' => 'Email address used for system notifications (password resets, alerts, etc.)',
        'email_from_name' => 'Email From Name',
        'email_from_name_desc' => 'Display name that appears in outgoing emails',

        // Step 4
        'administrator_account' => 'Administrator Account',
        'admin_name' => 'Admin Name',
        'admin_email' => 'Admin Email',
        'admin_password' => 'Admin Password',
        'admin_password_desc' => 'Minimum 8 characters',
        'confirm_password' => 'Confirm Password',

        // Step 5
        'installation' => 'Installation',
        'installation_summary' => 'Installation Summary',
        'installation_desc' => 'Review your configuration before installing:',
        'ready_to_install' => 'Ready to install? Click the button below.',
        'install_now' => 'Install Now',
        'installation_progress' => 'Installation Progress',
        'please_wait' => 'Please wait, this may take a few moments...',
        'installation_complete' => 'Installation Complete!',
        'installation_complete_desc' => 'MuseDock CMS has been successfully installed.',
        'goto_admin' => 'Go to Admin Panel',
        'installation_failed' => 'Installation Failed',

        // Progress steps
        'creating_env' => 'Creating .env file',
        'testing_database' => 'Testing database connection',
        'running_migrations' => 'Running migrations',
        'creating_admin' => 'Creating administrator account',
        'creating_lock' => 'Creating installation lock',

        // Common
        'required' => 'Required',
        'current' => 'Current',
        'passed' => 'Passed',
        'failed' => 'Failed',
        'optional' => 'Optional',
    ],

    'es' => [
        'installation_wizard' => 'Asistente de Instalación',
        'title' => 'Asistente de Instalación',
        'step_requirements' => 'Requisitos',
        'step_database' => 'Base de Datos',
        'step_site' => 'Configuración',
        'step_admin' => 'Administrador',
        'step_install' => 'Instalar',

        // Step 1
        'system_requirements' => 'Requisitos del Sistema',
        'checking_requirements' => 'Verificando requisitos...',
        'composer_required' => 'Dependencias de Composer Requeridas',
        'composer_desc' => 'Las dependencias de Composer no están instaladas. Tienes dos opciones:',
        'auto_install' => 'Instalación Automática (si está disponible)',
        'manual_instructions' => 'Instrucciones Manuales',
        'manual_desc' => 'Conéctate vía SSH y ejecuta:',
        'then_refresh' => 'Luego actualiza esta página.',
        'recheck' => 'Volver a verificar',
        'continue' => 'Continuar',

        // Step 2
        'database_configuration' => 'Configuración de Base de Datos',
        'database_driver' => 'Driver de Base de Datos',
        'database_host' => 'Host de Base de Datos',
        'database_port' => 'Puerto de Base de Datos',
        'database_name' => 'Nombre de Base de Datos',
        'database_user' => 'Usuario de Base de Datos',
        'database_password' => 'Contraseña de Base de Datos',
        'test_connection' => 'Probar Conexión',
        'back' => 'Atrás',

        // Step 3
        'site_configuration' => 'Configuración del Sitio',
        'basic_information' => 'Información Básica',
        'site_name' => 'Nombre del Sitio',
        'site_name_desc' => 'El nombre de tu sitio web (aparece en título, emails, etc.)',
        'site_url' => 'URL del Sitio',
        'site_url_desc' => 'URL completa incluyendo http:// o https:// (sin barra final)',
        'default_language' => 'Idioma Predeterminado',
        'default_language_desc' => 'Idioma principal para el panel de administración',
        'environment' => 'Entorno',
        'environment_desc' => 'Usa "Desarrollo" solo para pruebas',
        'production' => 'Producción',
        'development' => 'Desarrollo',
        'email_configuration' => 'Configuración de Email',
        'email_from_address' => 'Dirección Email De',
        'email_from_address_desc' => 'Dirección de email usada para notificaciones del sistema (reseteo de contraseñas, alertas, etc.)',
        'email_from_name' => 'Nombre Email De',
        'email_from_name_desc' => 'Nombre que aparece en los emails salientes',

        // Step 4
        'administrator_account' => 'Cuenta de Administrador',
        'admin_name' => 'Nombre del Administrador',
        'admin_email' => 'Email del Administrador',
        'admin_password' => 'Contraseña del Administrador',
        'admin_password_desc' => 'Mínimo 8 caracteres',
        'confirm_password' => 'Confirmar Contraseña',

        // Step 5
        'installation' => 'Instalación',
        'installation_summary' => 'Resumen de Instalación',
        'installation_desc' => 'Revisa tu configuración antes de instalar:',
        'ready_to_install' => '¿Listo para instalar? Haz clic en el botón de abajo.',
        'install_now' => 'Instalar Ahora',
        'installation_progress' => 'Progreso de Instalación',
        'please_wait' => 'Por favor espera, esto puede tomar unos momentos...',
        'installation_complete' => '¡Instalación Completa!',
        'installation_complete_desc' => 'MuseDock CMS ha sido instalado exitosamente.',
        'goto_admin' => 'Ir al Panel de Administración',
        'installation_failed' => 'Instalación Fallida',

        // Progress steps
        'creating_env' => 'Creando archivo .env',
        'testing_database' => 'Probando conexión a base de datos',
        'running_migrations' => 'Ejecutando migraciones',
        'creating_admin' => 'Creando cuenta de administrador',
        'creating_lock' => 'Creando bloqueo de instalación',

        // Common
        'required' => 'Requerido',
        'current' => 'Actual',
        'passed' => 'Aprobado',
        'failed' => 'Fallido',
        'optional' => 'Opcional',
    ]
];
