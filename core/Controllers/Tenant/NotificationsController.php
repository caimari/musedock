<?php

namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Services\NotificationService;

class NotificationsController
{
    /**
     * Obtener notificaciones no leídas (API)
     */
    public function getUnread()
    {
        SessionSecurity::startSession();

        $admin = $_SESSION['admin'] ?? null;

        if (!$admin) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No autenticado']);
            exit;
        }

        $limit = (int)($_GET['limit'] ?? 10);
        $notifications = NotificationService::getUnread($admin['id'], 'admin', $limit);

        // Convertir a array
        $notificationsArray = array_map(function($notification) {
            return $notification->toArray();
        }, $notifications);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'notifications' => $notificationsArray,
            'unread_count' => NotificationService::getUnreadCount($admin['id'], 'admin')
        ]);
        exit;
    }

    /**
     * Obtener conteo de notificaciones no leídas (API)
     */
    public function getUnreadCount()
    {
        SessionSecurity::startSession();

        $admin = $_SESSION['admin'] ?? null;

        if (!$admin) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No autenticado']);
            exit;
        }

        $count = NotificationService::getUnreadCount($admin['id'], 'admin');

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'count' => $count
        ]);
        exit;
    }

    /**
     * Marcar notificación como leída (API)
     */
    public function markAsRead(int $id)
    {
        SessionSecurity::startSession();

        $admin = $_SESSION['admin'] ?? null;

        if (!$admin) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No autenticado']);
            exit;
        }

        // Validar CSRF
        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
            exit;
        }

        $result = NotificationService::markAsRead($id);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Notificación marcada como leída' : 'Error al marcar'
        ]);
        exit;
    }

    /**
     * Marcar todas las notificaciones como leídas (API)
     */
    public function markAllAsRead()
    {
        SessionSecurity::startSession();

        $admin = $_SESSION['admin'] ?? null;

        if (!$admin) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No autenticado']);
            exit;
        }

        // Validar CSRF
        if (!validate_csrf($_POST['_csrf'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
            exit;
        }

        $result = NotificationService::markAllAsRead($admin['id'], 'admin');

        header('Content-Type: application/json');
        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Todas las notificaciones marcadas como leídas' : 'Error'
        ]);
        exit;
    }
}
