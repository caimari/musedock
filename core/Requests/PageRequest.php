<?php

namespace Screenart\Musedock\Requests;

use Screenart\Musedock\Services\SlugService;
use Screenart\Musedock\Services\TenantManager;

class PageRequest
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
        $prefix = $data['prefix'] ?? 'p';

        if (!empty($data['slug']) && empty($errors)) {
            $slugExists = SlugService::exists(
                $data['slug'],
                $prefix,
                $excludeId,
                'pages'
            );

            if ($slugExists) {
                $errors[] = 'El slug ya está en uso.';
            }
        }

        return $errors;
    }
}
