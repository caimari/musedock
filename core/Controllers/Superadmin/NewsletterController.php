<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Traits\RequiresPermission;

class NewsletterController
{
    use RequiresPermission;

    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.view');

        $auth = SessionSecurity::getAuthenticatedUser();
        if (!$auth || ($auth['type'] ?? null) !== 'super_admin') {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $status = trim((string)($_GET['status'] ?? ''));
        $q = trim((string)($_GET['q'] ?? ''));

        $pdo = Database::connect();
        $sql = "SELECT ns.*, t.name AS tenant_name FROM newsletter_subscribers ns LEFT JOIN tenants t ON t.id = ns.tenant_id WHERE 1=1";
        $params = [];

        if ($status !== '') {
            $sql .= " AND ns.status = ?";
            $params[] = $status;
        }
        if ($q !== '') {
            $sql .= " AND (ns.email LIKE ? OR ns.name LIKE ?)";
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }

        $sql .= " ORDER BY ns.created_at DESC LIMIT 500";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(\PDO::FETCH_OBJ);

        return View::renderSuperadmin('newsletter/index', [
            'title' => 'Newsletter',
            'items' => $items,
            'status' => $status,
            'q' => $q,
        ]);
    }
}
