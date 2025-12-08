<?php

/**
 * Migración: Agregar menú de Tareas Programadas (Cron)
 * Fecha: 2025-11-14
 * Descripción: Añade entrada en admin_menus para el panel de cron/pseudo-cron
 */

use Screenart\Musedock\Database;

class AddCronMenu_2025_11_14_030000
{
    public function up()
    {
        $pdo = Database::connect();

        echo "════════════════════════════════════════════════════════════\n";
        echo " MIGRACIÓN: Agregar menú de Tareas Programadas (Cron)\n";
        echo "════════════════════════════════════════════════════════════\n\n";

        try {
            // Obtener ID del menú padre "Ajustes"
            $stmt = $pdo->query("SELECT id FROM admin_menus WHERE slug = 'settings'");
            $settings = $stmt->fetch(\PDO::FETCH_ASSOC);
            $settings_id = $settings['id'] ?? null;

            if (!$settings_id) {
                echo "⚠ Advertencia: No se encontró el menú padre 'settings'\n";
                echo "  El menú de Tareas Programadas se creará como menú raíz\n";
            }

            // Verificar si ya existe el menú
            $stmt = $pdo->query("SELECT id FROM admin_menus WHERE slug = 'cron_status'");
            $exists = $stmt->fetch();

            if ($exists) {
                echo "⚠ Menú 'cron_status' ya existe (ID: {$exists['id']})\n";
            } else {
                // Insertar menú de Tareas Programadas como hijo de Ajustes
                $stmt = $pdo->prepare("
                    INSERT INTO admin_menus
                    (parent_id, title, slug, url, icon, icon_type, order_position, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $settings_id,                    // parent_id
                    'Tareas Programadas',            // title
                    'cron_status',                   // slug
                    '/musedock/cron/status',         // url
                    'clock',                         // icon (Bootstrap Icons)
                    'bi',                            // icon_type
                    8,                               // order_position (después de Logs que tiene 7)
                    1                                // is_active
                ]);

                $menuId = $pdo->lastInsertId();
                echo "✓ Menú 'Tareas Programadas' creado exitosamente (ID: {$menuId})\n";
            }

            echo "\n";
            echo "════════════════════════════════════════════════════════════\n";
            echo " ✓ Migración completada exitosamente\n";
            echo "════════════════════════════════════════════════════════════\n\n";
            echo "📋 ACCESO AL PANEL:\n";
            echo "   URL: /musedock/cron/status\n";
            echo "   Ubicación: Panel de Superadmin > Ajustes > Tareas Programadas\n\n";

        } catch (Exception $e) {
            echo "\n";
            echo "✗ Error al agregar menú: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    public function down()
    {
        $pdo = Database::connect();

        echo "════════════════════════════════════════════════════════════\n";
        echo " ROLLBACK: Eliminar menú de Tareas Programadas (Cron)\n";
        echo "════════════════════════════════════════════════════════════\n\n";

        try {
            // Eliminar menú
            $stmt = $pdo->query("SELECT id FROM admin_menus WHERE slug = 'cron_status'");
            $exists = $stmt->fetch();

            if ($exists) {
                $pdo->exec("DELETE FROM admin_menus WHERE slug = 'cron_status'");
                echo "✓ Menú 'Tareas Programadas' eliminado\n";
            } else {
                echo "⚠ Menú 'cron_status' no existe\n";
            }

            echo "\n";
            echo "════════════════════════════════════════════════════════════\n";
            echo " ✓ Rollback completado exitosamente\n";
            echo "════════════════════════════════════════════════════════════\n\n";

        } catch (Exception $e) {
            echo "\n";
            echo "✗ Error al revertir migración: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
}
