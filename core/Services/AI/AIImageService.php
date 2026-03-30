<?php

namespace Screenart\Musedock\Services\AI;

use Screenart\Musedock\Database;
use Screenart\Musedock\Services\AI\Models\Usage;

/**
 * AI Image Generation Service
 * Supports: OpenAI (DALL-E), MiniMax, Picalias, FAL
 */
class AIImageService
{
    /**
     * Generate an image with a specific provider
     *
     * @param int $providerId Provider ID from ai_image_providers table
     * @param string $prompt Text prompt for image generation
     * @param array $options ['size' => '1024x1024', 'style' => 'natural', 'quality' => 'standard', 'n' => 1]
     * @param array $metadata ['user_id', 'user_type', 'tenant_id', 'module', 'action']
     * @return array ['url' => string, 'local_path' => string, 'model' => string, 'provider' => string]
     */
    public static function generate(int $providerId, string $prompt, array $options = [], array $metadata = []): array
    {
        // Get provider config
        $provider = self::getProvider($providerId);
        if (!$provider) {
            throw new \Exception("Proveedor de imagen no encontrado (ID: {$providerId})");
        }
        if (!$provider['active']) {
            throw new \Exception("El proveedor de imagen '{$provider['name']}' no está activo");
        }

        // Check tenant limits
        $tenantId = $metadata['tenant_id'] ?? null;
        if ($tenantId) {
            $quota = Usage::hasTenantExceededDailyLimit($tenantId);
            if ($quota['exceeded']) {
                throw new \Exception("Se ha alcanzado el límite diario de uso de IA para este sitio");
            }
        }

        // Resolve API key (tenant key takes priority)
        $apiKey = self::resolveApiKey($provider, $tenantId);
        if (empty($apiKey)) {
            throw new \Exception("No hay API key configurada para el proveedor '{$provider['name']}'");
        }

        // Generate based on provider type
        $result = match ($provider['provider_type']) {
            'openai'   => self::generateOpenAI($apiKey, $prompt, $provider, $options),
            'minimax'  => self::generateMiniMax($apiKey, $prompt, $provider, $options),
            'picalias' => self::generatePicalias($apiKey, $prompt, $provider, $options),
            'fal'      => self::generateFAL($apiKey, $prompt, $provider, $options),
            default    => throw new \Exception("Tipo de proveedor de imagen no soportado: {$provider['provider_type']}")
        };

        // Download and save image locally
        $localPath = self::downloadAndSave($result['url'], $tenantId, $metadata);

        // Log usage
        try {
            $usage = new Usage();
            $usage->log([
                'provider_id' => $providerId,
                'provider_type' => $provider['provider_type'],
                'model' => $result['model'],
                'prompt_tokens' => mb_strlen($prompt),
                'completion_tokens' => 0,
                'total_tokens' => 1, // 1 image = 1 "token" for quota purposes
                'user_id' => $metadata['user_id'] ?? null,
                'user_type' => $metadata['user_type'] ?? null,
                'tenant_id' => $tenantId,
                'module' => $metadata['module'] ?? 'aiimage',
                'action' => $metadata['action'] ?? 'generate_image',
                'source' => $metadata['source'] ?? 'system_key',
            ]);
        } catch (\Exception $e) {
            error_log("[AIImage] Error logging usage: " . $e->getMessage());
        }

        return [
            'url' => $result['url'],
            'local_path' => $localPath,
            'model' => $result['model'],
            'provider' => $provider['name'],
            'provider_type' => $provider['provider_type'],
        ];
    }

    /**
     * Generate with the default image provider
     */
    public static function generateWithDefault(string $prompt, array $options = [], array $metadata = []): array
    {
        $provider = self::getDefaultProvider($metadata['tenant_id'] ?? null);
        if (!$provider) {
            throw new \Exception("No hay proveedor de imagen por defecto configurado");
        }
        return self::generate((int)$provider['id'], $prompt, $options, $metadata);
    }

    /**
     * OpenAI DALL-E generation
     */
    private static function generateOpenAI(string $apiKey, string $prompt, array $provider, array $options): array
    {
        $model = $options['model'] ?? $provider['model'] ?? 'dall-e-3';
        $size = $options['size'] ?? '1024x1024';
        $quality = $options['quality'] ?? 'standard';
        $style = $options['style'] ?? 'natural';

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'n' => 1,
            'size' => $size,
            'response_format' => 'url',
        ];

