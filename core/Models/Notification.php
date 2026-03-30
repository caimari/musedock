<?php

namespace Screenart\Musedock\Models;

use Screenart\Musedock\Database;

class Notification
{
    public int $id;
    public int $user_id;
    public string $user_type;
    public string $type;
    public string $title;
    public string $message;
    public ?array $data = null;
    public bool $is_read = false;
    public string $created_at;
    public ?string $read_at = null;

    /**
     * Crear una nueva notificación
     */
    public static function create(array $notificationData): ?self
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            INSERT INTO notifications
            (user_id, user_type, type, title, message, data, is_read)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $dataJson = isset($notificationData['data']) ? json_encode($notificationData['data']) : null;

        $result = $stmt->execute([
            $notificationData['user_id'],
            $notificationData['user_type'],
            $notificationData['type'],
            $notificationData['title'],
            $notificationData['message'],
            $dataJson,
            $notificationData['is_read'] ?? false
        ]);

        if ($result) {
            return self::find((int)$db->lastInsertId());
        }

        return null;
    }

    /**
     * Buscar notificación por ID
     */
    public static function find(int $id): ?self
    {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM notifications WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($data) {
            return self::hydrate($data);
        }

        return null;
    }

    /**
     * Obtener notificaciones de un usuario
     */
    public static function getByUser(int $userId, string $userType, bool $onlyUnread = false, int $limit = 50): array
    {
        $db = Database::connect();

        $query = "SELECT * FROM notifications WHERE user_id = ? AND user_type = ?";
        $params = [$userId, $userType];

        if ($onlyUnread) {
            $query .= " AND is_read = 0";
        }

        // LIMIT no puede usarse con placeholder en PDO con emulación deshabilitada
        // Como $limit ya está tipado como int, es seguro usarlo directamente
        $query .= " ORDER BY created_at DESC LIMIT " . (int)$limit;

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([self::class, 'hydrate'], $results);
    }

    /**
     * Obtener cantidad de notificaciones no leídas
     */
    public static function getUnreadCount(int $userId, string $userType): int
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT COUNT(*) as count
            FROM notifications
            WHERE user_id = ? AND user_type = ? AND is_read = 0
        ");

        $stmt->execute([$userId, $userType]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int)$result['count'];
    }

    /**
     * Marcar notificación como leída
     */
    public function markAsRead(): bool
    {
        if ($this->is_read) {
            return true;
        }

        $db = Database::connect();

        $stmt = $db->prepare("
            UPDATE notifications
            SET is_read = 1, read_at = NOW()
            WHERE id = ?
        ");

        $result = $stmt->execute([$this->id]);

        if ($result) {
            $this->is_read = true;
            $this->read_at = date('Y-m-d H:i:s');
        }

        return $result;
    }

    /**
     * Marcar todas las notificaciones de un usuario como leídas
     */
    public static function markAllAsRead(int $userId, string $userType): bool
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            UPDATE notifications
            SET is_read = 1, read_at = NOW()
            WHERE user_id = ? AND user_type = ? AND is_read = 0
        ");

        return $stmt->execute([$userId, $userType]);
    }

    /**
     * Eliminar notificación
     */
    public function delete(): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("DELETE FROM notifications WHERE id = ?");
        return $stmt->execute([$this->id]);
    }

    /**
     * Eliminar notificaciones antiguas (limpieza)
     */
    public static function deleteOldNotifications(int $daysOld = 30): int
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            DELETE FROM notifications
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            AND is_read = 1
        ");

        $stmt->execute([$daysOld]);

        return $stmt->rowCount();
    }

    /**
     * Convertir a array para JSON
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_type' => $this->user_type,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data,
            'is_read' => $this->is_read,
            'created_at' => $this->created_at,
            'read_at' => $this->read_at
        ];
    }

    /**
     * Hidratar modelo desde array
     */
    private static function hydrate(array $data): self
    {
        $notification = new self();
        $notification->id = (int)$data['id'];
        $notification->user_id = (int)$data['user_id'];
        $notification->user_type = $data['user_type'];
        $notification->type = $data['type'];
        $notification->title = $data['title'];
        $notification->message = $data['message'];
        $notification->data = isset($data['data']) ? json_decode($data['data'], true) : null;
        $notification->is_read = (bool)$data['is_read'];
        $notification->created_at = $data['created_at'];
        $notification->read_at = $data['read_at'];

        return $notification;
    }
}
