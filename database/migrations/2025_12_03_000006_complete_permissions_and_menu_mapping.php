<?php

/**
 * Migración: Completar permisos del sistema y mapeo a menús
 *
 * Esta migración:
 * 1. Agrega todos los permisos necesarios para el CMS
 * 2. Actualiza admin_menus con los permission slugs correspondientes
 * 3. Asegura que el sidebar respete permisos para super_admins sin is_root
 */

use Screenart\Musedock\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        echo "════════════════════════════════════════════════════════════\n";
        echo " MIGRACIÓN: Completar permisos y mapeo de menús\n";
        echo "════════════════════════════════════════════════════════════\n\n";

        // PASO 1: Agregar permisos faltantes
        echo "Agregando permisos faltantes...\n";

        $permissions = [
            // Dashboard
            ['slug' => 'dashboard.view', 'name' => 'Ver Dashboard', 'description' => 'Acceso al panel principal', 'category' => 'Dashboard'],

            // Gestión de Usuarios (para /musedock/)
            ['slug' => 'users.view', 'name' => 'Ver usuarios', 'description' => 'Ver listado de usuarios', 'category' => 'Usuarios'],
            ['slug' => 'users.create', 'name' => 'Crear usuarios', 'description' => 'Crear nuevos usuarios', 'category' => 'Usuarios'],
            ['slug' => 'users.edit', 'name' => 'Editar usuarios', 'description' => 'Editar usuarios existentes', 'category' => 'Usuarios'],
            ['slug' => 'users.delete', 'name' => 'Eliminar usuarios', 'description' => 'Eliminar usuarios del sistema', 'category' => 'Usuarios'],
            ['slug' => 'users.manage', 'name' => 'Gestionar usuarios', 'description' => 'Gestión completa de usuarios y roles', 'category' => 'Usuarios'],

            // Roles
            ['slug' => 'roles.view', 'name' => 'Ver roles', 'description' => 'Ver listado de roles', 'category' => 'Usuarios'],
            ['slug' => 'roles.create', 'name' => 'Crear roles', 'description' => 'Crear nuevos roles', 'category' => 'Usuarios'],
            ['slug' => 'roles.edit', 'name' => 'Editar roles', 'description' => 'Editar roles existentes', 'category' => 'Usuarios'],
            ['slug' => 'roles.delete', 'name' => 'Eliminar roles', 'description' => 'Eliminar roles del sistema', 'category' => 'Usuarios'],

            // Permisos
            ['slug' => 'permissions.view', 'name' => 'Ver permisos', 'description' => 'Ver listado de permisos', 'category' => 'Usuarios'],
            ['slug' => 'permissions.manage', 'name' => 'Gestionar permisos', 'description' => 'Crear y editar permisos', 'category' => 'Usuarios'],

            // Recursos
            ['slug' => 'resources.view', 'name' => 'Ver recursos', 'description' => 'Ver recursos del sistema', 'category' => 'Sistema'],
            ['slug' => 'resources.manage', 'name' => 'Gestionar recursos', 'description' => 'Gestionar recursos', 'category' => 'Sistema'],

            // Sesiones
            ['slug' => 'sessions.view', 'name' => 'Ver sesiones', 'description' => 'Ver sesiones activas', 'category' => 'Sistema'],
            ['slug' => 'sessions.manage', 'name' => 'Gestionar sesiones', 'description' => 'Cerrar sesiones de usuarios', 'category' => 'Sistema'],

            // Páginas
            ['slug' => 'pages.view', 'name' => 'Ver páginas', 'description' => 'Ver listado de páginas', 'category' => 'Contenido'],
            ['slug' => 'pages.create', 'name' => 'Crear páginas', 'description' => 'Crear nuevas páginas', 'category' => 'Contenido'],
            ['slug' => 'pages.edit', 'name' => 'Editar páginas', 'description' => 'Editar páginas existentes', 'category' => 'Contenido'],
            ['slug' => 'pages.delete', 'name' => 'Eliminar páginas', 'description' => 'Eliminar páginas', 'category' => 'Contenido'],

            // Apariencia
            ['slug' => 'themes.view', 'name' => 'Ver temas', 'description' => 'Ver temas disponibles', 'category' => 'Apariencia'],
            ['slug' => 'themes.manage', 'name' => 'Gestionar temas', 'description' => 'Cambiar y configurar temas', 'category' => 'Apariencia'],
            ['slug' => 'menus.view', 'name' => 'Ver menús', 'description' => 'Ver menús de navegación', 'category' => 'Apariencia'],
            ['slug' => 'menus.edit', 'name' => 'Editar menús', 'description' => 'Editar menús de navegación', 'category' => 'Apariencia'],
            ['slug' => 'sliders.view', 'name' => 'Ver sliders', 'description' => 'Ver sliders del sitio', 'category' => 'Apariencia'],
            ['slug' => 'sliders.manage', 'name' => 'Gestionar sliders', 'description' => 'Crear y editar sliders', 'category' => 'Apariencia'],
            ['slug' => 'widgets.view', 'name' => 'Ver widgets', 'description' => 'Ver widgets disponibles', 'category' => 'Apariencia'],
            ['slug' => 'widgets.manage', 'name' => 'Gestionar widgets', 'description' => 'Configurar widgets', 'category' => 'Apariencia'],

            // Módulos
            ['slug' => 'modules.view', 'name' => 'Ver módulos', 'description' => 'Ver módulos instalados', 'category' => 'Sistema'],
            ['slug' => 'modules.manage', 'name' => 'Gestionar módulos', 'description' => 'Instalar/desinstalar módulos', 'category' => 'Sistema'],

            // Configuración
            ['slug' => 'settings.view', 'name' => 'Ver configuración', 'description' => 'Ver configuración del sistema', 'category' => 'Configuración'],
            ['slug' => 'settings.edit', 'name' => 'Editar configuración', 'description' => 'Modificar configuración del sistema', 'category' => 'Configuración'],
            ['slug' => 'settings.advanced', 'name' => 'Configuración avanzada', 'description' => 'Acceso a configuración avanzada', 'category' => 'Configuración'],

            // Idiomas
            ['slug' => 'languages.view', 'name' => 'Ver idiomas', 'description' => 'Ver idiomas configurados', 'category' => 'Configuración'],
            ['slug' => 'languages.manage', 'name' => 'Gestionar idiomas', 'description' => 'Agregar y configurar idiomas', 'category' => 'Configuración'],

            // SEO
            ['slug' => 'seo.view', 'name' => 'Ver SEO', 'description' => 'Ver configuración SEO', 'category' => 'Configuración'],
            ['slug' => 'seo.edit', 'name' => 'Editar SEO', 'description' => 'Modificar configuración SEO', 'category' => 'Configuración'],

            // Cookies
            ['slug' => 'cookies.view', 'name' => 'Ver cookies', 'description' => 'Ver configuración de cookies', 'category' => 'Configuración'],
            ['slug' => 'cookies.edit', 'name' => 'Editar cookies', 'description' => 'Modificar configuración de cookies', 'category' => 'Configuración'],

            // Tenants (solo visible si multi-tenant)
            ['slug' => 'tenants.view', 'name' => 'Ver tenants', 'description' => 'Ver listado de tenants', 'category' => 'Multi-tenant'],
            ['slug' => 'tenants.create', 'name' => 'Crear tenants', 'description' => 'Crear nuevos tenants', 'category' => 'Multi-tenant'],
            ['slug' => 'tenants.edit', 'name' => 'Editar tenants', 'description' => 'Editar tenants existentes', 'category' => 'Multi-tenant'],
            ['slug' => 'tenants.delete', 'name' => 'Eliminar tenants', 'description' => 'Eliminar tenants', 'category' => 'Multi-tenant'],

            // Logs
            ['slug' => 'logs.view', 'name' => 'Ver logs', 'description' => 'Ver registros del sistema', 'category' => 'Sistema'],
            ['slug' => 'logs.delete', 'name' => 'Eliminar logs', 'description' => 'Limpiar registros del sistema', 'category' => 'Sistema'],

            // IA
            ['slug' => 'ai.view', 'name' => 'Ver IA', 'description' => 'Ver panel de IA', 'category' => 'IA'],
            ['slug' => 'ai.manage', 'name' => 'Gestionar IA', 'description' => 'Configurar proveedores de IA', 'category' => 'IA'],
            ['slug' => 'ai.settings', 'name' => 'Configurar IA', 'description' => 'Modificar configuración de IA', 'category' => 'IA'],

            // Media
            ['slug' => 'media.view', 'name' => 'Ver media', 'description' => 'Ver biblioteca de medios', 'category' => 'Media'],
            ['slug' => 'media.upload', 'name' => 'Subir media', 'description' => 'Subir archivos multimedia', 'category' => 'Media'],
            ['slug' => 'media.delete', 'name' => 'Eliminar media', 'description' => 'Eliminar archivos multimedia', 'category' => 'Media'],

            // Tickets (solo si multi-tenant)
            ['slug' => 'tickets.view', 'name' => 'Ver tickets', 'description' => 'Ver tickets de soporte', 'category' => 'Multi-tenant'],
            ['slug' => 'tickets.manage', 'name' => 'Gestionar tickets', 'description' => 'Responder y gestionar tickets', 'category' => 'Multi-tenant'],

            // Seguridad
            ['slug' => 'security.view', 'name' => 'Ver seguridad', 'description' => 'Ver configuración de seguridad', 'category' => 'Seguridad'],
            ['slug' => 'security.manage', 'name' => 'Gestionar seguridad', 'description' => 'Configurar 2FA, WAF, etc.', 'category' => 'Seguridad'],
        ];

        $inserted = 0;
        foreach ($permissions as $perm) {
            try {
                $this->execute("
                    INSERT IGNORE INTO permissions (slug, name, description, category, tenant_id, created_at, updated_at)
                    VALUES ('{$perm['slug']}', '{$perm['name']}', '{$perm['description']}', '{$perm['category']}', NULL, NOW(), NOW())
                ");
                $inserted++;
            } catch (\Exception $e) {
                // Ignorar duplicados
            }
        }
        echo "  ✓ {$inserted} permisos verificados/insertados\n\n";

        // PASO 2: Actualizar admin_menus con permission slugs
        echo "Actualizando mapeo de permisos en admin_menus...\n";

        $menuPermissions = [
            // Menús principales
            'dashboard' => 'dashboard.view',
            'pages' => 'pages.view',
            'appearance' => 'themes.view',
            'modules' => 'modules.view',
            'user_management' => 'users.view',
            'settings' => 'settings.view',
            'ai' => 'ai.view',

            // Submenús de Apariencia
            'themes' => 'themes.view',
            'menus' => 'menus.view',
            'sliders' => 'sliders.view',
            'widgets' => 'widgets.view',
            'media_manager' => 'media.view',

            // Submenús de Gestión de Usuarios
            'users' => 'users.view',
            'admins' => 'users.view',
            'roles' => 'roles.view',
            'permissions' => 'permissions.view',
            'resources' => 'resources.view',
            'sessions' => 'sessions.view',

            // Submenús de Ajustes
            'config' => 'settings.view',
            'general_settings' => 'settings.view',
            'advanced' => 'settings.advanced',
            'languages' => 'languages.view',
            'seo_social' => 'seo.view',
            'cookies' => 'cookies.view',
            'tenants' => 'tenants.view',
            'logs' => 'logs.view',
            'cron' => 'settings.view',

            // Submenús de IA
            'ai_dashboard' => 'ai.view',
            'ai_settings' => 'ai.settings',
            'ai_providers' => 'ai.manage',
            'ai_logs' => 'logs.view',

            // Tickets
            'tickets' => 'tickets.view',

            // Seguridad
            'security' => 'security.view',
            '2fa' => 'security.view',
        ];

        $updated = 0;
        foreach ($menuPermissions as $menuSlug => $permissionSlug) {
            $result = $this->execute("UPDATE admin_menus SET permission = '{$permissionSlug}' WHERE slug = '{$menuSlug}'");
            $updated++;
        }

        echo "  ✓ {$updated} menús actualizados con permisos\n\n";

        echo "════════════════════════════════════════════════════════════\n";
        echo " ✓ Migración completada\n";
        echo "════════════════════════════════════════════════════════════\n\n";
        echo "NOTA: Los super_admins con is_root=0 ahora verán solo los menús\n";
        echo "      para los cuales tienen permisos asignados via roles.\n";
    }

    public function down(): void
    {
        // Revertir: limpiar permisos de admin_menus
        $this->execute("UPDATE admin_menus SET permission = NULL");
        echo "✓ Permisos eliminados de admin_menus\n";

        // No eliminamos los permisos de la tabla permissions
        // ya que podrían estar siendo usados por roles existentes
    }
};
