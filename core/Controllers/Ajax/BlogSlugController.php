<?php

namespace Screenart\Musedock\Controllers\Ajax;

use Screenart\Musedock\Database;
use Screenart\Musedock\Services\TenantManager;

class BlogSlugController
{
    public function checkCategory()
    {
        $this->checkInTable('blog_categories');
    }

    public function checkTag()
    {
        $this->checkInTable('blog_tags');
    }

    private function checkInTable(string $table): void
    {
        header('Content-Type: application/json');

        $slug = trim($_POST['slug'] ?? '');
        $excludeId = isset($_POST['exclude_id']) ? (int) $_POST['exclude_id'] : null;

        if ($slug === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Slug no proporcionado.']);
            exit;
        }

        if (!preg_match('/^[a-z0-9\\-]+$/', $slug)) {
            http_response_code(400);
            echo json_encode(['error' => 'Formato de slug inválido.']);
            exit;
        }

        try {
            $pdo = Database::connect();
            $tenantId = TenantManager::currentTenantId();

            $sql = "SELECT COUNT(*) as count FROM {$table} WHERE slug = :slug";
            $params = [':slug' => $slug];

            if ($tenantId !== null) {
                $sql .= " AND tenant_id = :tenant_id";
                $params[':tenant_id'] = $tenantId;
            } else {
                $sql .= " AND tenant_id IS NULL";
            }

            if ($excludeId) {
                $sql .= " AND id != :exclude_id";
                $params[':exclude_id'] = $excludeId;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            $exists = ((int) ($result['count'] ?? 0)) > 0;
            echo json_encode(['exists' => $exists]);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error interno del servidor.']);
            exit;
        }
    }
}

