<?php

namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\View;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Services\TicketService;
use Screenart\Musedock\Models\Ticket;
use Screenart\Musedock\Traits\RequiresPermission;

class TicketsController
{
    use RequiresPermission;

    /**
     * Listar todos los tickets del tenant
     */
    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('tickets.view');

        // Verificar multi-tenant
        if (!$this->isMultiTenantEnabled()) {
            flash('error', 'El sistema de tickets solo está disponible en modo multi-tenant.');
            header('Location: ' . admin_url('dashboard'));
            exit;
        }

        $tenantId = $GLOBALS['tenant']['id'] ?? null;

        if (!$tenantId) {
            flash('error', 'No se pudo identificar el tenant.');
            header('Location: ' . admin_url('dashboard'));
            exit;
        }

        // Filtros
        $status = $_GET['status'] ?? null;
        $priority = $_GET['priority'] ?? null;

        $filters = [];
        if ($status) $filters['status'] = $status;
        if ($priority) $filters['priority'] = $priority;

        // Obtener tickets
        $tickets = TicketService::getTicketsByTenant($tenantId, $filters);

        // Estadísticas
        $stats = TicketService::getStatsByTenant($tenantId);

        return View::renderTenantAdmin('tickets.index', [
            'tickets' => $tickets,
            'stats' => $stats,
            'filters' => $filters
        ]);
    }

    /**
     * Mostrar formulario para crear ticket
     */
    public function create()
    {
        SessionSecurity::startSession();
        $this->checkPermission('tickets.create');

        if (!$this->isMultiTenantEnabled()) {
            flash('error', 'El sistema de tickets solo está disponible en modo multi-tenant.');
            header('Location: ' . admin_url('dashboard'));
            exit;
        }

        return View::renderTenantAdmin('tickets.create', [
            'priorities' => ['low', 'normal', 'high', 'urgent']
        ]);
    }

    /**
     * Guardar nuevo ticket
     */
    public function store()
    {
        SessionSecurity::startSession();
        $this->checkPermission('tickets.create');

        if (!$this->isMultiTenantEnabled()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Sistema de tickets no disponible']);
            exit;
        }

        $tenantId = $GLOBALS['tenant']['id'] ?? null;
        $admin = $_SESSION['admin'] ?? null;

        if (!$tenantId || !$admin) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Datos de sesión incompletos']);
            exit;
        }

        // Validar CSRF
        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
            exit;
        }

        // Validar datos
        $subject = trim($_POST['subject'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? 'normal';

        if (empty($subject) || empty($description)) {
            flash('error', 'El asunto y la descripción son obligatorios.');
            header('Location: ' . admin_url('tickets/create'));
            exit;
        }

        // Crear ticket
        $ticket = TicketService::createTicket([
            'tenant_id' => $tenantId,
            'admin_id' => $admin['id'],
            'subject' => $subject,
            'description' => $description,
            'priority' => $priority,
            'status' => 'open'
        ]);

        if ($ticket) {
            flash('success', 'Ticket creado exitosamente. Nuestro equipo lo revisará pronto.');
            header('Location: ' . admin_url('tickets/' . $ticket->id));
        } else {
            flash('error', 'Error al crear el ticket. Inténtalo de nuevo.');
            header('Location: ' . admin_url('tickets/create'));
        }

        exit;
    }

    /**
     * Ver detalles de un ticket
     */
    public function show(int $id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('tickets.view');

        if (!$this->isMultiTenantEnabled()) {
            flash('error', 'El sistema de tickets solo está disponible en modo multi-tenant.');
            header('Location: ' . admin_url('dashboard'));
            exit;
        }

        $tenantId = $GLOBALS['tenant']['id'] ?? null;
        $admin = $_SESSION['admin'] ?? null;

        // Obtener ticket con mensajes
        $ticket = TicketService::getTicketWithMessages($id);

        if (!$ticket) {
            flash('error', 'Ticket no encontrado.');
            header('Location: ' . admin_url('tickets'));
            exit;
        }

        // Verificar permisos de acceso
        if (!TicketService::canAccess($ticket, $admin['id'], 'admin', $tenantId)) {
            flash('error', 'No tienes permisos para ver este ticket.');
            header('Location: ' . admin_url('tickets'));
            exit;
        }

        return View::renderTenantAdmin('tickets.show', [
            'ticket' => $ticket
        ]);
    }

    /**
     * Agregar respuesta a un ticket
     */
    public function reply(int $id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('tickets.reply');

        if (!$this->isMultiTenantEnabled()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Sistema de tickets no disponible']);
            exit;
        }

        $tenantId = $GLOBALS['tenant']['id'] ?? null;
        $admin = $_SESSION['admin'] ?? null;

        // Validar CSRF
        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
            exit;
        }

        // Obtener ticket
        $ticket = Ticket::find($id);

        if (!$ticket) {
            flash('error', 'Ticket no encontrado.');
            header('Location: ' . admin_url('tickets'));
            exit;
        }

        // Verificar permisos
        if (!TicketService::canAccess($ticket, $admin['id'], 'admin', $tenantId)) {
            flash('error', 'No tienes permisos para responder este ticket.');
            header('Location: ' . admin_url('tickets'));
            exit;
        }

        // Validar mensaje
        $message = trim($_POST['message'] ?? '');

        if (empty($message)) {
            flash('error', 'El mensaje no puede estar vacío.');
            header('Location: ' . admin_url('tickets/' . $id));
            exit;
        }

        // Agregar respuesta
        $reply = TicketService::addReply($id, $admin['id'], 'admin', $message);

        if ($reply) {
            flash('success', 'Respuesta agregada exitosamente.');
        } else {
            flash('error', 'Error al agregar la respuesta.');
        }

        header('Location: ' . admin_url('tickets/' . $id));
        exit;
    }

    /**
     * Cambiar estado de un ticket
     */
    public function updateStatus(int $id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('tickets.update');

        if (!$this->isMultiTenantEnabled()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Sistema de tickets no disponible']);
            exit;
        }

        $tenantId = $GLOBALS['tenant']['id'] ?? null;
        $admin = $_SESSION['admin'] ?? null;

        // Validar CSRF
        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
            exit;
        }

        $ticket = Ticket::find($id);

        if (!$ticket) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Ticket no encontrado']);
            exit;
        }

        // Verificar permisos
        if (!TicketService::canAccess($ticket, $admin['id'], 'admin', $tenantId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Sin permisos']);
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
     * Eliminar ticket
     */
    public function delete(int $id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('tickets.delete');

        if (!$this->isMultiTenantEnabled()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Sistema de tickets no disponible']);
            exit;
        }

        $tenantId = $GLOBALS['tenant']['id'] ?? null;
        $admin = $_SESSION['admin'] ?? null;

        // Validar CSRF
        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
            exit;
        }

        $ticket = Ticket::find($id);

        if (!$ticket) {
            flash('error', 'Ticket no encontrado.');
            header('Location: ' . admin_url('tickets'));
            exit;
        }

        // Solo el creador puede eliminar
        if ($ticket->admin_id !== $admin['id']) {
            flash('error', 'Solo el creador puede eliminar este ticket.');
            header('Location: ' . admin_url('tickets'));
            exit;
        }

        $result = TicketService::deleteTicket($id);

        if ($result) {
            flash('success', 'Ticket eliminado exitosamente.');
        } else {
            flash('error', 'Error al eliminar el ticket.');
        }

        header('Location: ' . admin_url('tickets'));
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
