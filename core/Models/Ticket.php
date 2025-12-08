<?php

namespace Screenart\Musedock\Models;

use Screenart\Musedock\Database;

class Ticket
{
    public int $id;
    public int $tenant_id;
    public int $admin_id;
    public ?int $assigned_to = null;
    public string $subject;
    public string $description;
    public string $status = 'open';
    public string $priority = 'normal';
    public string $created_at;
    public string $updated_at;
    public ?string $resolved_at = null;

    // Relaciones cargadas
    public ?array $tenant = null;
    public ?array $admin = null;
    public ?array $assignedUser = null;
    public ?array $messages = null;

    /**
     * Crear un nuevo ticket
     */
    public static function create(array $data): ?self
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            INSERT INTO support_tickets
            (tenant_id, admin_id, subject, description, status, priority, assigned_to)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $result = $stmt->execute([
            $data['tenant_id'],
            $data['admin_id'],
            $data['subject'],
            $data['description'],
            $data['status'] ?? 'open',
            $data['priority'] ?? 'normal',
            $data['assigned_to'] ?? null
        ]);

        if ($result) {
            return self::find((int)$db->lastInsertId());
        }

        return null;
    }

    /**
     * Buscar ticket por ID
     */
    public static function find(int $id): ?self
    {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM support_tickets WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($data) {
            return self::hydrate($data);
        }

        return null;
    }

    /**
     * Obtener todos los tickets de un tenant
     */
    public static function getByTenant(int $tenantId, array $filters = []): array
    {
        $db = Database::connect();

        $query = "SELECT * FROM support_tickets WHERE tenant_id = ?";
        $params = [$tenantId];

        // Filtros opcionales
        if (isset($filters['status'])) {
            $query .= " AND status = ?";
            $params[] = $filters['status'];
        }

        if (isset($filters['priority'])) {
            $query .= " AND priority = ?";
            $params[] = $filters['priority'];
        }

        if (isset($filters['assigned_to'])) {
            $query .= " AND assigned_to = ?";
            $params[] = $filters['assigned_to'];
        }

        $query .= " ORDER BY created_at DESC";

        if (isset($filters['limit'])) {
            $query .= " LIMIT " . (int)$filters['limit'];
        }

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([self::class, 'hydrate'], $results);
    }

    /**
     * Obtener tickets asignados a un usuario
     */
    public static function getAssignedTo(int $userId): array
    {
        $db = Database::connect();
        $stmt = $db->prepare("
            SELECT * FROM support_tickets
            WHERE assigned_to = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId]);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([self::class, 'hydrate'], $results);
    }

    /**
     * Actualizar ticket
     */
    public function update(array $data): bool
    {
        $db = Database::connect();

        $fields = [];
        $params = [];

        foreach ($data as $key => $value) {
            if (in_array($key, ['subject', 'description', 'status', 'priority', 'assigned_to'])) {
                $fields[] = "$key = ?";
                $params[] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        // Si se resuelve, guardar timestamp
        if (isset($data['status']) && $data['status'] === 'resolved' && $this->status !== 'resolved') {
            $fields[] = "resolved_at = NOW()";
        }

        $params[] = $this->id;

        $sql = "UPDATE support_tickets SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);

        return $stmt->execute($params);
    }

    /**
     * Eliminar ticket
     */
    public function delete(): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("DELETE FROM support_tickets WHERE id = ?");
        return $stmt->execute([$this->id]);
    }

    /**
     * Cargar mensajes del ticket
     */
    public function loadMessages(): self
    {
        $this->messages = TicketMessage::getByTicket($this->id);
        return $this;
    }

    /**
     * Agregar mensaje al ticket
     */
    public function addMessage(int $userId, string $userType, string $message, bool $isInternal = false): ?TicketMessage
    {
        return TicketMessage::create([
            'ticket_id' => $this->id,
            'user_id' => $userId,
            'user_type' => $userType,
            'message' => $message,
            'is_internal' => $isInternal
        ]);
    }

    /**
     * Asignar ticket a un usuario
     */
    public function assignTo(?int $userId): bool
    {
        return $this->update(['assigned_to' => $userId]);
    }

    /**
     * Cambiar estado del ticket
     */
    public function changeStatus(string $status): bool
    {
        if (!in_array($status, ['open', 'in_progress', 'resolved', 'closed'])) {
            return false;
        }

        return $this->update(['status' => $status]);
    }

    /**
     * Obtener estadísticas de tickets por tenant
     */
    public static function getStats(int $tenantId): array
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
            FROM support_tickets
            WHERE tenant_id = ?
        ");

        $stmt->execute([$tenantId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Hidratar modelo desde array
     */
    private static function hydrate(array $data): self
    {
        $ticket = new self();
        $ticket->id = (int)$data['id'];
        $ticket->tenant_id = (int)$data['tenant_id'];
        $ticket->admin_id = (int)$data['admin_id'];
        $ticket->assigned_to = $data['assigned_to'] ? (int)$data['assigned_to'] : null;
        $ticket->subject = $data['subject'];
        $ticket->description = $data['description'];
        $ticket->status = $data['status'];
        $ticket->priority = $data['priority'];
        $ticket->created_at = $data['created_at'];
        $ticket->updated_at = $data['updated_at'];
        $ticket->resolved_at = $data['resolved_at'];

        return $ticket;
    }
}
