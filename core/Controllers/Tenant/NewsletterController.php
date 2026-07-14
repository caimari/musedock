<?php

namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Security\SessionSecurity;

class NewsletterController
{
    public function index()
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['admin']) && !isset($_SESSION['user'])) {
            http_response_code(403);
            exit('Acceso denegado');
        }

        $tenantId = tenant_id();
        if (!$tenantId) {
            http_response_code(400);
            exit('Tenant no detectado');
        }

        $status = trim((string)($_GET['status'] ?? ''));
        $q = trim((string)($_GET['q'] ?? ''));

        $pdo = Database::connect();
        $sql = "SELECT * FROM newsletter_subscribers WHERE tenant_id = ?";
        $params = [$tenantId];

        if ($status !== '') {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        if ($q !== '') {
            $sql .= " AND (email LIKE ? OR name LIKE ?)";
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }

        $sql .= " ORDER BY created_at DESC LIMIT 500";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(\PDO::FETCH_OBJ);

        return View::renderTenantAdmin('newsletter/index', [
            'title' => 'Newsletter',
            'items' => $items,
            'status' => $status,
            'q' => $q,
        ]);
    }
}
