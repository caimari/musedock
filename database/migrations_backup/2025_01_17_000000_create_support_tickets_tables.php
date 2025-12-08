<?php

/**
 * Migración: Sistema de Tickets de Soporte
 * Fecha: 2025-01-17
 * Descripción: Sistema completo de tickets con notificaciones en tiempo real
 * Solo activo cuando MULTI_TENANT_ENABLED=true
 *
 * CARACTERÍSTICAS:
 * ✅ Tickets de soporte multi-tenant
 * ✅ Sistema de mensajes/conversación
 * ✅ Notificaciones en tiempo real (WebSocket/Redis)
 * ✅ Prioridades y estados
 * ✅ Asignación a superadmins
 * ✅ Notas internas para staff
 */

use Screenart\Musedock\Database;

class CreateSupportTicketsTables_2025_01_17_000000
{
    public function up()
    {
        $pdo = Database::connect();

        echo "════════════════════════════════════════════════════════════\n";
        echo " MIGRACIÓN: Sistema de Tickets de Soporte\n";
        echo "════════════════════════════════════════════════════════════\n\n";

        try {
            // ========== TABLA: support_tickets ==========
            echo "📝 Creando tabla 'support_tickets'...\n";

            $stmt = $pdo->query("SHOW TABLES LIKE 'support_tickets'");
            if ($stmt->fetch()) {
                echo "⚠ Tabla 'support_tickets' ya existe\n";
            } else {
                $pdo->exec("
                    CREATE TABLE `support_tickets` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `tenant_id` int(11) NOT NULL COMMENT 'ID del tenant',
                        `admin_id` int(11) NOT NULL COMMENT 'ID del admin que crea el ticket',
                        `assigned_to` int(11) DEFAULT NULL COMMENT 'ID del superadmin asignado',
                        `subject` varchar(500) NOT NULL COMMENT 'Asunto del ticket',
                        `description` text NOT NULL COMMENT 'Descripción del problema',
                        `priority` enum('low','normal','high','urgent') DEFAULT 'normal' COMMENT 'Prioridad',
                        `status` enum('open','in_progress','resolved','closed') DEFAULT 'open' COMMENT 'Estado',
                        `resolved_at` datetime DEFAULT NULL COMMENT 'Fecha de resolución',
                        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `tenant_id` (`tenant_id`),
                        KEY `admin_id` (`admin_id`),
                        KEY `assigned_to` (`assigned_to`),
                        KEY `status` (`status`),
                        KEY `priority` (`priority`),
                        KEY `created_at` (`created_at`),
                        CONSTRAINT `fk_tickets_tenant`
                            FOREIGN KEY (`tenant_id`)
                            REFERENCES `tenants` (`id`)
                            ON DELETE CASCADE,
                        CONSTRAINT `fk_tickets_admin`
                            FOREIGN KEY (`admin_id`)
                            REFERENCES `admins` (`id`)
                            ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Tickets de soporte de tenants'
                ");
                echo "✓ Tabla 'support_tickets' creada\n";
            }

            // ========== TABLA: support_ticket_messages ==========
            echo "📝 Creando tabla 'support_ticket_messages'...\n";

            $stmt = $pdo->query("SHOW TABLES LIKE 'support_ticket_messages'");
            if ($stmt->fetch()) {
                echo "⚠ Tabla 'support_ticket_messages' ya existe\n";
            } else {
                $pdo->exec("
                    CREATE TABLE `support_ticket_messages` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `ticket_id` int(11) NOT NULL COMMENT 'ID del ticket',
                        `user_id` int(11) NOT NULL COMMENT 'ID del usuario que escribe',
                        `user_type` enum('admin','super_admin') NOT NULL COMMENT 'Tipo de usuario',
                        `message` text NOT NULL COMMENT 'Mensaje',
                        `is_internal` tinyint(1) DEFAULT 0 COMMENT '1 = Nota interna (solo staff)',
                        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `ticket_id` (`ticket_id`),
                        KEY `user_type` (`user_type`,`user_id`),
                        KEY `created_at` (`created_at`),
                        CONSTRAINT `fk_ticket_messages_ticket`
                            FOREIGN KEY (`ticket_id`)
                            REFERENCES `support_tickets` (`id`)
                            ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Mensajes/respuestas de tickets'
                ");
                echo "✓ Tabla 'support_ticket_messages' creada\n";
            }

            // ========== TABLA: notifications ==========
            echo "📝 Creando tabla 'notifications'...\n";

            $stmt = $pdo->query("SHOW TABLES LIKE 'notifications'");
            if ($stmt->fetch()) {
                echo "⚠ Tabla 'notifications' ya existe\n";
            } else {
                $pdo->exec("
                    CREATE TABLE `notifications` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `user_id` int(11) NOT NULL COMMENT 'ID del usuario destinatario',
                        `user_type` enum('admin','super_admin') NOT NULL COMMENT 'Tipo de usuario',
                        `type` varchar(100) NOT NULL COMMENT 'Tipo de notificación',
                        `title` varchar(255) NOT NULL COMMENT 'Título',
                        `message` text NOT NULL COMMENT 'Mensaje',
                        `data` text DEFAULT NULL COMMENT 'Datos adicionales (JSON)',
                        `is_read` tinyint(1) DEFAULT 0 COMMENT '1 = Leída, 0 = No leída',
                        `read_at` datetime DEFAULT NULL COMMENT 'Fecha de lectura',
                        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `user_type_id` (`user_type`,`user_id`),
                        KEY `is_read` (`is_read`),
                        KEY `created_at` (`created_at`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Notificaciones de usuarios'
                ");
                echo "✓ Tabla 'notifications' creada\n";
            }

            // ========== TABLA: support_ticket_stats ==========
            echo "📝 Creando tabla 'support_ticket_stats'...\n";

            $stmt = $pdo->query("SHOW TABLES LIKE 'support_ticket_stats'");
            if ($stmt->fetch()) {
                echo "⚠ Tabla 'support_ticket_stats' ya existe\n";
            } else {
                $pdo->exec("
                    CREATE TABLE `support_ticket_stats` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `tenant_id` int(11) NOT NULL COMMENT 'ID del tenant',
                        `total_tickets` int(11) DEFAULT 0,
                        `open_tickets` int(11) DEFAULT 0,
                        `in_progress_tickets` int(11) DEFAULT 0,
                        `resolved_tickets` int(11) DEFAULT 0,
                        `closed_tickets` int(11) DEFAULT 0,
                        `avg_response_time` int(11) DEFAULT NULL COMMENT 'Tiempo promedio de respuesta (minutos)',
                        `avg_resolution_time` int(11) DEFAULT NULL COMMENT 'Tiempo promedio de resolución (horas)',
                        `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `tenant_id` (`tenant_id`),
                        CONSTRAINT `fk_ticket_stats_tenant`
                            FOREIGN KEY (`tenant_id`)
                            REFERENCES `tenants` (`id`)
                            ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Estadísticas de tickets por tenant'
                ");
                echo "✓ Tabla 'support_ticket_stats' creada\n";
            }

            // Crear índices adicionales
            echo "📝 Creando índices adicionales...\n";

            try {
                $pdo->exec("CREATE INDEX idx_tickets_tenant_status ON support_tickets(tenant_id, status)");
                $pdo->exec("CREATE INDEX idx_tickets_assigned ON support_tickets(assigned_to, status)");
                echo "✓ Índices adicionales creados\n";
            } catch (Exception $e) {
                // Índices pueden ya existir
                echo "⚠ Algunos índices ya existen\n";
            }

            echo "\n";
            echo "════════════════════════════════════════════════════════════\n";
            echo " ✓ Migración completada exitosamente\n";
            echo "════════════════════════════════════════════════════════════\n\n";

            echo "📋 PRÓXIMOS PASOS:\n";
            echo "1. Habilitar sistema multi-tenant en .env:\n";
            echo "   MULTI_TENANT_ENABLED=true\n\n";
            echo "2. Configurar Redis para notificaciones (opcional):\n";
            echo "   REDIS_ENABLED=true\n";
            echo "   REDIS_HOST=127.0.0.1\n";
            echo "   REDIS_PORT=6379\n\n";
            echo "3. Acceder al panel de tickets:\n";
            echo "   Tenant: /admin/tickets\n";
            echo "   Superadmin: /musedock/tickets\n\n";

        } catch (Exception $e) {
            echo "\n";
            echo "✗ Error en migración: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    public function down()
    {
        $pdo = Database::connect();

        echo "════════════════════════════════════════════════════════════\n";
        echo " ROLLBACK: Sistema de Tickets de Soporte\n";
        echo "════════════════════════════════════════════════════════════\n\n";

        try {
            // Eliminar en orden inverso por foreign keys
            $tables = [
                'support_ticket_stats',
                'notifications',
                'support_ticket_messages',
                'support_tickets'
            ];

            foreach ($tables as $table) {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                if ($stmt->fetch()) {
                    $pdo->exec("DROP TABLE `$table`");
                    echo "✓ Tabla '$table' eliminada\n";
                } else {
                    echo "⚠ Tabla '$table' no existe\n";
                }
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
