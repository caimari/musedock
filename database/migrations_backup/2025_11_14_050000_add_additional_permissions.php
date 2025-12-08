<?php

/**
 * Migración: Agregar permisos adicionales para proteger todos los controladores
 * Fecha: 2025-11-14
 *
 * Agrega permisos faltantes para:
 * - Gestión de roles y permisos
 * - Módulos y plugins
 * - Logs y auditoría
 * - Funcionalidades avanzadas (AI)
 * - Media avanzada (sliders)
 */

use Screenart\Musedock\Database;

class AddAdditionalPermissions_2025_11_14_050000
{
    public function up()
    {
        $pdo = Database::connect();

        echo "════════════════════════════════════════════════════════════\n";
        echo " MIGRACIÓN: Permisos Adicionales del Sistema\n";
        echo "════════════════════════════════════════════════════════════\n\n";

        try {
            echo "Insertando permisos adicionales...\n\n";

            $additionalPermissions = [
                // USUARIOS AVANZADO (Roles y Permisos)
                ['slug' => 'users.manage', 'name' => 'Gestionar roles y permisos', 'description' => 'Crear y editar roles, asignar permisos del sistema', 'category' => 'Usuarios'],

                // MÓDULOS Y PLUGINS
                ['slug' => 'modules.manage', 'name' => 'Gestionar módulos', 'description' => 'Activar/desactivar módulos y plugins del sistema', 'category' => 'Módulos'],
                ['slug' => 'modules.install', 'name' => 'Instalar módulos', 'description' => 'Instalar nuevos módulos en el sistema', 'category' => 'Módulos'],
                ['slug' => 'modules.configure', 'name' => 'Configurar módulos', 'description' => 'Configurar ajustes de módulos instalados', 'category' => 'Módulos'],

                // LOGS Y AUDITORÍA
                ['slug' => 'logs.view', 'name' => 'Ver logs', 'description' => 'Acceder a logs del sistema y auditoría', 'category' => 'Logs'],
                ['slug' => 'logs.delete', 'name' => 'Eliminar logs', 'description' => 'Limpiar logs antiguos del sistema', 'category' => 'Logs'],

                // FUNCIONALIDADES AVANZADAS
                ['slug' => 'advanced.ai', 'name' => 'Usar AI', 'description' => 'Acceder a funcionalidades de inteligencia artificial', 'category' => 'Avanzado'],
                ['slug' => 'advanced.cron', 'name' => 'Gestionar tareas programadas', 'description' => 'Ver y gestionar tareas cron del sistema', 'category' => 'Avanzado'],

                // MEDIA AVANZADA
                ['slug' => 'media.manage', 'name' => 'Gestionar media avanzada', 'description' => 'Gestionar sliders, galerías y media compleja', 'category' => 'Media'],

                // IDIOMAS
                ['slug' => 'languages.manage', 'name' => 'Gestionar idiomas', 'description' => 'Agregar y configurar idiomas del sistema', 'category' => 'Configuración'],
            ];

            $stmt = $pdo->prepare("
                INSERT IGNORE INTO permissions (slug, name, description, category, tenant_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, NULL, NOW(), NOW())
            ");

            $inserted = 0;
            foreach ($additionalPermissions as $perm) {
                try {
                    $result = $stmt->execute([
                        $perm['slug'],
                        $perm['name'],
                        $perm['description'],
                        $perm['category']
                    ]);
                    if ($result && $stmt->rowCount() > 0) {
                        $inserted++;
                        echo "  ✓ {$perm['slug']}: {$perm['name']}\n";
                    }
                } catch (\Exception $e) {
                    // Ignorar duplicados
                    if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                        throw $e;
                    }
                }
            }

            echo "\n✓ {$inserted} permisos adicionales insertados\n\n";

            echo "════════════════════════════════════════════════════════════\n";
            echo " ✓ Migración completada exitosamente\n";
            echo "════════════════════════════════════════════════════════════\n\n";

            echo "📋 PERMISOS AGREGADOS:\n";
            echo "   • Usuarios: users.manage\n";
            echo "   • Módulos: manage, install, configure\n";
            echo "   • Logs: view, delete\n";
            echo "   • Avanzado: ai, cron\n";
            echo "   • Media: manage\n";
            echo "   • Idiomas: manage\n\n";

            echo "💡 PRÓXIMOS PASOS:\n";
            echo "   - Todos los controladores serán protegidos con estos permisos\n";
            echo "   - Asigna permisos a usuarios desde /musedock/users/{id}/edit\n\n";

        } catch (Exception $e) {
            echo "\n✗ Error: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    public function down()
    {
        $pdo = Database::connect();

        echo "════════════════════════════════════════════════════════════\n";
        echo " ROLLBACK: Eliminar permisos adicionales\n";
        echo "════════════════════════════════════════════════════════════\n\n";

        try {
            $slugs = [
                'users.manage',
                'modules.manage',
                'modules.install',
                'modules.configure',
                'logs.view',
                'logs.delete',
                'advanced.ai',
                'advanced.cron',
                'media.manage',
                'languages.manage',
            ];

            $placeholders = implode(',', array_fill(0, count($slugs), '?'));
            $stmt = $pdo->prepare("DELETE FROM permissions WHERE slug IN ({$placeholders})");
            $stmt->execute($slugs);

            echo "✓ Permisos adicionales eliminados\n\n";

            echo "════════════════════════════════════════════════════════════\n";
            echo " ✓ Rollback completado\n";
            echo "════════════════════════════════════════════════════════════\n\n";

        } catch (Exception $e) {
            echo "\n✗ Error: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
}
