<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Services\NotificationService;

class NotificationsController
{
    /**
     * Obtener notificaciones no leídas
     */
    public function getUnread()
    {
        try {
            SessionSecurity::startSession();

            $auth = SessionSecurity::getAuthenticatedUser();

            if (!$auth || $auth['type'] !== 'super_admin') {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'No autenticado']);
                exit;
            }

            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

            $notifications = NotificationService::getUnread($auth['id'], 'super_admin', $limit);

            echo json_encode([
                'success' => true,
                'notifications' => $notifications
            ]);

            exit;
        } catch (\Exception $e) {
            error_log("NotificationsController::getUnread Error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'notifications' => [],
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * Obtener conteo de notificaciones no leídas
     */
    public function getUnreadCount()
    {
        try {
            SessionSecurity::startSession();

            $auth = SessionSecurity::getAuthenticatedUser();

            if (!$auth || $auth['type'] !== 'super_admin') {
                http_response_code(401);
                echo json_encode(['success' => false, 'count' => 0]);
                exit;
            }

            $count = NotificationService::getUnreadCount($auth['id'], 'super_admin');

            echo json_encode([
                'success' => true,
                'count' => $count
            ]);

            exit;
        } catch (\Exception $e) {
            error_log("NotificationsController::getUnreadCount Error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'count' => 0,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * Marcar una notificación como leída
     */
    public function markAsRead(int $id)
    {
        SessionSecurity::startSession();

        $auth = SessionSecurity::getAuthenticatedUser();

        if (!$auth || $auth['type'] !== 'super_admin') {
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

        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Notificación marcada como leída' : 'Error al marcar notificación'
        ]);

        exit;
    }

    /**
     * Marcar todas las notificaciones como leídas
     */
    public function markAllAsRead()
    {
        SessionSecurity::startSession();

        $auth = SessionSecurity::getAuthenticatedUser();

        if (!$auth || $auth['type'] !== 'super_admin') {
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

        $result = NotificationService::markAllAsRead($auth['id'], 'super_admin');

        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Todas las notificaciones marcadas como leídas' : 'Error al marcar notificaciones'
        ]);

        exit;
    }
}