        // DALL-E 3 supports quality and style
        if ($model === 'dall-e-3') {
            $payload['quality'] = $quality;
            $payload['style'] = $style;
        }

        $endpoint = $provider['endpoint'] ?? 'https://api.openai.com/v1/images/generations';

        $response = self::httpPost($endpoint, $payload, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ]);

        if (!isset($response['data'][0]['url'])) {
            throw new \Exception("OpenAI no devolvió una imagen: " . json_encode($response));
        }

        return [
            'url' => $response['data'][0]['url'],
            'model' => $model,
        ];
    }

    /**
     * MiniMax image generation
     * API: https://api.minimax.io/v1/image_generation
     * Model: image-01
     * Aspect ratios: 1:1, 16:9, 4:3, 3:2, 2:3, 3:4, 9:16, 21:9
     * Cost: ~$0.01 per generation
     */
    private static function generateMiniMax(string $apiKey, string $prompt, array $provider, array $options): array
    {
        $model = $options['model'] ?? $provider['model'] ?? 'image-01';
        $size = $options['size'] ?? '1024x1024';

        // Convert WxH size to MiniMax aspect_ratio format
        $aspectRatioMap = [
            '1024x1024' => '1:1',
            '1280x720'  => '16:9',
            '720x1280'  => '9:16',
            '1152x864'  => '4:3',
            '864x1152'  => '3:4',
            '1248x832'  => '3:2',
            '832x1248'  => '2:3',
            '1344x576'  => '21:9',
            '1792x1024' => '16:9',
            '1024x1792' => '9:16',
            '512x512'   => '1:1',
        ];
        $aspectRatio = $aspectRatioMap[$size] ?? '1:1';

        $endpoint = $provider['endpoint'] ?? 'https://api.minimax.io/v1/image_generation';

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'aspect_ratio' => $aspectRatio,
            'response_format' => 'url',
            'n' => 1,
            'prompt_optimizer' => true,
        ];

        $response = self::httpPost($endpoint, $payload, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ]);

        // Check for API errors
        if (isset($response['base_resp']['status_code']) && $response['base_resp']['status_code'] !== 0) {
            throw new \Exception("MiniMax error: " . ($response['base_resp']['status_msg'] ?? 'Error desconocido'));
        }

        $url = $response['data']['image_urls'][0] ?? null;
        if (!$url) {
            throw new \Exception("MiniMax no devolvio una imagen: " . json_encode($response));
        }

        return [
            'url' => $url,
            'model' => $model,
        ];
    }

    /**
     * Picalias image generation (custom provider - placeholder for API instructions)
     */
    private static function generatePicalias(string $apiKey, string $prompt, array $provider, array $options): array
    {
        $model = $options['model'] ?? $provider['model'] ?? 'default';
        $size = $options['size'] ?? '1024x1024';

        $endpoint = $provider['endpoint'] ?? 'https://api.picalias.com/v1/generate';

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'size' => $size,
        ];

        $response = self::httpPost($endpoint, $payload, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ]);

        // Picalias response - adjust when API docs are provided
        $url = $response['data']['url'] ?? $response['url'] ?? $response['image_url'] ?? null;
        if (!$url) {
            throw new \Exception("Picalias no devolvió una imagen: " . json_encode($response));
        }

        return [
            'url' => $url,
            'model' => $model,
        ];
    }

    /**
     * FAL.ai image generation
     */
    private static function generateFAL(string $apiKey, string $prompt, array $provider, array $options): array
    {
        $model = $options['model'] ?? $provider['model'] ?? 'fal-ai/flux/schnell';
        $size = $options['size'] ?? '1024x1024';

        // Parse size to width/height
        $parts = explode('x', $size);
        $width = (int)($parts[0] ?? 1024);
        $height = (int)($parts[1] ?? 1024);

        $endpoint = $provider['endpoint'] ?? "https://fal.run/{$model}";

        $payload = [
            'prompt' => $prompt,
            'image_size' => [
                'width' => $width,
                'height' => $height,
            ],
            'num_images' => 1,
        ];

        $response = self::httpPost($endpoint, $payload, [
            'Authorization: Key ' . $apiKey,
            'Content-Type: application/json',
        ]);

        $url = $response['images'][0]['url'] ?? $response['data'][0]['url'] ?? null;
        if (!$url) {
            throw new \Exception("FAL no devolvió una imagen: " . json_encode($response));
        }

        return [
            'url' => $url,
            'model' => $model,
        ];
    }

    /**
     * Download image from URL and save via the Media Manager system.
     * This ensures: proper thumbnails (420px + 800px), storage quota tracking,
     * Flysystem compatibility (R2/S3-ready), and visibility in the File Manager.
     */
    private static function downloadAndSave(string $url, ?int $tenantId, array $metadata): string
    {
        // Download image via cURL
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($imageData === false || $httpCode >= 400) {
            throw new \Exception("No se pudo descargar la imagen generada" . ($curlError ? ": {$curlError}" : " (HTTP {$httpCode})"));
        }

        // Detect MIME type from content
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($imageData);
        $ext = match ($mime) {
            'image/png' => 'png',
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };

        // Save to a temp file so we can use the Media Manager's thumbnail system
        $tmpFile = tempnam(sys_get_temp_dir(), 'ai_img_');
        $tmpFileExt = $tmpFile . '.' . $ext;
        @unlink($tmpFile);
        file_put_contents($tmpFileExt, $imageData);

        try {
            // Use the Media Manager's upload infrastructure
            $mediaController = new \MediaManager\Controllers\MediaController();
            $result = $mediaController->uploadFromFile($tmpFileExt, $mime, $tenantId, [
                'original_name' => 'ai-generated-' . date('Ymd-His') . '.' . $ext,
                'user_id' => $metadata['user_id'] ?? null,
                'folder_id' => null,
                'disk' => 'media',
            ]);
        } finally {
            @unlink($tmpFileExt);
        }

        if (!$result || !isset($result['url'])) {
            throw new \Exception("Error al registrar la imagen IA en el Media Manager");
        }

        return $result['url'];
    }

    /**
     * Get provider by ID
     */
    public static function getProvider(int $id): ?array
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT * FROM ai_image_providers WHERE id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get default image provider
     */
    public static function getDefaultProvider(?int $tenantId = null): ?array
    {
        try {
            $pdo = Database::connect();

            // First check for tenant-specific default
            if ($tenantId) {
                $stmt = $pdo->prepare("SELECT * FROM ai_image_providers WHERE active = 1 AND (tenant_id = ? OR system_wide = 1) ORDER BY tenant_id DESC, id ASC LIMIT 1");
                $stmt->execute([$tenantId]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM ai_image_providers WHERE active = 1 AND (system_wide = 1 OR tenant_id IS NULL) ORDER BY id ASC LIMIT 1");
                $stmt->execute();
            }

            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get all active providers for a context
     */
    public static function getProviders(?int $tenantId = null): array
    {
        try {
            $pdo = Database::connect();

            if ($tenantId) {
                $stmt = $pdo->prepare("SELECT id, name, provider_type, model, active FROM ai_image_providers WHERE active = 1 AND (tenant_id = ? OR system_wide = 1) ORDER BY name ASC");
                $stmt->execute([$tenantId]);
            } else {
                $stmt = $pdo->prepare("SELECT id, name, provider_type, model, active FROM ai_image_providers WHERE active = 1 AND (system_wide = 1 OR tenant_id IS NULL) ORDER BY name ASC");
                $stmt->execute();
            }

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Resolve API key (tenant-specific key takes priority)
     */
    private static function resolveApiKey(array $provider, ?int $tenantId): ?string
    {
        // Check for tenant-specific key first
        if ($tenantId) {
            try {
                $pdo = Database::connect();
                $stmt = $pdo->prepare("SELECT api_key FROM ai_tenant_providers WHERE tenant_id = ? AND provider_type = ? AND active = 1 LIMIT 1");
                $stmt->execute([$tenantId, $provider['provider_type']]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row && !empty($row['api_key'])) {
                    return $row['api_key'];
                }
            } catch (\Exception $e) {
                // Fall through to system key
            }
        }

        return $provider['api_key'] ?? null;
    }

    /**
     * HTTP POST helper
     */
    private static function httpPost(string $url, array $data, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("Error de conexión con el proveedor de imagen: {$error}");
        }

        $decoded = json_decode($response, true);
        if ($decoded === null) {
            throw new \Exception("Respuesta inválida del proveedor de imagen (HTTP {$httpCode})");
        }

        if ($httpCode >= 400) {
            $errorMsg = $decoded['error']['message'] ?? $decoded['message'] ?? $decoded['error'] ?? 'Error desconocido';
            throw new \Exception("Error del proveedor de imagen (HTTP {$httpCode}): {$errorMsg}");
        }

        return $decoded;
    }
}
