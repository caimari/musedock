<?php

namespace Blog\Requests;

use Screenart\Musedock\Services\SlugService;
use Screenart\Musedock\Services\TenantManager;

class BlogPostRequest
{
    public static function validate(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        if (empty($data['title'])) {
            $errors[] = 'El título es obligatorio.';
        }

        if (empty($data['slug'])) {
            $errors[] = 'El slug es obligatorio.';
        }

        // Validar formato del slug con regex estricto
        if (isset($data['slug']) && !empty($data['slug'])) {
            if (!preg_match('/^[a-z0-9\-]+$/', $data['slug'])) {
                $errors[] = 'El slug solo puede contener letras minúsculas, números y guiones.';
            }
            if (strlen($data['slug']) > 200) {
                $errors[] = 'El slug no puede exceder 200 caracteres.';
            }
        }

        $tenantId = TenantManager::currentTenantId();
        $prefix = $data['prefix'] ?? 'blog';

        if (!empty($data['slug']) && empty($errors)) {
            $slugExists = SlugService::exists(
                $data['slug'],
                $prefix,
                $excludeId,
                'blog',
                $tenantId
            );

            if ($slugExists) {
                $errors[] = 'El slug ya está en uso.';
            }
        }

        // Validar status
        if (isset($data['status']) && !in_array($data['status'], ['draft', 'published'])) {
            $errors[] = 'El estado debe ser "draft" o "published".';
        }

        // Validar visibility
        if (isset($data['visibility']) && !in_array($data['visibility'], ['public', 'private', 'password'])) {
            $errors[] = 'La visibilidad debe ser "public", "private" o "password".';
        }

        return $errors;
    }
}
