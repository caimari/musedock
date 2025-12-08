<?php

namespace Blog\Requests;

class BlogCategoryRequest
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

        // Verificar que el slug sea único
        if (!empty($data['slug'])) {
            try {
                $pdo = \Screenart\Musedock\Database::connect();
                $query = "SELECT COUNT(*) as count FROM blog_categories WHERE slug = ? AND tenant_id IS NULL";
                $params = [$data['slug']];

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
                error_log("Error al validar slug de categoría: " . $e->getMessage());
            }
        }

        return $errors;
    }
}
