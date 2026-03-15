<?php
namespace Screenart\Musedock\Controllers\Api;

use Screenart\Musedock\Database;
use Screenart\Musedock\Services\AI\AIImageService;

class ApiAIImageController
{
    /**
     * Generate an image with AI
     */
    public function generate()
    {
        try {
            // Verify authentication
            $userType = null;
            $userId = null;

            if (isset($_SESSION['super_admin'])) {
                $userType = 'super_admin';
                $userId = $_SESSION['super_admin']['id'];
            } elseif (isset($_SESSION['admin'])) {
                $userType = 'admin';
                $userId = $_SESSION['admin']['id'];
            }

            if (!$userId) {
                $this->jsonResponse(['success' => false, 'message' => 'No has iniciado sesión'], 401);
                return;
            }

            // Get JSON input
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);

            if (!$data || empty($data['prompt'])) {
                $this->jsonResponse(['success' => false, 'message' => 'Se requiere un prompt para generar la imagen'], 400);
                return;
            }

            $tenantId = function_exists('tenant_id') ? tenant_id() : null;

            // Allow superadmin to specify tenant_id for proper scoping
            if (!$tenantId && $userType === 'super_admin' && !empty($data['tenant_id'])) {
                $tenantId = (int)$data['tenant_id'];
            }

            $options = [
                'size' => $data['size'] ?? '1024x1024',
                'quality' => $data['quality'] ?? 'standard',
                'style' => $data['style'] ?? 'natural',
                'model' => $data['model'] ?? null,
            ];

            $metadata = [
                'user_id' => $userId,
                'user_type' => $userType,
                'tenant_id' => $tenantId,
                'module' => $data['module'] ?? 'aiimage',
                'action' => 'generate_image',
                'source' => 'system_key',
            ];

            // Use specific provider or default
            if (!empty($data['provider_id'])) {
                $result = AIImageService::generate((int)$data['provider_id'], $data['prompt'], $options, $metadata);
            } else {
                $result = AIImageService::generateWithDefault($data['prompt'], $options, $metadata);
            }

            $this->jsonResponse([
                'success' => true,
                'image' => [
                    'url' => $result['url'],
                    'local_path' => $result['local_path'],
                    'model' => $result['model'],
                    'provider' => $result['provider'],
                ],
            ]);

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available image providers
     */
    public function providers()
    {
        try {
            $tenantId = function_exists('tenant_id') ? tenant_id() : null;
            $providers = AIImageService::getProviders($tenantId);

            $this->jsonResponse([
                'success' => true,
                'providers' => $providers,
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an AI-generated image
     */
    public function delete()
    {
        try {
            if (!isset($_SESSION['super_admin']) && !isset($_SESSION['admin'])) {
                $this->jsonResponse(['success' => false, 'message' => 'No autorizado'], 401);
                return;
            }

            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            $path = $data['path'] ?? '';

            // Only allow deleting AI-generated images (path must contain /ai_)
            if (empty($path) || strpos($path, '/ai_') === false) {
                $this->jsonResponse(['success' => false, 'message' => 'Ruta no valida'], 400);
                return;
            }

            // Convert /media/file/{scope}/{year}/{month}/{file} to storage path
            $storagePath = null;
            if (preg_match('#^/media/file/(.+)$#', $path, $m)) {
                $appRoot = defined('APP_ROOT') ? APP_ROOT : rtrim($_SERVER['DOCUMENT_ROOT'] ?? '/var/www/vhosts/musedock.com/httpdocs', '/');
                $storagePath = $appRoot . '/storage/app/media/' . $m[1];
            }

            if ($storagePath && file_exists($storagePath)) {
                unlink($storagePath);
                // Also delete thumbnail
                $dir = dirname($storagePath);
                $filename = basename($storagePath);
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                $thumbFile = $dir . '/thumbs/' . str_replace('.' . $ext, '_thumb.jpg', $filename);
                if (file_exists($thumbFile)) {
                    unlink($thumbFile);
                }
            }

            $this->jsonResponse(['success' => true]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
