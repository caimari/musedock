<?php
/**
 * Script de instalación del módulo AI Writer
 */

// Importar la clase Database
require_once __DIR__ . '/../../core/Database.php';

use Screenart\Musedock\Database;

try {
    // Registrar permisos para el módulo
    $perms = [
        ['name' => 'aiwriter.settings', 'description' => 'Configurar ajustes de AI Writer'],
        ['name' => 'ai.use', 'description' => 'Usar IA en el editor y otras funcionalidades']
    ];

    foreach ($perms as $perm) {
        $existing = Database::query(
            "SELECT COUNT(*) FROM permissions WHERE name = :name", 
            ['name' => $perm['name']]
        )->fetchColumn();
        
        if (!$existing) {
            Database::query(
                "INSERT INTO permissions (name, description) VALUES (:name, :description)",
                ['name' => $perm['name'], 'description' => $perm['description']]
            );
        }
    }

    // Verificar si el sistema base de IA está instalado
    $aiProvidersTableExists = false;
    try {
        $aiProvidersTableExists = Database::query("SHOW TABLES LIKE 'ai_providers'")->rowCount() > 0;
    } catch (\Exception $e) {
        // Ignorar errores
    }

    if (!$aiProvidersTableExists) {
        echo "<div style='color: red; font-weight: bold;'>Error: El sistema base de IA no está instalado. Por favor, ejecuta primero el script de instalación del sistema base de IA.</div>";
        exit;
    }

    // Asignar permiso a roles existentes
    $adminRoleId = Database::query("SELECT id FROM roles WHERE name = 'admin' AND tenant_id IS NULL")->fetchColumn();
    if ($adminRoleId) {
        // Verificar si ya tiene el permiso
        $permissionId = Database::query("SELECT id FROM permissions WHERE name = 'ai.use'")->fetchColumn();
        
        if ($permissionId) {
            $hasPermission = Database::query("
                SELECT COUNT(*) FROM role_permissions
                WHERE role_id = :role_id AND permission_id = :permission_id
            ", [
                'role_id' => $adminRoleId,
                'permission_id' => $permissionId
            ])->fetchColumn();
            
            if (!$hasPermission) {
                Database::query("
                    INSERT INTO role_permissions (role_id, permission_id)
                    VALUES (:role_id, :permission_id)
                ", [
                    'role_id' => $adminRoleId,
                    'permission_id' => $permissionId
                ]);
            }
        }
    }

    echo "Módulo AI Writer instalado correctamente";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}