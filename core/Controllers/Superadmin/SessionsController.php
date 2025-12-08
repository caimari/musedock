<?php
namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\Database;
use Screenart\Musedock\View;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Traits\RequiresPermission;

class SessionsController
{
    use RequiresPermission;

    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.manage');
        $db = Database::connect();

        // --- Sesiones persistentes (con token) ---
        $superadminSessions = $db->query("
            SELECT s.*, a.email, a.id AS user_id
            FROM super_admin_session_tokens s
            JOIN super_admins a ON a.id = s.super_admin_id
        ")->fetchAll();

        $adminSessions = $db->query("
            SELECT s.*, a.email, a.id AS user_id, t.name AS tenant_name
            FROM admin_session_tokens s
            JOIN admins a ON a.id = s.admin_id
            JOIN tenants t ON a.tenant_id = t.id
        ")->fetchAll();

        $userSessions = $db->query("
            SELECT s.*, u.email, u.id AS user_id, t.name AS tenant_name
            FROM user_session_tokens s
            JOIN users u ON u.id = s.user_id
            JOIN tenants t ON u.tenant_id = t.id
        ")->fetchAll();

        // --- Usuarios activos (última actividad) ---
        $superadminActives = $db->query("
            SELECT a.email, sa.*, a.id AS user_id
            FROM super_admin_activity sa
            JOIN super_admins a ON a.id = sa.super_admin_id
        ")->fetchAll();

        $adminActives = $db->query("
            SELECT a.email, aa.*, a.id AS user_id, t.name AS tenant_name
            FROM admin_activity aa
            JOIN admins a ON a.id = aa.admin_id
            JOIN tenants t ON a.tenant_id = t.id
        ")->fetchAll();

        $userActives = $db->query("
            SELECT u.email, ua.*, u.id AS user_id, t.name AS tenant_name
            FROM user_activity ua
            JOIN users u ON u.id = ua.user_id
            JOIN tenants t ON u.tenant_id = t.id
        ")->fetchAll();

        return View::renderSuperadmin('sessions.index', [
            'title' => 'Sesiones activas',
            'superadmin_sessions' => $superadminSessions,
            'admin_sessions'      => $adminSessions,
            'user_sessions'       => $userSessions,  // Añadido para usuarios regulares
            'superadmin_actives'  => $superadminActives,
            'admin_actives'       => $adminActives,
            'user_actives'        => $userActives,
        ]);
    }

    public function destroy($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('users.manage');

        $type = $_POST['user_type'] ?? null;
        $source = $_POST['source'] ?? 'token';
        $userId = $_POST['user_id'] ?? null;

        if (!$type || !in_array($type, ['admin', 'superadmin', 'user'])) {
            flash('error', 'Tipo de usuario no válido.');
            header("Location: /musedock/sessions");
            exit;
        }

        $db = Database::connect();

        if ($source === 'activity' && !$userId) {
            flash('error', 'ID de usuario no proporcionado.');
            header("Location: /musedock/sessions");
            exit;
        }

        if ($source === 'activity') {
            // Cada tipo tiene su propia tabla y estructura
            if ($type === 'superadmin') {
                $stmt = $db->prepare("DELETE FROM super_admin_activity WHERE super_admin_id = :user_id");
            } elseif ($type === 'admin') {
                $stmt = $db->prepare("DELETE FROM admin_activity WHERE admin_id = :user_id");
            } else { // user
                $stmt = $db->prepare("DELETE FROM user_activity WHERE user_id = :user_id");
            }
            
            $stmt->execute([
                'user_id' => $userId
            ]);
        } else {
            $tokenTable = match ($type) {
                'superadmin' => 'super_admin_session_tokens',
                'admin'      => 'admin_session_tokens',
                'user'       => 'user_session_tokens',
            };

            $tokenColumn = match ($type) {
                'superadmin' => 'super_admin_id',
                'admin'      => 'admin_id',
                'user'       => 'user_id',
            };

            // Verificar si estamos eliminando nuestro propio token
            if (isset($_COOKIE['remember_token'])) {
                $hash = hash('sha256', $_COOKIE['remember_token']);
                $check = $db->prepare("SELECT id FROM {$tokenTable} WHERE id = :id AND token = :token");
                $check->execute(['id' => $id, 'token' => $hash]);

                if ($check->fetch()) {
                    // Estamos eliminando nuestro propio token, cerramos la sesión actual
                    $stmt = $db->prepare("DELETE FROM {$tokenTable} WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    
                    SessionSecurity::destroy();
                    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
                    header("Location: /musedock/login");
                    exit;
                }
            }

            // Eliminamos el token solicitado
            $stmt = $db->prepare("DELETE FROM {$tokenTable} WHERE id = :id");
            $stmt->execute(['id' => $id]);
        }

        flash('success', 'Sesión cerrada correctamente.');
        header("Location: /musedock/sessions");
        exit;
    }
}