<?php

namespace Screenart\Musedock\Controllers\Ajax;

use Screenart\Musedock\Services\SlugService;
use Screenart\Musedock\Services\TenantManager;

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
        $allowedModules = ['pages', 'blog', 'products', 'categories', 'tags', 'posts'];
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

            // Verificar slug solo dentro del mismo tenant/global
            $exists = SlugService::exists($slug, $prefix, $excludeId, $module, $tenantId);

            header('Content-Type: application/json');
            echo json_encode(['exists' => $exists]);
            exit;

        } catch (\Throwable $e) {
            // En caso de error interno
            http_response_code(500);
            echo json_encode(['error' => 'Error interno del servidor.']);
            // Puedes loguearlo si quieres:
            // \Screenart\Musedock\Logger::log('Slug AJAX error: ' . $e->getMessage(), 'ERROR');
            exit;
        }
    }
}
