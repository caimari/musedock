<?php

namespace Blog\Requests;

use Screenart\Musedock\Services\TenantManager;

class BlogTagRequest
{
    public static function validate(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors[] = 'El nombre es obligatorio.';
        }

        if (empty($data['slug'])) {
            $errors[] = 'El slug es obligatorio.';
        }

        // Validar formato del slug con regex estricto
        if (isset($data['slug']) && !empty($data['slug'])) {
            if (!preg_match('/^[a-z0-9\\-]+$/', $data['slug'])) {
                $errors[] = 'El slug solo puede contener letras minúsculas, números y guiones.';
            }
            if (strlen($data['slug']) > 200) {
                $errors[] = 'El slug no puede exceder 200 caracteres.';
            }
        }

        // Verificar que el slug sea único dentro del mismo dominio:
        // - Tenant: tenant_id = {id}
        // - Global: tenant_id IS NULL
        if (!empty($data['slug'])) {
            try {
                $pdo = \Screenart\Musedock\Database::connect();
                $tenantId = array_key_exists('tenant_id', $data) ? $data['tenant_id'] : TenantManager::currentTenantId();
                $query = "SELECT COUNT(*) as count FROM blog_tags WHERE slug = ?";
                $params = [$data['slug']];

                if ($tenantId !== null && $tenantId !== '') {
                    $query .= " AND tenant_id = ?";
                    $params[] = (int) $tenantId;
                } else {
                    $query .= " AND tenant_id IS NULL";
                }

                if ($excludeId) {
                    $query .= " AND id != ?";
                    $params[] = $excludeId;
                }

                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($result['count'] > 0) {
                    $errors[] = 'El slug ya está en uso.';
                }
            } catch (\Exception $e) {
                error_log("Error al validar slug de etiqueta: " . $e->getMessage());
            }
        }

        return $errors;
    }
}
