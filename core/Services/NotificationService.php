<?php

namespace Screenart\Musedock\Services;

use Screenart\Musedock\Models\Notification;
use Screenart\Musedock\Env;

class NotificationService
{
    private static ?object $redis = null;

    /**
     * Inicializar conexión a Redis
     */
    private static function getRedis(): ?object
    {
        if (self::$redis !== null) {
            return self::$redis;
        }

        // Verificar si Redis está habilitado
        $redisEnabled = Env::get('REDIS_ENABLED', false);

        if (!$redisEnabled || !extension_loaded('redis')) {
            error_log("NotificationService: Redis no está habilitado o extensión no disponible");
            return null;
        }

        try {
            $redis = new \Redis();
            $host = Env::get('REDIS_HOST', '127.0.0.1');
            $port = (int)Env::get('REDIS_PORT', 6379);
            $timeout = (float)Env::get('REDIS_TIMEOUT', 2.5);

            $connected = $redis->connect($host, $port, $timeout);

            if (!$connected) {
                error_log("NotificationService: No se pudo conectar a Redis en {$host}:{$port}");
                return null;
            }

            // Autenticación si está configurada
            $password = Env::get('REDIS_PASSWORD', '');
            if ($password) {
                $redis->auth($password);
            }

            // Seleccionar base de datos
            $database = (int)Env::get('REDIS_DATABASE', 0);
            $redis->select($database);

            self::$redis = $redis;

            error_log("NotificationService: Conectado a Redis correctamente");

            return self::$redis;

        } catch (\Exception $e) {
            error_log("NotificationService: Error al conectar a Redis: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Crear y enviar notificación
     */
    public static function send(array $data): ?Notification
    {
        // Crear notificación en BD
        $notification = Notification::create($data);

        if (!$notification) {
            error_log("NotificationService: Error al crear notificación en BD");
            return null;
        }

        // Enviar vía WebSocket/Redis si está disponible
        self::sendViaWebSocket($notification);

        return $notification;
    }

    /**
     * Enviar notificación vía WebSocket usando Redis pub/sub
     */
    private static function sendViaWebSocket(Notification $notification): void
    {
        $redis = self::getRedis();

        if (!$redis) {
            error_log("NotificationService: No se puede enviar vía WebSocket (Redis no disponible)");
            return;
        }

        try {
            // Canal específico por usuario y tipo
            $channel = "notifications:{$notification->user_type}:{$notification->user_id}";

            // Publicar notificación
            $payload = json_encode($notification->toArray());

            $redis->publish($channel, $payload);

            error_log("NotificationService: Notificación enviada vía Redis al canal: {$channel}");

        } catch (\Exception $e) {
            error_log("NotificationService: Error al publicar en Redis: " . $e->getMessage());
        }
    }

    /**
     * Notificar creación de ticket
     */
    public static function notifyTicketCreated(int $ticketId, string $subject, int $tenantId): void
    {
        // Notificar a superadmins (pueden ver todos los tickets)
        self::notifySuperAdmins([
            'type' => 'ticket_created',
            'title' => 'Nuevo ticket de soporte',
            'message' => "Se ha creado un nuevo ticket: {$subject}",
            'data' => [
                'ticket_id' => $ticketId,
                'tenant_id' => $tenantId,
                'action_url' => '/musedock/tickets/' . $ticketId
            ]
        ]);
    }

    /**
     * Notificar respuesta a ticket
     */
    public static function notifyTicketReply(int $ticketId, string $subject, int $recipientId, string $recipientType): void
    {
        self::send([
            'user_id' => $recipientId,
            'user_type' => $recipientType,
            'type' => 'ticket_reply',
            'title' => 'Nueva respuesta en ticket',
            'message' => "Han respondido a tu ticket: {$subject}",
            'data' => [
                'ticket_id' => $ticketId,
                'action_url' => $recipientType === 'admin' ? admin_url('tickets/' . $ticketId) : '/musedock/tickets/' . $ticketId
            ]
        ]);
    }

    /**
     * Notificar asignación de ticket
     */
    public static function notifyTicketAssigned(int $ticketId, string $subject, int $assignedToId, string $assignedToType): void
    {
        self::send([
            'user_id' => $assignedToId,
            'user_type' => $assignedToType,
            'type' => 'ticket_assigned',
            'title' => 'Ticket asignado a ti',
            'message' => "Se te ha asignado el ticket: {$subject}",
            'data' => [
                'ticket_id' => $ticketId,
                'action_url' => $assignedToType === 'admin' ? admin_url('tickets/' . $ticketId) : '/musedock/tickets/' . $ticketId
            ]
        ]);
    }

    /**
     * Notificar cambio de estado de ticket
     */
    public static function notifyTicketStatusChanged(int $ticketId, string $subject, string $newStatus, int $recipientId, string $recipientType): void
    {
        $statusLabels = [
            'open' => 'Abierto',
            'in_progress' => 'En Progreso',
            'resolved' => 'Resuelto',
            'closed' => 'Cerrado'
        ];

        self::send([
            'user_id' => $recipientId,
            'user_type' => $recipientType,
            'type' => 'ticket_status_changed',
            'title' => 'Estado de ticket actualizado',
            'message' => "El ticket '{$subject}' cambió a: {$statusLabels[$newStatus]}",
            'data' => [
                'ticket_id' => $ticketId,
                'new_status' => $newStatus,
                'action_url' => $recipientType === 'admin' ? admin_url('tickets/' . $ticketId) : '/musedock/tickets/' . $ticketId
            ]
        ]);
    }

    /**
     * Notificar a todos los superadmins
     */
    private static function notifySuperAdmins(array $data): void
    {
        $db = \Screenart\Musedock\Database::connect();
        $stmt = $db->query("SELECT id FROM super_admins");
        $superAdmins = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($superAdmins as $admin) {
            self::send(array_merge($data, [
                'user_id' => $admin['id'],
                'user_type' => 'super_admin'
            ]));
        }
    }

    /**
     * Obtener notificaciones no leídas de un usuario
     */
    public static function getUnread(int $userId, string $userType, int $limit = 10): array
    {
        return Notification::getByUser($userId, $userType, true, $limit);
    }

    /**
     * Obtener conteo de notificaciones no leídas
     */
    public static function getUnreadCount(int $userId, string $userType): int
    {
        return Notification::getUnreadCount($userId, $userType);
    }

    /**
     * Marcar notificación como leída
     */
    public static function markAsRead(int $notificationId): bool
    {
        $notification = Notification::find($notificationId);

        if (!$notification) {
            return false;
        }

        return $notification->markAsRead();
    }

    /**
     * Marcar todas como leídas
     */
    public static function markAllAsRead(int $userId, string $userType): bool
    {
        return Notification::markAllAsRead($userId, $userType);
    }
}
