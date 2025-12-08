<?php

namespace Screenart\Musedock\Models;

use Screenart\Musedock\Database;

class TicketMessage
{
    public int $id;
    public int $ticket_id;
    public int $user_id;
    public string $user_type;
    public string $message;
    public bool $is_internal = false;
    public string $created_at;

    // Datos cargados
    public ?array $user = null;

    /**
     * Crear un nuevo mensaje
     */
    public static function create(array $data): ?self
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            INSERT INTO support_ticket_messages
            (ticket_id, user_id, user_type, message, is_internal)
            VALUES (?, ?, ?, ?, ?)
        ");

        $result = $stmt->execute([
            $data['ticket_id'],
            $data['user_id'],
            $data['user_type'],
            $data['message'],
            $data['is_internal'] ?? false
        ]);

        if ($result) {
            return self::find((int)$db->lastInsertId());
        }

        return null;
    }

    /**
     * Buscar mensaje por ID
     */
    public static function find(int $id): ?self
    {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM support_ticket_messages WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($data) {
            return self::hydrate($data);
        }

        return null;
    }

    /**
     * Obtener mensajes de un ticket
     */
    public static function getByTicket(int $ticketId, bool $includeInternal = true): array
    {
        $db = Database::connect();

        $query = "SELECT * FROM support_ticket_messages WHERE ticket_id = ?";
        $params = [$ticketId];

        if (!$includeInternal) {
            $query .= " AND is_internal = 0";
        }

        $query .= " ORDER BY created_at ASC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([self::class, 'hydrate'], $results);
    }

    /**
     * Cargar información del usuario que envió el mensaje
     */
    public function loadUser(): self
    {
        $db = Database::connect();

        if ($this->user_type === 'admin') {
            $stmt = $db->prepare("SELECT id, name, email FROM admins WHERE id = ?");
        } else {
            $stmt = $db->prepare("SELECT id, name, email FROM super_admins WHERE id = ?");
        }

        $stmt->execute([$this->user_id]);
        $this->user = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $this;
    }

    /**
     * Eliminar mensaje
     */
    public function delete(): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("DELETE FROM support_ticket_messages WHERE id = ?");
        return $stmt->execute([$this->id]);
    }

    /**
     * Hidratar modelo desde array
     */
    private static function hydrate(array $data): self
    {
        $message = new self();
        $message->id = (int)$data['id'];
        $message->ticket_id = (int)$data['ticket_id'];
        $message->user_id = (int)$data['user_id'];
        $message->user_type = $data['user_type'];
        $message->message = $data['message'];
        $message->is_internal = (bool)$data['is_internal'];
        $message->created_at = $data['created_at'];

        return $message;
    }
}
