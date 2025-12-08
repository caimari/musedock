<?php

use Screenart\Musedock\Database;

/**
 * Migración: Actualizar permisos en admin_menus
 *
 * Asocia cada menú con el permiso correspondiente para que el sistema
 * muestre automáticamente los menús según los permisos del usuario.
 */
class UpdateAdminMenusPermissions_2025_11_14_080000
{
    public function up()
    {
        $pdo = Database::connect();

        // Mapeo de slug de menú -> slug de permiso
        $menuPermissions = [
            // Menús principales
            'pages' => 'pages.view',
            'appearance' => 'themes.view',
            'modules' => 'modules.view',
            'user_management' => 'users.view',
            'settings' => 'settings.view',
            'ai' => 'ai.view',

            // Submenús de Apariencia
            'themes' => 'themes.view',
            'menus' => 'menus.view',
            'media_manager' => 'media.view',

            // Submenús de Gestión de Usuarios
            'users' => 'users.view',
            'admins' => 'admins.view',
            'roles' => 'roles.view',
            'permissions' => 'permissions.view',

            // Submenús de Ajustes
            'general_settings' => 'settings.view',
            'tenants' => 'tenants.view',
            'logs' => 'logs.view',
            'cron' => 'cron.view',

            // Submenús de IA
            'ai_dashboard' => 'ai.view',
            'ai_providers' => 'ai.manage',
            'ai_logs' => 'logs.view',
        ];

        $stmt = $pdo->prepare("UPDATE admin_menus SET permission = ? WHERE slug = ?");

        foreach ($menuPermissions as $menuSlug => $permissionSlug) {
            $stmt->execute([$permissionSlug, $menuSlug]);
        }

        echo "✓ Permisos actualizados en " . count($menuPermissions) . " menús\n";
    }

    public function down()
    {
        $pdo = Database::connect();

        // Limpiar permisos
        $pdo->exec("UPDATE admin_menus SET permission = NULL");

        echo "✓ Permisos eliminados de admin_menus\n";
    }
}
