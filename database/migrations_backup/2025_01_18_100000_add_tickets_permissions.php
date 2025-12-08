<?php

/**
 * Migración: Agregar permisos para el sistema de tickets
 * Fecha: 2025-01-18
 *
 * Agrega permisos necesarios para el sistema de soporte de tickets multi-tenant
 */

use Screenart\Musedock\Database;

class AddTicketsPermissions_2025_01_18_100000
{
    public function up()
    {
        $pdo = Database::connect();

        echo "════════════════════════════════════════════════════════════\n";
        echo " MIGRACIÓN: Permisos del Sistema de Tickets\n";
        echo "════════════════════════════════════════════════════════════\n\n";

        try {
            echo "Insertando permisos de tickets...\n\n";

            $ticketsPermissions = [
                // TICKETS
                ['slug' => 'tickets.view', 'name' => 'Ver tickets', 'description' => 'Ver listado y detalle de tickets de soporte', 'category' => 'Tickets'],
                ['slug' => 'tickets.create', 'name' => 'Crear tickets', 'description' => 'Crear nuevos tickets de soporte', 'category' => 'Tickets'],
                ['slug' => 'tickets.update', 'name' => 'Actualizar tickets', 'description' => 'Actualizar estado y prioridad de tickets', 'category' => 'Tickets'],
                ['slug' => 'tickets.delete', 'name' => 'Eliminar tickets', 'description' => 'Eliminar tickets de soporte', 'category' => 'Tickets'],
                ['slug' => 'tickets.reply', 'name' => 'Responder tickets', 'description' => 'Agregar respuestas a tickets de soporte', 'category' => 'Tickets'],
            ];

            $stmt = $pdo->prepare("
                INSERT IGNORE INTO permissions (slug, name, description, category, created_at)
                VALUES (:slug, :name, :description, :category, NOW())
            ");

            foreach ($ticketsPermissions as $permission) {
                $stmt->execute($permission);
                echo "  ✓ Permiso agregado: {$permission['slug']}\n";
            }

            echo "\n✓ Permisos de tickets agregados exitosamente\n\n";

            // Otorgar todos los permisos de tickets a todos los admin existentes
            echo "Otorgando permisos de tickets a administradores existentes...\n";

            // Obtener todos los admin por tenant
            $admins = $pdo->query("SELECT id, tenant_id FROM admins")->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($admins)) {
                $userPermStmt = $pdo->prepare("
                    INSERT IGNORE INTO user_permissions (user_id, permission_slug, tenant_id, created_at)
                    VALUES (:user_id, :permission_slug, :tenant_id, NOW())
                ");

                foreach ($admins as $admin) {
                    foreach ($ticketsPermissions as $permission) {
                        $userPermStmt->execute([
                            'user_id' => $admin['id'],
                            'permission_slug' => $permission['slug'],
                            'tenant_id' => $admin['tenant_id']
                        ]);
                    }
                    echo "  ✓ Permisos otorgados al admin ID: {$admin['id']} (tenant: {$admin['tenant_id']})\n";
                }

                echo "\n✓ Permisos otorgados a " . count($admins) . " administradores\n\n";
            } else {
                echo "  ⚠ No se encontraron administradores para otorgar permisos\n\n";
            }

        } catch (PDOException $e) {
            echo "❌ ERROR: " . $e->getMessage() . "\n\n";
            throw $e;
        }

        echo "════════════════════════════════════════════════════════════\n";
        echo " ✓ MIGRACIÓN COMPLETADA\n";
        echo "════════════════════════════════════════════════════════════\n\n";
    }

    public function down()
    {
        $pdo = Database::connect();

        echo "Revirtiendo permisos de tickets...\n";

        // Eliminar permisos de usuarios
        $pdo->exec("DELETE FROM user_permissions WHERE permission_slug LIKE 'tickets.%'");

        // Eliminar permisos base
        $pdo->exec("DELETE FROM permissions WHERE slug LIKE 'tickets.%'");

        echo "✓ Permisos de tickets eliminados\n";
    }
}
