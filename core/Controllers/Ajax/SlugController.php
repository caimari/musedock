<?php

namespace Screenart\Musedock\Controllers\Ajax;

use Screenart\Musedock\Services\SlugService;
use Screenart\Musedock\Services\TenantManager;
use Screenart\Musedock\Database;

class SlugController
{
    public function check()
    {
        // Sanitizar inputs
        $slug = trim($_POST['slug'] ?? '');
        $prefix = trim($_POST['prefix'] ?? 'p');
        $excludeId = isset($_POST['exclude_id']) ? (int)$_POST['exclude_id'] : null;

        // Validar formato de slug (solo letras, números, guiones)
        if (!preg_match('/^[a-z0-9\-_]+$/i', $slug)) {
            http_response_code(400);
            echo json_encode(['error' => 'Formato de slug inválido.']);
            exit;
        }

        // Validar formato de prefix (solo letras)
        if (!preg_match('/^[a-z]+$/i', $prefix)) {
            $prefix = 'p'; // Valor por defecto seguro
        }

        // SECURITY: Validar módulo contra whitelist para prevenir SQL injection
        $allowedModules = ['pages', 'blog', 'products', 'categories', 'tags', 'posts', 'galleries'];
        $inputModule = $_POST['module'] ?? 'pages';
        $module = in_array($inputModule, $allowedModules, true) ? $inputModule : 'pages';

        if (!$slug) {
            http_response_code(400);
            echo json_encode(['error' => 'Slug no proporcionado.']);
            exit;
        }

        try {
            // Obtener tenant actual (null para CMS global)
            $tenantId = TenantManager::currentTenantId();

            // Para galerías, consultar directamente la tabla galleries
            if ($module === 'galleries') {
                $exists = $this->checkGallerySlug($slug, $excludeId, $tenantId);
            } else {
                // Verificar slug solo dentro del mismo tenant/global
                $exists = SlugService::exists($slug, $prefix, $excludeId, $module, $tenantId);
            }

            header('Content-Type: application/json');
            echo json_encode(['exists' => $exists]);
            exit;

        } catch (\Throwable $e) {
            // En caso de error interno
            http_response_code(500);
            echo json_encode(['error' => 'Error interno del servidor.']);
            exit;
        }
    }

    /**
     * Verifica si un slug de galería ya existe
     */
    private function checkGallerySlug(string $slug, ?int $excludeId, ?int $tenantId): bool
    {
        // El módulo image-gallery usa la tabla image_galleries (no "galleries")
        $query = Database::table('image_galleries')->where('slug', $slug);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        // Filtrar por tenant_id
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            // Galerías globales: compatibilidad NULL/0
            $query->whereRaw('(tenant_id IS NULL OR tenant_id = 0)');
        }

        return $query->exists();
    }
}
