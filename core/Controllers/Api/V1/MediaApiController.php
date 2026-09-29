<?php

namespace Screenart\Musedock\Controllers\Api\V1;

use Screenart\Musedock\Database;
use Screenart\Musedock\Middlewares\ApiKeyAuth;
use Screenart\Musedock\SafeHtml;
use Screenart\Musedock\Security\UrlGuard;
use Screenart\Musedock\Services\AI\AIImageService;

/**
 * API v1 — Medios (biblioteca de imágenes del tenant).
 *
 *   GET  /api/v1/media           listar imágenes
 *   POST /api/v1/media/generate  generar una imagen con IA (proveedor del sitio) y guardarla
 *   POST /api/v1/media/upload    descargar una imagen pública por URL y guardarla
 *
 * Las respuestas incluyen la URL y un <img> listo para insertar en el contenido
 * de un post/página, o para usar como imagen destacada (featured_image_url).
 */
class MediaApiController
{
    private const ALLOWED_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    private const MAX_UPLOAD_BYTES = 10485760;
    private const SIZES = ['1024x1024', '1792x1024', '1024x1792', '1536x1024', '1024x1536'];

    // =========================================================================
    // GET /api/v1/media
    // =========================================================================
    public function index()
    {
        ApiKeyAuth::requirePermission('media.read');
        $tenantId = $this->tenantId();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($_GET['per_page'] ?? 20)));
        $search = trim((string) ($_GET['search'] ?? ''));

        $pdo = Database::connect();
        $where = "tenant_id = ? AND mime_type LIKE 'image/%'";
        $params = [$tenantId];
        if ($search !== '') {
            $where .= " AND (LOWER(filename) LIKE ? OR LOWER(COALESCE(alt_text, '')) LIKE ?)";
            $params[] = '%' . mb_strtolower($search) . '%';
            $params[] = '%' . mb_strtolower($search) . '%';
        }

        $count = $pdo->prepare("SELECT COUNT(*) FROM media WHERE {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $stmt = $pdo->prepare("SELECT id FROM media WHERE {$where} ORDER BY id DESC LIMIT " . $perPage . " OFFSET " . (($page - 1) * $perPage));
        $stmt->execute($params);

        $items = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $id) {
            $media = \MediaManager\Models\Media::find((int) $id);
            if ($media) {
                $items[] = $this->present($media);
            }
        }

        echo json_encode([
            'success'    => true,
            'media'      => $items,
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => (int) ceil($total / $perPage)],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // =========================================================================
    // POST /api/v1/media/generate
    // =========================================================================
    public function generate()
    {
        ApiKeyAuth::requirePermission('media.create');
        $tenantId = $this->tenantId();
        $input = ApiKeyAuth::getJsonInput();

        $prompt = trim(SafeHtml::plain($input['prompt'] ?? ''));
        if ($prompt === '' || mb_strlen($prompt) > 4000) {
            ApiKeyAuth::respond(422, 'VALIDATION_ERROR', 'prompt is required (max 4000 characters).');
        }

        $size = in_array($input['size'] ?? '', self::SIZES, true) ? $input['size'] : '1024x1024';
        $options = ['size' => $size];
        if (in_array($input['quality'] ?? '', ['standard', 'hd'], true)) {
            $options['quality'] = $input['quality'];
        }

        if (!AIImageService::getDefaultProvider($tenantId)) {
            ApiKeyAuth::respond(422, 'NO_IMAGE_PROVIDER', 'This website has no AI image provider configured. A site admin must set one up in IA → AI Image.');
        }

        $key = ApiKeyAuth::key();
        try {
            $result = AIImageService::generateWithDefault($prompt, $options, [
                'tenant_id' => $tenantId,
                'user_id'   => null,
                'user_type' => 'api',
                'module'    => 'mcp',
                'action'    => 'generate_image',
                'source'    => 'api_key:' . $key->id,
            ]);
        } catch (\Throwable $e) {
            error_log('API media/generate: ' . $e->getMessage());
            // El mensaje del servicio es para humanos (cuota, proveedor inactivo...), sin datos sensibles
            ApiKeyAuth::respond(502, 'IMAGE_GENERATION_FAILED', mb_substr($e->getMessage(), 0, 300));
        }

        $media = $this->findByUrl($tenantId, (string) ($result['local_path'] ?? ''));
        if (!$media) {
            ApiKeyAuth::respond(500, 'MEDIA_NOT_SAVED', 'The image was generated but could not be saved to the media library.');
        }

        $this->setAltText($media, $input['alt_text'] ?? $prompt);

        http_response_code(201);
        echo json_encode([
            'success'  => true,
            'media'    => $this->present(\MediaManager\Models\Media::find((int) $media->id)),
            'provider' => $result['provider'] ?? null,
            'model'    => $result['model'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // =========================================================================
    // POST /api/v1/media/upload
    // =========================================================================
    public function upload()
    {
        ApiKeyAuth::requirePermission('media.create');
        $tenantId = $this->tenantId();
        $input = ApiKeyAuth::getJsonInput();

        $url = trim((string) ($input['url'] ?? ''));
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            ApiKeyAuth::respond(422, 'VALIDATION_ERROR', 'url must be a valid http(s) URL.');
        }

        // Solo IPs públicas (anti-SSRF), tamaño máximo 10 MB
        $data = UrlGuard::fetch($url, self::MAX_UPLOAD_BYTES, 20);
        if ($data === null) {
            ApiKeyAuth::respond(422, 'DOWNLOAD_FAILED', 'The image could not be downloaded (not public, too large, or unreachable).');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($data);
        if (!isset(self::ALLOWED_MIMES[$mime])) {
            ApiKeyAuth::respond(422, 'INVALID_IMAGE', 'Only JPEG, PNG, WebP and GIF images are allowed.');
        }
        if (@getimagesizefromstring($data) === false) {
            ApiKeyAuth::respond(422, 'INVALID_IMAGE', 'The file is not a valid image.');
        }

        $ext = self::ALLOWED_MIMES[$mime];
        $baseName = pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME);
        $baseName = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($input['filename'] ?? $baseName)) ?: 'image';

        $tmp = tempnam(sys_get_temp_dir(), 'api_img_');
        $tmpExt = $tmp . '.' . $ext;
        @unlink($tmp);
        file_put_contents($tmpExt, $data);

        try {
            $result = (new \MediaManager\Controllers\MediaController())->uploadFromFile($tmpExt, $mime, $tenantId, [
                'original_name' => mb_substr($baseName, 0, 80) . '.' . $ext,
                'user_id'       => null,
                'folder_id'     => null,
                'disk'          => 'media',
            ]);
        } finally {
            @unlink($tmpExt);
        }

        if (empty($result['id'])) {
            ApiKeyAuth::respond(500, 'MEDIA_NOT_SAVED', 'The image could not be saved to the media library.');
        }

        $media = \MediaManager\Models\Media::find((int) $result['id']);
        $this->setAltText($media, $input['alt_text'] ?? null);

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'media'   => $this->present(\MediaManager\Models\Media::find((int) $result['id'])),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function tenantId(): int
    {
        $tenantId = ApiKeyAuth::resolveTenantId();
        if (!$tenantId) {
            ApiKeyAuth::respond(422, 'VALIDATION_ERROR', 'tenant_id is required.');
        }
        if (!class_exists(\MediaManager\Controllers\MediaController::class)) {
            ApiKeyAuth::respond(503, 'MEDIA_UNAVAILABLE', 'The media library module is not active on this website.');
        }
        return (int) $tenantId;
    }

    private function findByUrl(int $tenantId, string $url): ?\MediaManager\Models\Media
    {
        // AIImageService devuelve la URL pública; se busca el registro recién creado
        $stmt = Database::connect()->prepare("SELECT id FROM media WHERE tenant_id = ? ORDER BY id DESC LIMIT 5");
        $stmt->execute([$tenantId]);
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $id) {
            $media = \MediaManager\Models\Media::find((int) $id);
            if ($media && $media->getPublicUrl() === $url) {
                return $media;
            }
        }
        return null;
    }

    private function setAltText(?\MediaManager\Models\Media $media, $alt): void
    {
        $alt = $alt === null ? '' : mb_substr(SafeHtml::plain((string) $alt), 0, 250);
        if ($media && $alt !== '') {
            Database::connect()->prepare("UPDATE media SET alt_text = ? WHERE id = ?")->execute([$alt, (int) $media->id]);
        }
    }

    private function present(\MediaManager\Models\Media $media): array
    {
        $url = $media->getPublicUrl();
        $absolute = str_starts_with($url, 'http') ? $url : 'https://' . ($GLOBALS['tenant']['domain'] ?? ($_SERVER['HTTP_HOST'] ?? '')) . $url;
        $alt = (string) ($media->alt_text ?? '');
        $meta = is_array($media->metadata) ? $media->metadata : (json_decode((string) $media->metadata, true) ?: []);

        return [
            'id'            => (int) $media->id,
            'url'           => $url,
            'absolute_url'  => $absolute,
            'thumbnail_url' => method_exists($media, 'getThumbnailUrl') ? $media->getThumbnailUrl() : null,
            'alt_text'      => $alt,
            'filename'      => $media->filename,
            'mime_type'     => $media->mime_type,
            'width'         => $meta['width'] ?? null,
            'height'        => $meta['height'] ?? null,
            'created_at'    => $media->created_at ?? null,
            'html'          => '<img src="' . htmlspecialchars($url, ENT_QUOTES) . '" alt="' . htmlspecialchars($alt, ENT_QUOTES) . '" loading="lazy">',
        ];
    }
}
