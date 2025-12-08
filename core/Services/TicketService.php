<?php

namespace Screenart\Musedock\Services;

use Screenart\Musedock\Models\Ticket;
use Screenart\Musedock\Models\TicketMessage;

class TicketService
{
    /**
     * Crear un nuevo ticket
     */
    public static function createTicket(array $data): ?Ticket
    {
        // Validar datos requeridos
        if (empty($data['tenant_id']) || empty($data['admin_id']) ||
            empty($data['subject']) || empty($data['description'])) {
            return null;
        }

        // Crear ticket
        $ticket = Ticket::create($data);

        if (!$ticket) {
            return null;
        }

        // Notificar a superadmins
        NotificationService::notifyTicketCreated(
            $ticket->id,
            $ticket->subject,
            $ticket->tenant_id
        );

        error_log("TicketService: Ticket #{$ticket->id} creado exitosamente");

        return $ticket;
    }

    /**
     * Agregar respuesta a un ticket
     */
    public static function addReply(int $ticketId, int $userId, string $userType, string $message, bool $isInternal = false): ?TicketMessage
    {
        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            error_log("TicketService: Ticket #{$ticketId} no encontrado");
            return null;
        }

        // Crear mensaje
        $ticketMessage = $ticket->addMessage($userId, $userType, $message, $isInternal);

        if (!$ticketMessage) {
            return null;
        }

        // Actualizar timestamp del ticket
        $ticket->update(['updated_at' => date('Y-m-d H:i:s')]);

        // Notificar al creador del ticket si no es él quien responde
        if ($ticket->admin_id !== $userId && !$isInternal) {
            NotificationService::notifyTicketReply(
                $ticket->id,
                $ticket->subject,
                $ticket->admin_id,
                'admin'
            );
        }

        // Si está asignado a alguien y no es esa persona quien responde, notificar
        if ($ticket->assigned_to && $ticket->assigned_to !== $userId && !$isInternal) {
            // Determinar tipo de usuario asignado (podría ser admin o super_admin)
            $assignedUserType = 'super_admin'; // Por defecto, ajustar según lógica

            NotificationService::notifyTicketReply(
                $ticket->id,
                $ticket->subject,
                $ticket->assigned_to,
                $assignedUserType
            );
        }

        error_log("TicketService: Respuesta agregada al ticket #{$ticketId}");

        return $ticketMessage;
    }

    /**
     * Asignar ticket a un usuario
     */
    public static function assignTicket(int $ticketId, ?int $assignedToId): bool
    {
        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            return false;
        }

        $result = $ticket->assignTo($assignedToId);

        if ($result && $assignedToId) {
            // Notificar al usuario asignado
            NotificationService::notifyTicketAssigned(
                $ticket->id,
                $ticket->subject,
                $assignedToId,
                'super_admin' // Ajustar según lógica
            );

            error_log("TicketService: Ticket #{$ticketId} asignado a usuario #{$assignedToId}");
        }

        return $result;
    }

    /**
     * Cambiar estado de un ticket
     */
    public static function changeStatus(int $ticketId, string $newStatus): bool
    {
        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            return false;
        }

        $oldStatus = $ticket->status;
        $result = $ticket->changeStatus($newStatus);

        if ($result && $oldStatus !== $newStatus) {
            // Notificar al creador del ticket
            NotificationService::notifyTicketStatusChanged(
                $ticket->id,
                $ticket->subject,
                $newStatus,
                $ticket->admin_id,
                'admin'
            );

            error_log("TicketService: Estado del ticket #{$ticketId} cambiado de '{$oldStatus}' a '{$newStatus}'");
        }

        return $result;
    }

    /**
     * Obtener tickets de un tenant
     */
    public static function getTicketsByTenant(int $tenantId, array $filters = []): array
    {
        return Ticket::getByTenant($tenantId, $filters);
    }

    /**
     * Obtener ticket con mensajes
     */
    public static function getTicketWithMessages(int $ticketId): ?Ticket
    {
        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            return null;
        }

        $ticket->loadMessages();

        // Cargar información de usuario en cada mensaje
        foreach ($ticket->messages as $message) {
            $message->loadUser();
        }

        return $ticket;
    }

    /**
     * Verificar si un usuario puede acceder a un ticket
     */
    public static function canAccess(Ticket $ticket, int $userId, string $userType, ?int $tenantId = null): bool
    {
        // Superadmins pueden acceder a todos los tickets
        if ($userType === 'super_admin') {
            return true;
        }

        // Admins solo pueden acceder a tickets de su tenant
        if ($userType === 'admin') {
            return $ticket->tenant_id === $tenantId && (
                $ticket->admin_id === $userId || $ticket->assigned_to === $userId
            );
        }

        return false;
    }

    /**
     * Obtener estadísticas de tickets
     */
    public static function getStatsByTenant(int $tenantId): array
    {
        return Ticket::getStats($tenantId);
    }

    /**
     * Eliminar ticket
     */
    public static function deleteTicket(int $ticketId): bool
    {
        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            return false;
        }

        $result = $ticket->delete();

        if ($result) {
            error_log("TicketService: Ticket #{$ticketId} eliminado");
        }

        return $result;
    }
}
