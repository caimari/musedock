<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Services\TicketService;
use Screenart\Musedock\Models\Ticket;
use Screenart\Musedock\Database;
use Screenart\Musedock\Traits\RequiresPermission;

class TicketsController
{
    use RequiresPermission;

    /**
     * Listar todos los tickets (de todos los tenants)
     */
    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('tickets.manage');

        // Verificar multi-tenant
        if (!$this->isMultiTenantEnabled()) {
            flash('error', 'El sistema de tickets solo está disponible en modo multi-tenant.');
            header('Location: /musedock/dashboard');
            exit;
        }

        // Filtros
        $tenantId = $_GET['tenant_id'] ?? null;
        $status = $_GET['status'] ?? null;
        $priority = $_GET['priority'] ?? null;
        $assigned_to = $_GET['assigned_to'] ?? null;

        // Obtener todos los tickets
        $db = Database::connect();

        $query = "SELECT t.*, tn.name as tenant_name, tn.domain as tenant_domain
                  FROM support_tickets t
                  LEFT JOIN tenants tn ON t.tenant_id = tn.id
                  WHERE 1=1";
        $params = [];

        if ($tenantId) {
            $query .= " AND t.tenant_id = ?";
            $params[] = $tenantId;
        }

        if ($status) {
            $query .= " AND t.status = ?";
            $params[] = $status;
        }

        if ($priority) {
            $query .= " AND t.priority = ?";
            $params[] = $priority;
        }

        if ($assigned_to) {
            if ($assigned_to === 'unassigned') {
                $query .= " AND t.assigned_to IS NULL";
            } else {
                $query .= " AND t.assigned_to = ?";
                $params[] = $assigned_to;
            }
        }

        $query .= " ORDER BY
                    CASE t.priority
                        WHEN 'urgent' THEN 1
                        WHEN 'high' THEN 2
                        WHEN 'normal' THEN 3
                        WHEN 'low' THEN 4
                    END,
                    t.created_at DESC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $tickets = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Estadísticas globales
        $statsStmt = $db->query("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
                SUM(CASE WHEN assigned_to IS NULL THEN 1 ELSE 0 END) as unassigned
            FROM support_tickets
        ");
        $stats = $statsStmt->fetch(\PDO::FETCH_ASSOC);

        // Obtener lista de tenants para filtro
        $tenantsStmt = $db->query("SELECT id, name, domain FROM tenants ORDER BY name");
        $tenants = $tenantsStmt->fetchAll(\PDO::FETCH_ASSOC);

        // Obtener lista de superadmins para asignación
        $adminsStmt = $db->query("SELECT id, name FROM super_admins ORDER BY name");
        $superAdmins = $adminsStmt->fetchAll(\PDO::FETCH_ASSOC);

        return View::renderSuperadmin('tickets.index', [
            'tickets' => $tickets,
            'stats' => $stats,
            'tenants' => $tenants,
            'superAdmins' => $superAdmins,
            'filters' => [
                'tenant_id' => $tenantId,
                'status' => $status,
                'priority' => $priority,
                'assigned_to' => $assigned_to
            ]
        ]);
    }

    /**
     * Ver detalles de un ticket
     */
    public function show(int $id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('tickets.manage');

        if (!$this->isMultiTenantEnabled()) {
            flash('error', 'El sistema de tickets solo está disponible en modo multi-tenant.');
            header('Location: /musedock/dashboard');
            exit;
        }

        // Obtener ticket con mensajes
        $ticket = TicketService::getTicketWithMessages($id);

        if (!$ticket) {
            flash('error', 'Ticket no encontrado.');
            header('Location: /musedock/tickets');
            exit;
        }

        // Obtener información del tenant
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
        $stmt->execute([$ticket->tenant_id]);
        $tenant = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Obtener superadmins para asignación
        $adminsStmt = $db->query("SELECT id, name FROM super_admins ORDER BY name");
        $superAdmins = $adminsStmt->fetchAll(\PDO::FETCH_ASSOC);

        return View::renderSuperadmin('tickets.show', [
            'ticket' => $ticket,
            'tenant' => $tenant,
            'superAdmins' => $superAdmins
        ]);
    }

    /**
     * Agregar respuesta a un ticket
     */
    public function reply(int $id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('tickets.manage');

        if (!$this->isMultiTenantEnabled()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Sistema de tickets no disponible']);
            exit;
        }

        $superAdmin = $_SESSION['super_admin'] ?? null;

        // Validar CSRF
        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
            exit;
        }

        // Validar mensaje
        $message = trim($_POST['message'] ?? '');
        $isInternal = isset($_POST['is_internal']) && $_POST['is_internal'] === '1';

        if (empty($message)) {
            flash('error', 'El mensaje no puede estar vacío.');
            header('Location: /musedock/tickets/' . $id);
            exit;
        }

        // Agregar respuesta
        $reply = TicketService::addReply($id, $superAdmin['id'], 'super_admin', $message, $isInternal);

        if ($reply) {
            flash('success', 'Respuesta agregada exitosamente.');
        } else {
            flash('error', 'Error al agregar la respuesta.');
        }

        header('Location: /musedock/tickets/' . $id);
        exit;
    }

    /**
     * Cambiar estado de un ticket
     */
    public function updateStatus(int $id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('tickets.manage');

        if (!$this->isMultiTenantEnabled()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Sistema de tickets no disponible']);
            exit;
        }

        // Validar CSRF
        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
            exit;
        }

        $newStatus = $_POST['status'] ?? '';

        if (!in_array($newStatus, ['open', 'in_progress', 'resolved', 'closed'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Estado inválido']);
            exit;
        }

        $result = TicketService::changeStatus($id, $newStatus);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Estado actualizado']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error al actualizar']);
        }

        exit;
    }

    /**
     * Asignar ticket a un superadmin
     */
    public function assign(int $id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('tickets.manage');

        if (!$this->isMultiTenantEnabled()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Sistema de tickets no disponible']);
            exit;
        }

        // Validar CSRF
        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
            exit;
        }

        $assignedTo = $_POST['assigned_to'] ?? null;

        // Si es vacío, desasignar
        if ($assignedTo === '' || $assignedTo === 'unassigned') {
            $assignedTo = null;
        } else {
            $assignedTo = (int)$assignedTo;
        }

        $result = TicketService::assignTicket($id, $assignedTo);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Ticket asignado correctamente']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error al asignar']);
        }

        exit;
    }

    /**
     * Eliminar ticket (solo superadmin)
     */
    public function delete(int $id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('tickets.manage');

        if (!$this->isMultiTenantEnabled()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Sistema de tickets no disponible']);
            exit;
        }

        // Validar CSRF
        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
            exit;
        }

        $result = TicketService::deleteTicket($id);

        if ($result) {
            flash('success', 'Ticket eliminado exitosamente.');
        } else {
            flash('error', 'Error al eliminar el ticket.');
        }

        header('Location: /musedock/tickets');
        exit;
    }

    /**
     * Verificar si el sistema multi-tenant está habilitado
     */
    private function isMultiTenantEnabled(): bool
    {
        return setting('multi_tenant_enabled', config('multi_tenant_enabled', false));
    }
}
