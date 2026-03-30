<?php

namespace MediaManager\Controllers;

use Screenart\Musedock\View;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Logger;
use MediaManager\Models\Media;
use Screenart\Musedock\Database;

if (!function_exists('slugify')) {
    function slugify($text)
    {
        // Reemplaza caracteres no alfanuméricos por guiones
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        // Transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        // Quita caracteres no deseados
        $text = preg_replace('~[^-\w]+~', '', $text);
        // Quita guiones al inicio y final
        $text = trim($text, '-');
        // Evita múltiples guiones
        $text = preg_replace('~-+~', '-', $text);
        // Minúsculas
        return strtolower($text);
    }
}

class MediaController
{
    private const DEFAULT_CACHE_CONTROL = 'public, max-age=31536000, immutable';

    /**
     * Tenant actual para el contexto actual.
     * - Superadmin (/musedock/*): null (global)
     * - Tenant (/admin/*): ID del tenant
     */
    private function getContextTenantId(): ?int
    {
        if (!$this->isTenantContext()) {
            return null;
        }

        $tenantId = function_exists('tenant_id') ? tenant_id() : ($_SESSION['admin']['tenant_id'] ?? null);
        return $tenantId ? (int)$tenantId : null;
    }

    /**
     * Aplica el scope de tenant al query de Media.
     * Tenant: solo su tenant_id. Superadmin: solo global (tenant_id NULL).
     */
    private function applyMediaTenantScope($query): void
    {
        $tenantId = $this->getContextTenantId();
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }
    }

    /**
     * Aplica el scope de tenant al query de Folder.
     */
    private function applyFolderTenantScope($query): void
    {
        $tenantId = $this->getContextTenantId();
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }
    }

    private function findScopedMedia(int $id): ?Media
    {
        $query = Media::query()->where('id', $id);
        $this->applyMediaTenantScope($query);
        $media = $query->first();
        return $media instanceof Media ? $media : ($media ? new Media((array)$media) : null);
    }

    private function findScopedFolder(int $id): ?\MediaManager\Models\Folder
    {
        $query = \MediaManager\Models\Folder::query()->where('id', $id);
        $this->applyFolderTenantScope($query);
        $folder = $query->first();
        return $folder instanceof \MediaManager\Models\Folder ? $folder : ($folder ? new \MediaManager\Models\Folder((array)$folder) : null);
    }

    private function getUploaderDisplayName(?int $userId): string
    {
        if (!$userId) {
            return '-';
        }
        $tenantId = $this->getContextTenantId();

        // Tenant: primero admins (panel), luego users (frontend).
        if ($tenantId !== null) {
            try {
                $admin = Database::table('admins')
                    ->where('id', $userId)
                    ->where('tenant_id', $tenantId)
                    ->first();
                $adminName = is_array($admin) ? ($admin['name'] ?? '') : ($admin->name ?? '');
                $adminName = trim((string)$adminName);
                if ($adminName !== '') {
                    return $adminName;
                }
            } catch (\Exception $e) {
                // Ignorar
            }

            try {
                $user = Database::table('users')
                    ->where('id', $userId)
                    ->where('tenant_id', $tenantId)
                    ->first();
                $userName = is_array($user) ? ($user['name'] ?? '') : ($user->name ?? '');
                $userName = trim((string)$userName);
                if ($userName !== '') {
                    return $userName;
                }
            } catch (\Exception $e) {
                // Ignorar
            }

            return 'Usuario';
        }

        // Superadmin (global): super_admins, luego users globales (tenant_id NULL).
        try {
            $sa = Database::table('super_admins')->where('id', $userId)->first();
            $saName = is_array($sa) ? ($sa['name'] ?? '') : ($sa->name ?? '');
            $saName = trim((string)$saName);
            if ($saName !== '') {
                return $saName;
            }
            if ($sa) {
                return 'Super Admin';
            }
        } catch (\Exception $e) {
            // Ignorar
        }

        try {
            $user = Database::table('users')
                ->where('id', $userId)
                ->whereNull('tenant_id')
                ->first();
            $userName = is_array($user) ? ($user['name'] ?? '') : ($user->name ?? '');
            $userName = trim((string)$userName);
            if ($userName !== '') {
                return $userName;
            }
        } catch (\Exception $e) {
            // Ignorar
        }

        return 'Usuario';
    }

    /**
     * Obtiene la información de cuota de almacenamiento del tenant actual
     * @return array ['quota_mb' => int, 'used_bytes' => int, 'available_bytes' => int, 'percentage' => float]
     */
    private function getTenantStorageInfo(): array
    {
        $tenantId = function_exists('tenant_id') ? tenant_id() : null;

        if (!$tenantId) {
            // Superadmin: sin límite
            return [
                'quota_mb' => 0, // 0 = ilimitado
                'used_bytes' => 0,
                'available_bytes' => PHP_INT_MAX,
                'percentage' => 0,
                'unlimited' => true
            ];
        }

        try {
            $tenant = Database::table('tenants')->where('id', $tenantId)->first();

            if (!$tenant) {
                return [
                    'quota_mb' => 1024,
                    'used_bytes' => 0,
                    'available_bytes' => 1024 * 1024 * 1024,
                    'percentage' => 0,
                    'unlimited' => false
                ];
            }

            $quotaMb = is_array($tenant) ? ($tenant['storage_quota_mb'] ?? 1024) : ($tenant->storage_quota_mb ?? 1024);
            $usedBytes = is_array($tenant) ? ($tenant['storage_used_bytes'] ?? 0) : ($tenant->storage_used_bytes ?? 0);
            $quotaBytes = $quotaMb * 1024 * 1024;
            $availableBytes = max(0, $quotaBytes - $usedBytes);
            $percentage = $quotaBytes > 0 ? round(($usedBytes / $quotaBytes) * 100, 2) : 0;

            return [
                'quota_mb' => $quotaMb,
                'used_bytes' => $usedBytes,
                'available_bytes' => $availableBytes,
                'percentage' => $percentage,
                'unlimited' => false
            ];
        } catch (\Exception $e) {
            Logger::error("Error getting tenant storage info: " . $e->getMessage());
            return [
                'quota_mb' => 1024,
                'used_bytes' => 0,
                'available_bytes' => 1024 * 1024 * 1024,
                'percentage' => 0,
                'unlimited' => false
            ];
        }
    }

    /**
     * Actualiza el espacio usado por el tenant
     * @param int $bytesChange Cambio en bytes (positivo para añadir, negativo para eliminar)
     */
    private function updateTenantStorageUsed(int $bytesChange): bool
    {
        $tenantId = function_exists('tenant_id') ? tenant_id() : null;

        if (!$tenantId) {
            return true; // Superadmin no tiene tracking
        }

        try {
            // Usar SQL directo para operación atómica
            $pdo = Database::connect();
            $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

            if ($driver === 'mysql') {
                // MySQL: usar GREATEST para evitar valores negativos
                $sql = "UPDATE tenants SET storage_used_bytes = GREATEST(0, storage_used_bytes + :change) WHERE id = :tenant_id";
            } else {
                // PostgreSQL
                $sql = "UPDATE tenants SET storage_used_bytes = GREATEST(0, storage_used_bytes + :change) WHERE id = :tenant_id";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'change' => $bytesChange,
                'tenant_id' => $tenantId
            ]);

            return true;
        } catch (\Exception $e) {
            Logger::error("Error updating tenant storage: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica si el tenant tiene espacio suficiente para un archivo
     * @param int $fileSize Tamaño del archivo en bytes
     * @return array ['allowed' => bool, 'message' => string]
     */
    private function checkStorageQuota(int $fileSize): array
    {
        $storageInfo = $this->getTenantStorageInfo();

        if ($storageInfo['unlimited']) {
            return ['allowed' => true, 'message' => ''];
        }

        if ($fileSize > $storageInfo['available_bytes']) {
            $availableMb = round($storageInfo['available_bytes'] / (1024 * 1024), 2);
            $fileSizeMb = round($fileSize / (1024 * 1024), 2);

            return [
                'allowed' => false,
                'message' => "Cuota de almacenamiento excedida. Disponible: {$availableMb} MB, Archivo: {$fileSizeMb} MB"
            ];
        }

        return ['allowed' => true, 'message' => ''];
    }

    public function index()
    {
        SessionSecurity::startSession();

        // Obtener discos disponibles para el selector
        $availableDisks = $this->getAvailableDisks();

        // Detectar si estamos en contexto tenant o superadmin
        $isTenant = $this->isTenantContext();

        if ($isTenant) {
            // Renderizar vista para tenant
            return View::renderTenantAdmin('media-manager.admin.index', [
                'title' => 'Biblioteca de Medios',
                'availableDisks' => $availableDisks,
                'defaultDisk' => 'media'
            ]);
        } else {
            // Renderizar vista para superadmin
            return View::renderSuperadmin('media-manager.admin.index', [
                'title' => 'Biblioteca de Medios',
                'availableDisks' => $availableDisks,
                'defaultDisk' => 'media'
            ]);
        }
    }

    /**
     * Detecta si estamos en contexto tenant o superadmin
     */
    private function isTenantContext(): bool
    {
        // Verificar si la URL actual contiene /{ADMIN_PATH_TENANT}/ (tenant)
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $adminPath = function_exists('admin_path') ? admin_path() : 'admin';
        $needle = '/' . trim((string)$adminPath, '/') . '/';
        return strpos($requestUri, $needle) !== false;
    }

    private function getDiskNameForContext(?string $requestedDisk): string
    {
        $validDisks = ['local', 'media', 'r2', 's3'];
        $diskName = in_array((string)$requestedDisk, $validDisks, true) ? (string)$requestedDisk : 'media';

        $diskConfig = config("filesystems.disks.{$diskName}");
        if (!$diskConfig || !is_array($diskConfig)) {
            return 'media';
        }

        // Validar disponibilidad del disco en tenant (flags)
        if ($this->isTenantContext()) {
            if ($diskName === 'r2' && !\Screenart\Musedock\Env::get('TENANT_DISK_R2_ENABLED', true)) {
                return 'media';
            }
            if ($diskName === 's3' && !\Screenart\Musedock\Env::get('TENANT_DISK_S3_ENABLED', false)) {
                return 'media';
            }
            if ($diskName === 'local' && !\Screenart\Musedock\Env::get('TENANT_DISK_LOCAL_ENABLED', false)) {
                return 'media';
            }
            if ($diskName === 'media' && !\Screenart\Musedock\Env::get('TENANT_DISK_MEDIA_ENABLED', true)) {
                return 'media';
            }
        }

        return $diskName;
    }

    private function createFilesystemForDisk(string $diskName): array
    {
        $diskConfig = config("filesystems.disks.{$diskName}");
        if (!$diskConfig || !is_array($diskConfig)) {
            $diskName = 'media';
            $diskConfig = config('filesystems.disks.media') ?: [];
        }

        if (($diskConfig['driver'] ?? 'local') === 's3') {
            $filesystem = $this->createS3Filesystem($diskConfig);
            if (!$filesystem) {
                throw new \Exception("No se pudo conectar con el almacenamiento en la nube ({$diskName})");
            }
            return [$filesystem, null, $diskConfig];
        }

        $localRoot = APP_ROOT . ($diskConfig['root'] ?? '/storage/app/media');
        $adapter = new \League\Flysystem\Local\LocalFilesystemAdapter(
            $localRoot,
            null,
            LOCK_EX,
            \League\Flysystem\Local\LocalFilesystemAdapter::DISALLOW_LINKS
        );
        $filesystem = new \League\Flysystem\Filesystem($adapter);
        return [$filesystem, $localRoot, $diskConfig];
    }

    private function buildWriteConfig(string $diskName, string $mimeType): array
    {
        $diskConfig = config("filesystems.disks.{$diskName}") ?: [];
        $cfg = [
            'visibility' => 'public',
        ];

        if (($diskConfig['driver'] ?? null) === 's3') {
            $cfg['ContentType'] = $mimeType ?: 'application/octet-stream';
            $cfg['CacheControl'] = self::DEFAULT_CACHE_CONTROL;
        }

        return $cfg;
    }

    private function createImageThumbnail(string $sourceFile, string $sourceMime, int $maxWidth = 420, int $maxHeight = 420): ?array
    {
        if (!file_exists($sourceFile)) {
            return null;
        }

        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }

        $img = null;
        switch ($sourceMime) {
            case 'image/jpeg':
                $img = @imagecreatefromjpeg($sourceFile);
                break;
            case 'image/png':
                $img = @imagecreatefrompng($sourceFile);
                break;
            case 'image/gif':
                $img = @imagecreatefromgif($sourceFile);
                break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $img = @imagecreatefromwebp($sourceFile);
                }
                break;
            default:
                return null;
        }

        if (!$img) {
            return null;
        }

        $srcWidth = imagesx($img);
        $srcHeight = imagesy($img);
        if ($srcWidth <= 0 || $srcHeight <= 0) {
            imagedestroy($img);
            return null;
        }

        $ratio = min($maxWidth / $srcWidth, $maxHeight / $srcHeight, 1);
        $dstWidth = (int)max(1, floor($srcWidth * $ratio));
        $dstHeight = (int)max(1, floor($srcHeight * $ratio));

        $thumb = imagecreatetruecolor($dstWidth, $dstHeight);
        if (!$thumb) {
            imagedestroy($img);
            return null;
        }

        // WebP soporta transparencia; JPEG necesita fondo blanco
        $useWebP = function_exists('imagewebp');
        if ($useWebP && in_array($sourceMime, ['image/png', 'image/webp', 'image/gif'])) {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
            imagefilledrectangle($thumb, 0, 0, $dstWidth, $dstHeight, $transparent);
            imagealphablending($thumb, true);
        } else {
            $white = imagecolorallocate($thumb, 255, 255, 255);
            imagefilledrectangle($thumb, 0, 0, $dstWidth, $dstHeight, $white);
        }

        imagecopyresampled($thumb, $img, 0, 0, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);
        imagedestroy($img);

        $tmp = tempnam(sys_get_temp_dir(), 'md_thumb_');
        if (!$tmp) {
            imagedestroy($thumb);
            return null;
        }

        // Prefer WebP (30% smaller than JPEG) with JPEG fallback
        if ($useWebP) {
            $tmpOut = $tmp . '.webp';
            @unlink($tmp);
            imagesavealpha($thumb, true);
            $ok = imagewebp($thumb, $tmpOut, 80);
            $outMime = 'image/webp';
        } else {
            $tmpOut = $tmp . '.jpg';
            @unlink($tmp);
            $ok = imagejpeg($thumb, $tmpOut, 82);
            $outMime = 'image/jpeg';
        }
        imagedestroy($thumb);

        if (!$ok || !file_exists($tmpOut)) {
            @unlink($tmpOut);
            return null;
        }

        return [
            'tmp_path' => $tmpOut,
            'mime_type' => $outMime,
            'size' => (int)filesize($tmpOut),
            'width' => $dstWidth,
            'height' => $dstHeight,
        ];
    }

    /**
     * Compress an image maintaining reasonable quality.
     * Resizes if larger than 2048px on any side, converts to JPEG at quality 85.
     * Returns path to compressed temp file, or null if compression not possible.
     */
    private function compressImage(string $sourcePath, string $mimeType, int $maxDim = 2048, int $quality = 85): ?string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }

        // Detectar si la imagen tiene transparencia (PNG, WebP, GIF)
        $hasTransparency = false;
        if ($mimeType === 'image/png' || $mimeType === 'image/webp' || $mimeType === 'image/gif') {
            $hasTransparency = $this->imageHasTransparency($sourcePath, $mimeType);
        }

        $img = match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png'  => @imagecreatefrompng($sourcePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : null,
            'image/gif'  => @imagecreatefromgif($sourcePath),
            default      => null,
        };

        if (!$img) return null;

        $srcW = imagesx($img);
        $srcH = imagesy($img);

        // Resize if larger than max dimension
        $ratio = min($maxDim / max($srcW, 1), $maxDim / max($srcH, 1), 1);
        $dstW = (int)max(1, floor($srcW * $ratio));
        $dstH = (int)max(1, floor($srcH * $ratio));

        if ($ratio < 1) {
            $resized = imagecreatetruecolor($dstW, $dstH);
            if ($hasTransparency) {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                imagefilledrectangle($resized, 0, 0, $dstW, $dstH, $transparent);
            } else {
                $white = imagecolorallocate($resized, 255, 255, 255);
                imagefilledrectangle($resized, 0, 0, $dstW, $dstH, $white);
            }
            imagecopyresampled($resized, $img, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
            imagedestroy($img);
            $img = $resized;
        }

        // Save: preserve format for transparent images, JPEG for opaque
        if ($hasTransparency) {
            if (function_exists('imagewebp')) {
                $tmp = tempnam(sys_get_temp_dir(), 'md_compress_') . '.webp';
                imagesavealpha($img, true);
                $ok = imagewebp($img, $tmp, $quality);
            } else {
                $tmp = tempnam(sys_get_temp_dir(), 'md_compress_') . '.png';
                imagesavealpha($img, true);
                $ok = imagepng($img, $tmp, 6);
            }
        } else {
            $tmp = tempnam(sys_get_temp_dir(), 'md_compress_') . '.jpg';
            $ok = imagejpeg($img, $tmp, $quality);
        }
        imagedestroy($img);

        if (!$ok || !file_exists($tmp)) {
            @unlink($tmp);
            return null;
        }

        // Only use compressed if it's actually smaller
        if (filesize($tmp) >= filesize($sourcePath)) {
            @unlink($tmp);
            return null;
        }

        return $tmp;
    }

    /**
     * Detectar si una imagen tiene píxeles transparentes
     */
    private function imageHasTransparency(string $path, string $mimeType): bool
    {
        // PNG: comprobar si tiene canal alpha en el header
        if ($mimeType === 'image/png') {
            $data = @file_get_contents($path, false, null, 0, 29);
            if ($data && strlen($data) >= 26) {
                // Byte 25 is color type: 4 = greyscale+alpha, 6 = RGBA
                $colorType = ord($data[25]);
                if ($colorType === 4 || $colorType === 6) {
                    return true;
                }
                // Also check for tRNS chunk (palette transparency)
                $fullData = @file_get_contents($path);
                if ($fullData && strpos($fullData, 'tRNS') !== false) {
                    return true;
                }
            }
        }
        // GIF: check for transparency
        if ($mimeType === 'image/gif') {
            $img = @imagecreatefromgif($path);
            if ($img) {
                $transparentIndex = imagecolortransparent($img);
                imagedestroy($img);
                return $transparentIndex >= 0;
            }
        }
        // WebP: assume transparency possible
        if ($mimeType === 'image/webp') {
            return true;
        }
        return false;
    }

    /**
     * Obtiene los discos disponibles para el Media Manager
     * Solo muestra discos que están configurados, tienen credenciales válidas,
     * y están habilitados según el contexto (tenant vs superadmin)
     */
    private function getAvailableDisks(): array
    {
        $disks = [];
        $filesystemsConfig = config('filesystems.disks', []);
        $isTenant = $this->isTenantContext();

        // Disco 'media' (local seguro)
        if (isset($filesystemsConfig['media'])) {
            // Para tenants: verificar si está habilitado en .env
            $enabled = $isTenant
                ? \Screenart\Musedock\Env::get('TENANT_DISK_MEDIA_ENABLED', true)
                : true; // Superadmin siempre tiene acceso

            if ($enabled) {
                $disks['media'] = [
                    'name' => 'Local (Seguro)',
                    'icon' => 'bi-hdd',
                    'description' => 'Almacenamiento local seguro'
                ];
            }
        }

        // Disco 'local' (legacy) - para ver archivos antiguos
        if (isset($filesystemsConfig['local'])) {
            $enabled = $isTenant
                ? \Screenart\Musedock\Env::get('TENANT_DISK_LOCAL_ENABLED', false)
                : true;

            if ($enabled) {
                $disks['local'] = [
                    'name' => 'Local (Legacy)',
                    'icon' => 'bi-folder',
                    'description' => 'Archivos públicos antiguos'
                ];
            }
        }

        // Disco R2 (Cloudflare) - solo si está configurado y habilitado
        if (isset($filesystemsConfig['r2'])) {
            $r2Config = $filesystemsConfig['r2'];
            $hasCredentials = !empty($r2Config['key']) && !empty($r2Config['secret']) && !empty($r2Config['bucket']);
            $enabled = $isTenant
                ? \Screenart\Musedock\Env::get('TENANT_DISK_R2_ENABLED', true)
                : true;

            if ($hasCredentials && $enabled) {
                $disks['r2'] = [
                    'name' => 'Cloudflare R2 (CDN)',
                    'icon' => 'bi-cloud',
                    'description' => 'CDN global con Cloudflare'
                ];
            }
        }

        // Disco S3 (Amazon) - solo si está configurado y habilitado
        if (isset($filesystemsConfig['s3'])) {
            $s3Config = $filesystemsConfig['s3'];
            $hasCredentials = !empty($s3Config['key']) && !empty($s3Config['secret']) && !empty($s3Config['bucket']);
            $enabled = $isTenant
                ? \Screenart\Musedock\Env::get('TENANT_DISK_S3_ENABLED', false)
                : true;

            if ($hasCredentials && $enabled) {
                $disks['s3'] = [
                    'name' => 'Amazon S3',
                    'icon' => 'bi-cloud-arrow-up',
                    'description' => 'Almacenamiento en Amazon S3'
                ];
            }
        }

        return $disks;
    }

    public function getMediaData()
    {
        SessionSecurity::startSession();

        // Obtener parámetros con seguridad
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 30;
        $search = isset($_GET['search']) ? $_GET['search'] : null;
        $typeFilter = isset($_GET['type']) ? $_GET['type'] : null;
        $folderId = isset($_GET['folder_id']) && $_GET['folder_id'] !== '' ? (int)$_GET['folder_id'] : null;
        $diskFilter = isset($_GET['disk']) ? $_GET['disk'] : null; // Nuevo: filtro por disco

        try {
            // Construir consulta
            $query = Media::query()->orderBy('created_at', 'DESC');
            $this->applyMediaTenantScope($query);

            // Filtrar por disco (si se especifica)
            if ($diskFilter && in_array($diskFilter, ['local', 'media', 'r2', 's3'])) {
                $query->where('disk', $diskFilter);
            }

            // Filtrar por carpeta
            // folder_id = 1 es la carpeta Root, los archivos sin carpeta (NULL) también son de Root
            if ($folderId === null || $folderId === 1) {
                // Raíz: mostrar archivos con folder_id = NULL o folder_id = 1
                $query->whereRaw("(folder_id IS NULL OR folder_id = 1)");
            } else {
                // Carpeta específica
                $folder = $this->findScopedFolder($folderId);
                if (!$folder) {
                    return $this->jsonResponse(['success' => false, 'message' => 'Carpeta no encontrada.'], 404);
                }
                $query->where('folder_id', (int)$folderId);
            }

            if ($search) {
                // QueryBuilder no soporta closures - usar whereRaw con bindings seguros
                $searchTerm = "%{$search}%";
                $query->whereRaw("(filename LIKE ? OR alt_text LIKE ? OR caption LIKE ?)",
                    [$searchTerm, $searchTerm, $searchTerm]);
            }

            if ($typeFilter === 'image') {
                $query->where('mime_type', 'LIKE', 'image/%');
            } elseif ($typeFilter === 'document') {
                $query->whereIn('mime_type', ['application/pdf', 'application/msword']);
            }

            $pagination = $query->paginate($perPage, $page);

            $mediaItems = [];

            $items = isset($pagination['items']) ? $pagination['items'] : [];

            foreach ($items as $media) {
                // Convertir a modelo si no es instancia (precaución por resultados raw)
                if (!$media instanceof Media) {
                    $media = new Media((array)$media);
                }

                // URLs: en cloud preferimos URL directa para el panel (evita hits al backend).
                $url = in_array($media->disk, ['r2', 's3'], true) ? $media->getPublicUrl(false) : $media->getPublicUrl();
                $thumbnailUrl = $media->getThumbnailUrl();

                // Obtener dimensiones de imagen desde metadata (sin leer archivo en cada request)
                $dimensions = '';
                if (strpos($media->mime_type, 'image/') === 0) {
                    $meta = is_array($media->metadata) ? $media->metadata : (is_string($media->metadata) ? json_decode($media->metadata, true) : []);
                    $w = $meta['width'] ?? null;
                    $h = $meta['height'] ?? null;
                    if ($w && $h) {
                        $dimensions = $w . ' por ' . $h . ' píxeles';
                    }
                    // No hay dimensiones en metadata — no leer archivo para no ralentizar el listado
                }

            // Obtener usuario que subió el archivo
            $uploader = $this->getUploaderDisplayName($media->user_id ? (int)$media->user_id : null);

            // Determinar la ruta de subida
            $uploadPath = $this->isTenantContext() ? '' : ($media->tenant_id ? 'Tenant ' . $media->tenant_id : 'Global');

            $mediaItems[] = [
                'id' => $media->id,
                'filename' => $media->filename,
                    'url' => $url,
                    'thumbnail_url' => $thumbnailUrl,
                    'mime_type' => $media->mime_type,
                    'size' => $media->size,
                    'alt_text' => $media->alt_text,
                    'caption' => $media->caption,
                    'created_at' => $media->created_at ? $media->created_at->format('Y-m-d H:i:s') : null,
                    'upload_date' => $media->created_at ? $media->created_at->format('d \d\e F \d\e Y') : date('d \d\e F \d\e Y'),
                    'tenant_id' => $media->tenant_id,
                    'user_id' => $media->user_id,
                    'uploader' => $uploader,
                    'upload_path' => $uploadPath,
                    'dimensions' => $dimensions
                ];
            }

            // Obtener información de la carpeta actual
            $currentFolder = null;
            $folderPath = '/';
            if ($folderId) {
                $folder = $this->findScopedFolder($folderId);
                if ($folder) {
                    $currentFolder = [
                        'id' => $folder->id,
                        'name' => $folder->name,
                        'path' => $folder->path
                    ];
                    $folderPath = $folder->path ?: '/';
                }
            }

            // Normalizar claves de paginación para el frontend
            $paginationData = [
                'current_page' => $pagination['current'] ?? $pagination['current_page'] ?? 1,
                'last_page'    => $pagination['last_page'] ?? 1,
                'per_page'     => $pagination['per_page'] ?? $perPage,
                'total'        => $pagination['total'] ?? 0,
            ];

            return $this->jsonResponse([
                'success' => true,
                'media' => $mediaItems,
                'pagination' => $paginationData,
                'current_folder' => $currentFolder,
                'folder_path' => $folderPath
            ]);

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'getMediaData']);
            return $this->jsonResponse(['success' => false, 'message' => 'Error al cargar los medios.'], 500);
        }
    }

/**
 * Método para obtener los detalles completos de un archivo de medios
 * con solución específica para la fecha inválida
 */
public function getMediaDetails($id)
{
    SessionSecurity::startSession();

    try {
        $media = $this->findScopedMedia((int)$id);
        if (!$media) {
            return $this->jsonResponse(['success' => false, 'message' => 'Media no encontrado.'], 404);
        }

        // Obtener ruta completa al archivo físico usando el disco correcto
        $url = in_array($media->disk, ['r2', 's3'], true) ? $media->getPublicUrl(false) : $media->getPublicUrl();
        $filePath = $media->getFullPath();

        // Verificar si el archivo existe físicamente
        $fileExists = $filePath && file_exists($filePath);
        
        // Obtener dimensiones si es imagen
        $dimensions = '';
        if ($fileExists && strpos($media->mime_type, 'image/') === 0) {
            $imageInfo = @getimagesize($filePath);
            if ($imageInfo) {
                $dimensions = $imageInfo[0] . ' por ' . $imageInfo[1] . ' píxeles';
            }
        }
        
        // Verificar tamaño real del archivo
        $size = $media->size;
        if ($fileExists && ($size === 0 || $size === null)) {
            $size = filesize($filePath);
            
            // Si encontramos un tamaño real, actualizamos la base de datos
            if ($size > 0) {
                $media->size = $size;
                $media->save();
            }
        }

	        // Solución para el nombre de usuario
	        $uploader = $this->getUploaderDisplayName($media->user_id ? (int)$media->user_id : null);

	        // Determinar la ruta de subida
	        $uploadPath = '';
	        if (!$this->isTenantContext()) {
	            if (strpos($media->path, 'global/') === 0) {
	                $uploadPath = 'Biblioteca Global';
	            } elseif (preg_match('/tenant_(\d+)\//', $media->path, $matches)) {
	                $tenantId = $matches[1];
	                $uploadPath = "Tenant {$tenantId}";
	            } else {
	                $uploadPath = dirname($media->path);
	            }
	        }

        // SOLUCIÓN ESPECÍFICA PARA LA FECHA
        // Usar una fecha en español directamente con los nombres de los meses correctos
        $meses = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
        ];
        
        $dia = date('d');
        $mes = $meses[(int)date('m')];
        $ano = date('Y');
        
        // Formatear la fecha manualmente para evitar problemas de localización
        $uploadDate = "{$dia} de {$mes} de {$ano}";
        
        // También usar una fecha estándar para created_at
        $createdAt = date('Y-m-d H:i:s');
        
        // Obtener tipo MIME más descriptivo
        $mimeType = $media->mime_type;
        if (!$mimeType || $mimeType === 'application/octet-stream') {
            // Intentar detectar el tipo por la extensión
            $extension = pathinfo($media->filename, PATHINFO_EXTENSION);
            $mimeMap = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ];
            
            $mimeType = $mimeMap[strtolower($extension)] ?? 'application/octet-stream';
        }
        
        // Descripción más amigable del tipo de archivo
        $typeDescription = $this->getFileTypeDescription($mimeType);

        return $this->jsonResponse([
            'success' => true,
            'media' => [
                'id' => $media->id,
                'filename' => $media->filename,
                'url' => $url,
                'thumbnail_url' => $url,
                'mime_type' => $mimeType,
                'type_description' => $typeDescription,
                'size' => $size,
                'alt_text' => $media->alt_text,
                'caption' => $media->caption,
                'created_at' => $createdAt,
                'upload_date' => $uploadDate,
                'tenant_id' => $media->tenant_id,
                'user_id' => $media->user_id,
                'uploader' => $uploader,
                'upload_path' => $uploadPath,
                'dimensions' => $dimensions,
                'file_exists' => $fileExists
            ]
        ]);
    } catch (\Exception $e) {
        Logger::exception($e, 'ERROR', ['source' => 'MediaDetails']);

        // 🔒 SECURITY: No exponer detalles técnicos en producción
        $message = 'Error al obtener detalles del medio.';
        if (getenv('APP_ENV') === 'development') {
            $message .= ' [DEBUG]: ' . $e->getMessage();
        }

        return $this->jsonResponse(['success' => false, 'message' => $message], 500);
    }
}
/**
 * Obtiene una descripción amigable del tipo de archivo basada en el MIME
 */
private function getFileTypeDescription($mimeType)
{
    $parts = explode('/', $mimeType);
    $type = $parts[0] ?? '';
    $subtype = $parts[1] ?? '';
    
    switch ($type) {
        case 'image':
            return 'Imagen ' . strtoupper($subtype);
        case 'video':
            return 'Vídeo ' . strtoupper($subtype);
        case 'audio':
            return 'Audio ' . strtoupper($subtype);
        case 'text':
            return 'Documento de texto';
        case 'application':
            switch ($subtype) {
                case 'pdf':
                    return 'Documento PDF';
                case 'msword':
                case 'vnd.openxmlformats-officedocument.wordprocessingml.document':
                    return 'Documento Word';
                case 'vnd.ms-excel':
                case 'vnd.openxmlformats-officedocument.spreadsheetml.sheet':
                    return 'Hoja de cálculo Excel';
                case 'vnd.ms-powerpoint':
                case 'vnd.openxmlformats-officedocument.presentationml.presentation':
                    return 'Presentación PowerPoint';
                case 'zip':
                case 'x-zip-compressed':
                    return 'Archivo ZIP';
                case 'x-rar-compressed':
                    return 'Archivo RAR';
                default:
                    return 'Archivo ' . $subtype;
            }
        default:
            return 'Archivo ' . $mimeType;
    }
}

/**
 * Método de subida mejorado con soporte para múltiples archivos
 * y verificación de tipos de archivo permitidos
 */
public function upload()
{
    SessionSecurity::startSession();

    // Verificar si se ha enviado al menos un archivo
    if (empty($_FILES['file']['name'][0])) {
        return $this->jsonResponse(['success' => false, 'message' => 'No se recibió ningún archivo.']);
    }

    $maxFilesPerRequest = (int)\Screenart\Musedock\Env::get('MEDIA_MAX_FILES_PER_REQUEST', 1);
    if ($maxFilesPerRequest > 0 && count($_FILES['file']['name']) > $maxFilesPerRequest) {
        return $this->jsonResponse([
            'success' => false,
            'message' => "Máximo {$maxFilesPerRequest} archivo(s) por subida. Por favor, sube de uno en uno."
        ], 429);
    }

    $minInterval = (int)\Screenart\Musedock\Env::get('MEDIA_UPLOAD_MIN_INTERVAL_SECONDS', 2);
    $now = time();
    $last = isset($_SESSION['media_upload_last_ts']) ? (int)$_SESSION['media_upload_last_ts'] : 0;
    if ($minInterval > 0 && $last > 0 && ($now - $last) < $minInterval) {
        return $this->jsonResponse([
            'success' => false,
            'message' => 'Demasiadas subidas. Espera unos segundos e inténtalo de nuevo.'
        ], 429);
    }
    if (!empty($_SESSION['media_upload_in_progress'])) {
        return $this->jsonResponse([
            'success' => false,
            'message' => 'Ya hay una subida activa. Espera a que termine.'
        ], 429);
    }
    $_SESSION['media_upload_in_progress'] = true;

    // Tipos MIME permitidos (puedes personalizar esta lista según tus necesidades)
    $allowedMimeTypes = [
        // Imágenes
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        // Documentos
        'application/pdf', 'application/msword', 
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation', // .pptx
        // Texto
        'text/plain', 'text/html', 'text/css', 'text/javascript', 
        // Archivos comprimidos
        'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed',
        // Audio
        'audio/mpeg', 'audio/wav', 'audio/ogg',
        // Vídeo
        'video/mp4', 'video/webm', 'video/ogg'
    ];

    // Tamaño máximo de archivo en bytes
    $maxFileSize = (int)\Screenart\Musedock\Env::get('MEDIA_MAX_UPLOAD_SIZE', (int)config('filesystems.max_upload_size', 50 * 1024 * 1024));

    // Variables para tracking
    $uploadedFiles = [];
    $errors = [];
    $filesCount = count($_FILES['file']['name']);

    try {
    // Procesar cada archivo
    for ($i = 0; $i < $filesCount; $i++) {
        $file = [
            'name'     => $_FILES['file']['name'][$i],
            'tmp_name' => $_FILES['file']['tmp_name'][$i],
            'error'    => $_FILES['file']['error'][$i],
            'type'     => $_FILES['file']['type'][$i],
            'size'     => $_FILES['file']['size'][$i],
        ];

        // Verificar errores de subida
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMsg = $this->getUploadErrorMessage($file['error']);
            $errors[] = "Error al subir '{$file['name']}': {$errorMsg}";
            continue;
        }

        // 🔒 SECURITY: Verificar tipo MIME REAL (no confiar en el tipo del cliente)
        // Previene: MIME spoofing, upload de archivos maliciosos
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($realMimeType, $allowedMimeTypes)) {
            $errors[] = "Tipo de archivo no permitido: {$file['name']} (detectado: {$realMimeType})";
            continue;
        }

        // Para imágenes, validación adicional con getimagesize
        if (strpos($realMimeType, 'image/') === 0) {
            $imageInfo = @getimagesize($file['tmp_name']);
            if ($imageInfo === false || $imageInfo['mime'] !== $realMimeType) {
                $errors[] = "El archivo {$file['name']} no es una imagen válida";
                continue;
            }
        }

        // Verificar tamaño máximo por archivo
        if ($file['size'] > $maxFileSize) {
            $sizeInMB = number_format($maxFileSize / 1024 / 1024, 2);
            $errors[] = "El archivo {$file['name']} excede el tamaño máximo permitido ({$sizeInMB}MB)";
            continue;
        }

        // 🔒 Verificar cuota de almacenamiento del tenant
        $quotaCheck = $this->checkStorageQuota($file['size']);
        if (!$quotaCheck['allowed']) {
            $errors[] = "No se puede subir '{$file['name']}': " . $quotaCheck['message'];
            continue;
        }

        try {
            $diskName = $this->getDiskNameForContext($_POST['disk'] ?? 'media');

            // Preparar la ruta del archivo
            $tenantId = function_exists('tenant_id') ? tenant_id() : ($_SESSION['admin']['tenant_id'] ?? null);
            $subPath = $tenantId ? "tenant_{$tenantId}" : "global";
            $yearMonth = date('Y/m');
            $safeFilename = slugify(pathinfo($file['name'], PATHINFO_FILENAME)) . '_' . uniqid() . '.' . strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $relativePath = "{$subPath}/{$yearMonth}/{$safeFilename}";
            $dirPath = dirname($relativePath);

            [$filesystem, $localRoot, $diskConfig] = $this->createFilesystemForDisk($diskName);

            // Crear directorio si no existe
            if (!$filesystem->directoryExists($dirPath)) {
                $filesystem->createDirectory($dirPath);
                // Establecer permisos solo para discos locales
                if ($localRoot) {
                    @chmod($localRoot . '/' . $dirPath, 0755);
                }
            }

            // Optionally compress image before saving
            $sourceForUpload = $file['tmp_name'];
            $tmpCompressed = null;
            $shouldCompress = !empty($_POST['compress']) && strpos($realMimeType, 'image/') === 0 && $realMimeType !== 'image/svg+xml';
            if ($shouldCompress) {
                $tmpCompressed = $this->compressImage($file['tmp_name'], $realMimeType);
                if ($tmpCompressed) {
                    $sourceForUpload = $tmpCompressed;
                    $file['size'] = filesize($tmpCompressed);
                }
            }

            // Abrir el archivo temporal
            $stream = fopen($sourceForUpload, 'r+');
            if (!$stream) {
                if ($tmpCompressed) @unlink($tmpCompressed);
                $errors[] = "No se pudo abrir el archivo temporal: {$file['name']}";
                continue;
            }

            // Guardar el archivo en el sistema de archivos
            $filesystem->writeStream($relativePath, $stream, $this->buildWriteConfig($diskName, $realMimeType));
            fclose($stream);
            if ($tmpCompressed) @unlink($tmpCompressed);

            // Establecer permisos solo para discos locales
            if ($localRoot) {
                @chmod($localRoot . '/' . $relativePath, 0644);
            }

            // Obtener folder_id si se especificó
            // Normalizar Root a NULL para mantener consistencia
            $folderId = isset($_POST['folder_id']) && $_POST['folder_id'] !== '' ? (int)$_POST['folder_id'] : null;
            if ($folderId !== null) {
                $folder = $this->findScopedFolder($folderId);
                if (!$folder) {
                    $errors[] = "Carpeta no encontrada o sin permisos (ID {$folderId}).";
                    continue;
                }
                if ($folder->path === '/') {
                    $folderId = null; // Root = NULL
                }
            }

            // Thumbnail real (solo imágenes raster)
            $thumbnail = null;
            $thumbRelativePath = null;
            $medium = null;
            $mediumRelativePath = null;
            if (strpos($realMimeType, 'image/') === 0 && $realMimeType !== 'image/svg+xml') {
                // Thumbnail (420px) para listados del media manager
                $thumbnail = $this->createImageThumbnail($file['tmp_name'], $realMimeType);
                if ($thumbnail && !empty($thumbnail['tmp_path'])) {
                    $thumbDir = "{$subPath}/{$yearMonth}/thumbs";
                    if (!$filesystem->directoryExists($thumbDir)) {
                        $filesystem->createDirectory($thumbDir);
                        if ($localRoot) {
                            @chmod($localRoot . '/' . $thumbDir, 0755);
                        }
                    }
                    $thumbExt = ($thumbnail['mime_type'] ?? '') === 'image/webp' ? 'webp' : 'jpg';
                    $thumbBasename = pathinfo($safeFilename, PATHINFO_FILENAME) . '_thumb.' . $thumbExt;
                    $thumbRelativePath = "{$thumbDir}/{$thumbBasename}";
                    $thumbStream = fopen($thumbnail['tmp_path'], 'r+');
                    if ($thumbStream) {
                        $filesystem->writeStream($thumbRelativePath, $thumbStream, $this->buildWriteConfig($diskName, $thumbnail['mime_type']));
                        fclose($thumbStream);
                    }
                    @unlink($thumbnail['tmp_path']);
                }

                // Medium (800px) para listados de blog, cards, previews
                $medium = $this->createImageThumbnail($file['tmp_name'], $realMimeType, 800, 800);
                if ($medium && !empty($medium['tmp_path'])) {
                    $mediumDir = "{$subPath}/{$yearMonth}/medium";
                    if (!$filesystem->directoryExists($mediumDir)) {
                        $filesystem->createDirectory($mediumDir);
                        if ($localRoot) {
                            @chmod($localRoot . '/' . $mediumDir, 0755);
                        }
                    }
                    $mediumExt = ($medium['mime_type'] ?? '') === 'image/webp' ? 'webp' : 'jpg';
                    $mediumBasename = pathinfo($safeFilename, PATHINFO_FILENAME) . '_medium.' . $mediumExt;
                    $mediumRelativePath = "{$mediumDir}/{$mediumBasename}";
                    $mediumStream = fopen($medium['tmp_path'], 'r+');
                    if ($mediumStream) {
                        $filesystem->writeStream($mediumRelativePath, $mediumStream, $this->buildWriteConfig($diskName, $medium['mime_type']));
                        fclose($mediumStream);
                    }
                    @unlink($medium['tmp_path']);
                }
            }

            // Guardar en la base de datos
            $userId = $_SESSION['super_admin']['id'] ?? ($_SESSION['admin']['id'] ?? ($_SESSION['user']['id'] ?? null));
            $publicToken = Media::generatePublicToken();
            $slug = Media::generateSlug($file['name']);
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $seoFilename = $slug . '-' . $publicToken . '.' . $extension;

            $metadata = null;

            // Guardar dimensiones del original en metadata (evita getimagesize en listados)
            if (strpos($realMimeType, 'image/') === 0 && $realMimeType !== 'image/svg+xml') {
                $imageInfo = @getimagesize($file['tmp_name']);
                if ($imageInfo) {
                    $metadata = [
                        'width' => $imageInfo[0],
                        'height' => $imageInfo[1],
                    ];
                }
            }

            if ($thumbRelativePath && $thumbnail) {
                if (!$metadata) $metadata = [];
                $metadata['thumbnail'] = [
                    'path' => $thumbRelativePath,
                    'mime_type' => $thumbnail['mime_type'] ?? 'image/jpeg',
                    'size' => $thumbnail['size'] ?? null,
                    'width' => $thumbnail['width'] ?? null,
                    'height' => $thumbnail['height'] ?? null,
                ];
            }
            if ($mediumRelativePath && $medium) {
                if (!$metadata) $metadata = [];
                $metadata['medium'] = [
                    'path' => $mediumRelativePath,
                    'mime_type' => $medium['mime_type'] ?? 'image/jpeg',
                    'size' => $medium['size'] ?? null,
                    'width' => $medium['width'] ?? null,
                    'height' => $medium['height'] ?? null,
                ];
            }

            // Asegurar que metadata sea NULL o JSON válido (no pasar null directamente)
            $metadataValue = $metadata ? json_encode($metadata) : null;

            $media = Media::create([
                'tenant_id'    => $tenantId,
                'user_id'      => $userId,
                'folder_id'    => $folderId,
                'disk'         => $diskName,
                'path'         => $relativePath,
                'public_token' => $publicToken,
                'slug'         => $slug,
                'seo_filename' => $seoFilename,
                'filename'     => $file['name'],
                'mime_type'    => $realMimeType,
                'size'         => $file['size'],
                'metadata'     => $metadataValue,
            ]);

            $dimensions = '';
            if ($metadata && isset($metadata['width']) && isset($metadata['height'])) {
                $dimensions = $metadata['width'] . 'x' . $metadata['height'];
            }

            // 📊 Actualizar el espacio usado por el tenant
            $bytesChange = (int)$file['size'];
            if ($metadata && isset($metadata['thumbnail']['size']) && is_numeric($metadata['thumbnail']['size'])) {
                $bytesChange += (int)$metadata['thumbnail']['size'];
            }
            if ($metadata && isset($metadata['medium']['size']) && is_numeric($metadata['medium']['size'])) {
                $bytesChange += (int)$metadata['medium']['size'];
            }
            $this->updateTenantStorageUsed($bytesChange);

            // Añadir a archivos subidos exitosamente
            $uploadedFiles[] = [
                'id'            => $media->id,
                'filename'      => $media->filename,
                // En cloud: usar URL directa para UX del panel; en local: SEO/token.
                'url'           => in_array($media->disk, ['r2', 's3'], true) ? $media->getPublicUrl(false) : $media->getPublicUrl(),
                'thumbnail_url' => $media->getThumbnailUrl(),
                'mime_type'     => $media->mime_type,
                'size'          => $media->size,
                'dimensions'    => $dimensions,
                'upload_date'   => date('d \d\e F \d\e Y')
            ];

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'MediaUpload']);

            // 🔒 SECURITY: No exponer detalles técnicos en producción
            $errorMsg = "Error al procesar '{$file['name']}'.";
            if (getenv('APP_ENV') === 'development') {
                $errorMsg .= " [DEBUG]: " . $e->getMessage();
            }
            $errors[] = $errorMsg;
        }
    }
    } finally {
        $_SESSION['media_upload_in_progress'] = false;
        $_SESSION['media_upload_last_ts'] = time();
    }

    // Construir mensaje de respuesta
    $totalUploaded = count($uploadedFiles);
    $totalErrors = count($errors);
    $message = "";
    
    if ($totalUploaded > 0) {
        $message .= "{$totalUploaded} archivo(s) subido(s) correctamente. ";
    }
    
    if ($totalErrors > 0) {
        $message .= "{$totalErrors} archivo(s) con errores.";
    }

    // Devolver respuesta JSON
    return $this->jsonResponse([
        'success' => $totalUploaded > 0,
        'message' => $message,
        'files'   => $uploadedFiles,
        'errors'  => $errors,
        'media'   => $uploadedFiles[0] ?? null // Para compatibilidad con código existente
    ]);
}

/**
 * Programmatic upload: save a local file into the Media Manager.
 * Used by AIImageService and other internal services to register files
 * with proper thumbnails, quota tracking, and Flysystem/R2 support.
 *
 * @param string $filePath Absolute path to the file on disk
 * @param string $mimeType MIME type of the file
 * @param int|null $tenantId Tenant ID (null for global)
 * @param array $options ['original_name', 'user_id', 'folder_id', 'disk', 'compress']
 * @return array|null ['id', 'url', 'thumbnail_url', 'token'] or null on failure
 */
public function uploadFromFile(string $filePath, string $mimeType, ?int $tenantId = null, array $options = []): ?array
{
    if (!file_exists($filePath)) {
        return null;
    }

    $fileSize = filesize($filePath);
    $originalName = $options['original_name'] ?? basename($filePath);
    $diskName = $options['disk'] ?? 'media';
    $userId = $options['user_id'] ?? null;
    $folderId = $options['folder_id'] ?? null;
    $compress = $options['compress'] ?? false;

    try {
        // Build storage path
        $scope = $tenantId ? "tenant_{$tenantId}" : 'global';
        $yearMonth = date('Y/m');
        $safeFilename = slugify(pathinfo($originalName, PATHINFO_FILENAME)) . '_' . uniqid() . '.' . strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $relativePath = "{$scope}/{$yearMonth}/{$safeFilename}";
        $dirPath = dirname($relativePath);

        [$filesystem, $localRoot, $diskConfig] = $this->createFilesystemForDisk($diskName);

        // Create directory
        if (!$filesystem->directoryExists($dirPath)) {
            $filesystem->createDirectory($dirPath);
            if ($localRoot) {
                @chmod($localRoot . '/' . $dirPath, 0755);
            }
        }

        // Optionally compress image before saving
        $sourceForUpload = $filePath;
        $tmpCompressed = null;
        if ($compress && strpos($mimeType, 'image/') === 0 && $mimeType !== 'image/svg+xml') {
            $tmpCompressed = $this->compressImage($filePath, $mimeType);
            if ($tmpCompressed) {
                $sourceForUpload = $tmpCompressed;
                $fileSize = filesize($tmpCompressed);
            }
        }

        // Write file via Flysystem
        $stream = fopen($sourceForUpload, 'r');
        if (!$stream) {
            if ($tmpCompressed) @unlink($tmpCompressed);
            return null;
        }
        $filesystem->writeStream($relativePath, $stream, $this->buildWriteConfig($diskName, $mimeType));
        fclose($stream);

        if ($localRoot) {
            @chmod($localRoot . '/' . $relativePath, 0644);
        }
        if ($tmpCompressed) @unlink($tmpCompressed);

        // Generate thumbnails (420px + 800px)
        $thumbnail = null;
        $thumbRelativePath = null;
        $medium = null;
        $mediumRelativePath = null;

        if (strpos($mimeType, 'image/') === 0 && $mimeType !== 'image/svg+xml') {
            // Thumbnail (420px)
            $thumbnail = $this->createImageThumbnail($filePath, $mimeType);
            if ($thumbnail && !empty($thumbnail['tmp_path'])) {
                $thumbDir = "{$scope}/{$yearMonth}/thumbs";
                if (!$filesystem->directoryExists($thumbDir)) {
                    $filesystem->createDirectory($thumbDir);
                    if ($localRoot) @chmod($localRoot . '/' . $thumbDir, 0755);
                }
                $thumbExt = ($thumbnail['mime_type'] ?? '') === 'image/webp' ? 'webp' : 'jpg';
                $thumbBasename = pathinfo($safeFilename, PATHINFO_FILENAME) . '_thumb.' . $thumbExt;
                $thumbRelativePath = "{$thumbDir}/{$thumbBasename}";
                $thumbStream = fopen($thumbnail['tmp_path'], 'r');
                if ($thumbStream) {
                    $filesystem->writeStream($thumbRelativePath, $thumbStream, $this->buildWriteConfig($diskName, $thumbnail['mime_type']));
                    fclose($thumbStream);
                }
                @unlink($thumbnail['tmp_path']);
            }

            // Medium (800px)
            $medium = $this->createImageThumbnail($filePath, $mimeType, 800, 800);
            if ($medium && !empty($medium['tmp_path'])) {
                $mediumDir = "{$scope}/{$yearMonth}/medium";
                if (!$filesystem->directoryExists($mediumDir)) {
                    $filesystem->createDirectory($mediumDir);
                    if ($localRoot) @chmod($localRoot . '/' . $mediumDir, 0755);
                }
                $mediumExt = ($medium['mime_type'] ?? '') === 'image/webp' ? 'webp' : 'jpg';
                $mediumBasename = pathinfo($safeFilename, PATHINFO_FILENAME) . '_medium.' . $mediumExt;
                $mediumRelativePath = "{$mediumDir}/{$mediumBasename}";
                $mediumStream = fopen($medium['tmp_path'], 'r');
                if ($mediumStream) {
                    $filesystem->writeStream($mediumRelativePath, $mediumStream, $this->buildWriteConfig($diskName, $medium['mime_type']));
                    fclose($mediumStream);
                }
                @unlink($medium['tmp_path']);
            }
        }

        // Build metadata
        $metadataArr = null;
        if ($thumbRelativePath && $thumbnail) {
            $metadataArr = [
                'thumbnail' => [
                    'path' => $thumbRelativePath,
                    'mime_type' => $thumbnail['mime_type'] ?? 'image/jpeg',
                    'size' => $thumbnail['size'] ?? null,
                    'width' => $thumbnail['width'] ?? null,
                    'height' => $thumbnail['height'] ?? null,
                ],
            ];
        }
        if ($mediumRelativePath && $medium) {
            if (!$metadataArr) $metadataArr = [];
            $metadataArr['medium'] = [
                'path' => $mediumRelativePath,
                'mime_type' => $medium['mime_type'] ?? 'image/jpeg',
                'size' => $medium['size'] ?? null,
                'width' => $medium['width'] ?? null,
                'height' => $medium['height'] ?? null,
            ];
        }

        // Create DB record using same token system as normal uploads
        $publicToken = Media::generatePublicToken();
        $slug = Media::generateSlug($originalName);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $seoFilename = $slug . '-' . $publicToken . '.' . $extension;

        $media = Media::create([
            'tenant_id'    => $tenantId,
            'user_id'      => $userId,
            'folder_id'    => $folderId,
            'disk'         => $diskName,
            'path'         => $relativePath,
            'public_token' => $publicToken,
            'slug'         => $slug,
            'seo_filename' => $seoFilename,
            'filename'     => $originalName,
            'mime_type'    => $mimeType,
            'size'         => $fileSize,
            'metadata'     => $metadataArr ? json_encode($metadataArr) : null,
        ]);

        // Update tenant storage quota
        $bytesChange = (int)$fileSize;
        if ($metadataArr && isset($metadataArr['thumbnail']['size'])) {
            $bytesChange += (int)$metadataArr['thumbnail']['size'];
        }
        if ($metadataArr && isset($metadataArr['medium']['size'])) {
            $bytesChange += (int)$metadataArr['medium']['size'];
        }
        $this->updateTenantStorageUsed($bytesChange);

        return [
            'id' => $media->id,
            'url' => $media->getPublicUrl(),
            'thumbnail_url' => $media->getThumbnailUrl(),
            'token' => $publicToken,
        ];

    } catch (\Exception $e) {
        error_log("[MediaManager::uploadFromFile] Error: " . $e->getMessage());
        return null;
    }
}

/**
 * Traduce los códigos de error de PHP a mensajes legibles
 */
private function getUploadErrorMessage($errorCode)
{
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
            return 'El tamaño del archivo excede el límite permitido por PHP.';
        case UPLOAD_ERR_FORM_SIZE:
            return 'El tamaño del archivo excede el límite especificado en el formulario.';
        case UPLOAD_ERR_PARTIAL:
            return 'El archivo se subió parcialmente.';
        case UPLOAD_ERR_NO_FILE:
            return 'No se subió ningún archivo.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'No se encontró la carpeta temporal.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Error al escribir el archivo en disco.';
        case UPLOAD_ERR_EXTENSION:
            return 'Subida detenida por una extensión de PHP.';
        default:
            return 'Error desconocido.';
    }
}
    public function updateMeta($id)
    {
        SessionSecurity::startSession();

        try {
            $media = $this->findScopedMedia((int)$id);
            if (!$media) {
                return $this->jsonResponse(['success' => false, 'message' => 'Media no encontrado.'], 404);
            }

            $altText = substr(strip_tags($_POST['alt_text'] ?? ''), 0, 255);
            $caption = substr(strip_tags($_POST['caption'] ?? ''), 0, 500);

            $media->alt_text = $altText;
            $media->caption = $caption;
            $media->save();

            return $this->jsonResponse(['success' => true, 'message' => 'Metadatos actualizados.']);
        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'MediaUpdateMeta']);
            return $this->jsonResponse(['success' => false, 'message' => 'Error al guardar los metadatos.'], 500);
        }
    }
    
    public function delete($id)
    {
        SessionSecurity::startSession();

        try {
            $media = $this->findScopedMedia((int)$id);
            if (!$media) {
                return $this->jsonResponse(['success' => false, 'message' => 'Medio no encontrado.']);
            }

            // Guardar el tamaño antes de eliminar para actualizar la cuota
            $fileSize = $media->size ?? 0;
            $meta = is_array($media->metadata) ? $media->metadata : (is_string($media->metadata) ? json_decode($media->metadata, true) : []);
            $thumbSize = isset($meta['thumbnail']['size']) && is_numeric($meta['thumbnail']['size']) ? (int)$meta['thumbnail']['size'] : 0;
            $thumbPath = $meta['thumbnail']['path'] ?? null;
            $mediumSize = isset($meta['medium']['size']) && is_numeric($meta['medium']['size']) ? (int)$meta['medium']['size'] : 0;
            $mediumPath = $meta['medium']['path'] ?? null;

            [$filesystem] = $this->createFilesystemForDisk($media->disk ?: 'media');
            try {
                $filesystem->delete($media->path);
            } catch (\Throwable $e) {
                // idempotente: si no existe, continuar
            }
            if ($thumbPath) {
                try {
                    $filesystem->delete((string)$thumbPath);
                } catch (\Throwable $e) {
                }
            }
            if ($mediumPath) {
                try {
                    $filesystem->delete((string)$mediumPath);
                } catch (\Throwable $e) {
                }
            }

            if ($media->delete()) {
                // 📊 Restar el espacio usado del tenant
                $bytes = (int)$fileSize + (int)$thumbSize + (int)$mediumSize;
                if ($bytes > 0) {
                    $this->updateTenantStorageUsed(-$bytes);
                }

                return $this->jsonResponse(['success' => true, 'message' => 'Medio eliminado correctamente.']);
            }

            return $this->jsonResponse(['success' => false, 'message' => 'Error al eliminar el medio.']);

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'MediaDelete']);
            return $this->jsonResponse(['success' => false, 'message' => 'Error al eliminar el medio.'], 500);
        }
    }

    /**
     * Renombra un archivo de medios
     */
    public function renameMedia($id)
    {
        SessionSecurity::startSession();

        try {
            $media = $this->findScopedMedia((int)$id);
            if (!$media) {
                return $this->jsonResponse(['success' => false, 'message' => 'Medio no encontrado.'], 404);
            }

            $newFilename = $_POST['filename'] ?? '';
            if (empty($newFilename)) {
                return $this->jsonResponse(['success' => false, 'message' => 'El nombre del archivo es requerido.'], 400);
            }

            // 🔒 SECURITY: Sanitizar nombre de archivo
            // Previene: Path traversal, overwrite de archivos del sistema
            $newFilename = basename($newFilename);

            // Validar caracteres permitidos (solo alfanuméricos, guiones, puntos, guiones bajos)
            if (!preg_match('/^[a-zA-Z0-9._-]+$/', $newFilename)) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'El nombre del archivo contiene caracteres no permitidos.'
                ], 400);
            }

            // Prevenir nombres maliciosos
            $dangerousNames = ['.htaccess', '.htpasswd', 'web.config', '.env', '.git', '.gitignore', 'composer.json', 'package.json'];
            if (in_array(strtolower($newFilename), $dangerousNames)) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Nombre de archivo no permitido.'
                ], 400);
            }

            // Prevenir múltiples extensiones (.php.jpg)
            $parts = explode('.', $newFilename);
            if (count($parts) > 2) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'El nombre de archivo no puede tener múltiples extensiones.'
                ], 400);
            }

            $oldPath = (string)$media->path;

            // Nuevo nombre de objeto: mantener directorio, sanitizar nombre, evitar colisiones
            $baseDir = dirname($oldPath);
            $baseDir = $baseDir === '.' ? '' : $baseDir;
            $extension = strtolower(pathinfo($newFilename, PATHINFO_EXTENSION));
            $baseName = pathinfo($newFilename, PATHINFO_FILENAME);
            $safeBase = slugify($baseName);
            $suffix = substr((string)$media->public_token, 0, 6);
            $newStorageName = $safeBase . '_' . $suffix . '.' . $extension;
            $newPath = $baseDir ? ($baseDir . '/' . $newStorageName) : $newStorageName;

            // Renombrar objeto real en el disco activo
            [$filesystem] = $this->createFilesystemForDisk($media->disk ?: 'media');
            if ($newPath !== $oldPath) {
                try {
                    // Flysystem move para S3/R2 = COPY + DELETE
                    $filesystem->move($oldPath, $newPath);
                } catch (\Throwable $e) {
                    return $this->jsonResponse(['success' => false, 'message' => 'No se pudo renombrar el archivo en el almacenamiento.'], 500);
                }
            }

            // Actualizar metadata y SEO (token se mantiene)
            $media->filename = $newFilename;
            $media->path = $newPath;
            $media->slug = Media::generateSlug($newFilename);
            $media->seo_filename = $media->slug . '-' . $media->public_token . '.' . $extension;
            $media->save();

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Archivo renombrado correctamente.',
                'media' => [
                    'id' => $media->id,
                    'filename' => $media->filename,
                    'path' => $media->path
                ]
            ]);

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'MediaRename']);
            return $this->jsonResponse(['success' => false, 'message' => 'Error al renombrar el archivo.'], 500);
        }
    }

    public function getPublicUrl()
    {
        // Si es disco local
        if ($this->disk === 'local') {
            return '/assets/uploads/' . ltrim($this->path, '/');
        }

        // Otros discos → por si en el futuro usas otro tipo
        return $this->path;
    }


    // ========================================================
    // FOLDER MANAGEMENT METHODS
    // ========================================================

    /**
     * Obtiene la estructura de carpetas y archivos del directorio actual
     */
    public function getFolderStructure()
    {
        SessionSecurity::startSession();

        // Establecer headers JSON PRIMERO antes de cualquier procesamiento
        header('Content-Type: application/json');

        try {
            // Usar helper tenant_id() que funciona tanto en superadmin como tenant
            $tenantId = function_exists('tenant_id') ? tenant_id() : ($_SESSION['admin']['tenant_id'] ?? null);

            // Obtener disco desde parámetro (default: media)
            $disk = isset($_GET['disk']) ? $_GET['disk'] : 'media';
            $validDisks = ['local', 'media', 'r2', 's3'];
            if (!in_array($disk, $validDisks)) {
                $disk = 'media';
            }

            // Usar el modelo Folder en lugar de queries crudas
            $query = \MediaManager\Models\Folder::query();

            // Filtrar por disco
            $query->where('disk', $disk);

            // Filtrar por tenant si existe
            if ($tenantId) {
                // Incluir carpetas del tenant + root global (para jerarquía)
                $query->whereRaw('(tenant_id = ? OR (tenant_id IS NULL AND path = ?))', [$tenantId, '/']);
            } else {
                $query->whereNull('tenant_id');
            }

            // Ordenar por jerarquía
            $folders = $query->orderBy('parent_id', 'ASC')
                           ->orderBy('name', 'ASC')
                           ->get();

            // Convertir a array
            $foldersData = [];
            foreach ($folders as $folder) {
                $foldersData[] = [
                    'id' => (int)$folder->id,
                    'parent_id' => $folder->parent_id ? (int)$folder->parent_id : null,
                    'name' => $folder->name,
                    'slug' => $folder->slug,
                    'path' => $folder->path,
                    'disk' => $folder->disk ?? $disk,
                    'description' => $folder->description ?? '',
                    'created_at' => $folder->created_at ? (string)$folder->created_at : null
                ];
            }

            // Si no hay carpetas para este disco, crear la raíz
            if (empty($foldersData)) {
                $rootFolder = \MediaManager\Models\Folder::getRootFolder($tenantId, $disk);
                $foldersData[] = [
                    'id' => (int)$rootFolder->id,
                    'parent_id' => null,
                    'name' => $rootFolder->name,
                    'slug' => $rootFolder->slug,
                    'path' => $rootFolder->path,
                    'disk' => $rootFolder->disk ?? $disk,
                    'description' => $rootFolder->description ?? '',
                    'created_at' => $rootFolder->created_at ? (string)$rootFolder->created_at : null
                ];
            }

            echo json_encode([
                'success' => true,
                'folders' => $foldersData,
                'disk' => $disk
            ]);
            exit;

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'getFolderStructure', 'error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al cargar carpetas: ' . $e->getMessage(),
                'debug' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ]);
            exit;
        }
    }

    /**
     * Crea una nueva carpeta
     */
    public function createFolder()
    {
        SessionSecurity::startSession();

        try {
            $name = $_POST['name'] ?? '';
            $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
            $description = $_POST['description'] ?? '';
            $tenantId = function_exists('tenant_id') ? tenant_id() : ($_SESSION['admin']['tenant_id'] ?? null);

            // Obtener disco desde POST (default: media)
            $disk = isset($_POST['disk']) ? $_POST['disk'] : 'media';
            $validDisks = ['local', 'media', 'r2', 's3'];
            if (!in_array($disk, $validDisks)) {
                $disk = 'media';
            }

            if (empty($name)) {
                return $this->jsonResponse(['success' => false, 'message' => 'El nombre de la carpeta es requerido.'], 400);
            }

            // Si no se especifica parent_id, obtener la raíz del disco actual
            if ($parentId === null) {
                $rootFolder = \MediaManager\Models\Folder::getRootFolder($tenantId, $disk);
                $parentId = $rootFolder->id;
            } else {
                $parentFolder = $this->findScopedFolder($parentId);
                if (!$parentFolder) {
                    return $this->jsonResponse(['success' => false, 'message' => 'Carpeta padre no encontrada o sin permisos.'], 404);
                }
            }

            // Generar slug único (incluyendo disco)
            $slug = \MediaManager\Models\Folder::generateSlug($name, $tenantId, $parentId, $disk);

            // Crear carpeta
            $folder = \MediaManager\Models\Folder::create([
                'tenant_id' => $tenantId,
                'parent_id' => $parentId,
                'name' => $name,
                'slug' => $slug,
                'path' => '', // Se generará automáticamente
                'disk' => $disk,
                'description' => $description
            ]);

            // Generar y actualizar path
            $folder->path = $folder->generatePath();
            $folder->save();

            // Crear directorio físico si no existe (solo para discos locales)
            if (in_array($disk, ['local', 'media'])) {
                $diskConfig = config("filesystems.disks.{$disk}");
                $diskRoot = $diskConfig['root'] ?? '/public/assets/uploads';
                $physicalPath = APP_ROOT . $diskRoot . $folder->path;
                if (!is_dir($physicalPath)) {
                    mkdir($physicalPath, 0755, true);
                }
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Carpeta creada correctamente.',
                'folder' => [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'path' => $folder->path,
                    'disk' => $folder->disk,
                    'created_at' => $folder->created_at ? $folder->created_at->format('Y-m-d H:i:s') : null
                ]
            ]);

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'createFolder']);
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Error al crear la carpeta: ' . $e->getMessage(),
                'debug' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }

    /**
     * Renombra una carpeta
     */
    public function renameFolder($id)
    {
        SessionSecurity::startSession();

        try {
            $folder = $this->findScopedFolder((int)$id);

            if (!$folder) {
                return $this->jsonResponse(['success' => false, 'message' => 'Carpeta no encontrada.'], 404);
            }

            // Verificar si es la carpeta raíz (no puede ser renombrada)
            // La raíz tiene path === '/' y es la única con parent_id === null
            if ($folder->path === '/') {
                return $this->jsonResponse(['success' => false, 'message' => 'No se puede renombrar la carpeta raíz.'], 403);
            }

            $newName = $_POST['name'] ?? '';

            if (empty($newName)) {
                return $this->jsonResponse(['success' => false, 'message' => 'El nombre es requerido.'], 400);
            }

            $oldPath = $folder->path;
            $folder->name = $newName;
            $folder->slug = \MediaManager\Models\Folder::generateSlug($newName, $folder->tenant_id, $folder->parent_id);
            $folder->path = $folder->generatePath();
            $folder->save();

            // Carpetas son virtuales (DB). No renombrar storage físico (evita COPY/DELETE masivo en R2).

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Carpeta renombrada correctamente.',
                'folder' => [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'path' => $folder->path
                ]
            ]);

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'renameFolder']);
            return $this->jsonResponse(['success' => false, 'message' => 'Error al renombrar la carpeta.'], 500);
        }
    }

    /**
     * Elimina una carpeta
     */
    /**
     * Eliminar todos los archivos de una carpeta (vaciado masivo)
     */
    public function deleteFolderContents($id)
    {
        SessionSecurity::startSession();

        try {
            $folder = $this->findScopedFolder((int)$id);
            if (!$folder) {
                return $this->jsonResponse(['success' => false, 'message' => 'Carpeta no encontrada.'], 404);
            }

            // Obtener todos los media de esta carpeta
            $query = Media::query()->where('folder_id', (int)$id);
            $this->applyMediaTenantScope($query);
            $mediaItems = $query->get();

            $deleted = 0;
            $errors = 0;

            foreach ($mediaItems as $media) {
                try {
                    $fileSize = $media->size ?? 0;
                    $meta = is_array($media->metadata) ? $media->metadata : (is_string($media->metadata) ? json_decode($media->metadata, true) : []);
                    $thumbSize = isset($meta['thumbnail']['size']) && is_numeric($meta['thumbnail']['size']) ? (int)$meta['thumbnail']['size'] : 0;
                    $thumbPath = $meta['thumbnail']['path'] ?? null;
                    $mediumSize = isset($meta['medium']['size']) && is_numeric($meta['medium']['size']) ? (int)$meta['medium']['size'] : 0;
                    $mediumPath = $meta['medium']['path'] ?? null;

                    [$filesystem] = $this->createFilesystemForDisk($media->disk ?: 'media');
                    try { $filesystem->delete($media->path); } catch (\Throwable $e) {}
                    if ($thumbPath) { try { $filesystem->delete((string)$thumbPath); } catch (\Throwable $e) {} }
                    if ($mediumPath) { try { $filesystem->delete((string)$mediumPath); } catch (\Throwable $e) {} }

                    if ($media->delete()) {
                        $bytes = (int)$fileSize + (int)$thumbSize + (int)$mediumSize;
                        if ($bytes > 0) {
                            $this->updateTenantStorageUsed(-$bytes);
                        }
                        $deleted++;
                    } else {
                        $errors++;
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    Logger::error("deleteFolderContents: Error eliminando media {$media->id}: " . $e->getMessage());
                }
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => "Se eliminaron {$deleted} archivo(s)." . ($errors > 0 ? " {$errors} error(es)." : ''),
                'deleted' => $deleted,
                'errors' => $errors,
            ]);

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'deleteFolderContents']);
            return $this->jsonResponse(['success' => false, 'message' => 'Error al vaciar la carpeta.'], 500);
        }
    }

    public function deleteFolder($id)
    {
        SessionSecurity::startSession();

        try {
            $folder = $this->findScopedFolder((int)$id);

            if (!$folder) {
                return $this->jsonResponse(['success' => false, 'message' => 'Carpeta no encontrada.'], 404);
            }

            // Verificar si es la carpeta raíz (no puede ser eliminada)
            if ($folder->path === '/') {
                return $this->jsonResponse(['success' => false, 'message' => 'No se puede eliminar la carpeta raíz.'], 403);
            }

            // Verificar si tiene contenido
            $mediaCount = $folder->countMediaRecursive();
            $childrenCount = count($folder->children());

            if ($mediaCount > 0 || $childrenCount > 0) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => "La carpeta contiene {$mediaCount} archivo(s) y {$childrenCount} subcarpeta(s). Debe estar vacía para eliminarla."
                ], 400);
            }

            // Eliminar carpeta
            if ($folder->delete()) {
                return $this->jsonResponse(['success' => true, 'message' => 'Carpeta eliminada correctamente.']);
            }

            return $this->jsonResponse(['success' => false, 'message' => 'Error al eliminar la carpeta.'], 500);

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'deleteFolder']);
            return $this->jsonResponse(['success' => false, 'message' => 'Error al eliminar la carpeta.'], 500);
        }
    }

    /**
     * Mueve archivos o carpetas a otra ubicación
     */
    public function moveItems()
    {
        SessionSecurity::startSession();

        try {
            $itemIds = $_POST['item_ids'] ?? [];
            $itemType = $_POST['item_type'] ?? 'media'; // 'media' o 'folder'
            $targetFolderId = isset($_POST['target_folder_id']) && $_POST['target_folder_id'] !== '' ? (int)$_POST['target_folder_id'] : null;

            // Si target es raíz (path '/'), normalizar a NULL para mantener consistencia
            if ($targetFolderId !== null) {
                $targetFolder = $this->findScopedFolder($targetFolderId);
                if (!$targetFolder) {
                    return $this->jsonResponse(['success' => false, 'message' => 'Carpeta destino no encontrada.'], 404);
                }
                if ($targetFolder->path === '/') {
                    $targetFolderId = null;
                }
            }

            if (empty($itemIds) || !is_array($itemIds)) {
                return $this->jsonResponse(['success' => false, 'message' => 'No se especificaron elementos para mover.'], 400);
            }

            $movedCount = 0;
            $errors = [];

            if ($itemType === 'media') {
                foreach ($itemIds as $mediaId) {
                    $media = $this->findScopedMedia((int)$mediaId);
                    if ($media && $media->moveToFolder($targetFolderId)) {
                        $movedCount++;
                    } else {
                        $errors[] = "Error moviendo archivo ID: {$mediaId}";
                    }
                }
            } elseif ($itemType === 'folder') {
                foreach ($itemIds as $folderId) {
                    $folder = $this->findScopedFolder((int)$folderId);
                    if ($folder && $folder->moveTo($targetFolderId)) {
                        $movedCount++;
                    } else {
                        $errors[] = "Error moviendo carpeta ID: {$folderId}";
                    }
                }
            }

            $message = "{$movedCount} elemento(s) movido(s) correctamente.";
            if (!empty($errors)) {
                $message .= " Errores: " . implode(', ', $errors);
            }

            return $this->jsonResponse([
                'success' => $movedCount > 0,
                'message' => $message,
                'moved_count' => $movedCount,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'moveItems']);
            return $this->jsonResponse(['success' => false, 'message' => 'Error al mover elementos.'], 500);
        }
    }

    /**
     * Copia archivos a otra ubicación
     */
    public function copyMedia()
    {
        SessionSecurity::startSession();

        try {
            $mediaIds = $_POST['media_ids'] ?? [];
            $targetFolderId = isset($_POST['target_folder_id']) && $_POST['target_folder_id'] !== '' ? (int)$_POST['target_folder_id'] : null;

            if (empty($mediaIds) || !is_array($mediaIds)) {
                return $this->jsonResponse(['success' => false, 'message' => 'No se especificaron archivos para copiar.'], 400);
            }

            $copiedCount = 0;
            $errors = [];

            if ($targetFolderId !== null) {
                $targetFolder = $this->findScopedFolder($targetFolderId);
                if (!$targetFolder) {
                    return $this->jsonResponse(['success' => false, 'message' => 'Carpeta destino no encontrada.'], 404);
                }
                if ($targetFolder->path === '/') {
                    $targetFolderId = null;
                }
            }

            foreach ($mediaIds as $mediaId) {
                $media = $this->findScopedMedia((int)$mediaId);
                if (!$media) {
                    $errors[] = "Archivo ID {$mediaId} no encontrado.";
                    continue;
                }

                $oldPath = (string)$media->path;
                $dir = dirname($oldPath);
                $dir = $dir === '.' ? '' : $dir;
                $info = pathinfo($oldPath);
                $base = $info['filename'] ?? 'file';
                $ext = isset($info['extension']) ? ('.' . $info['extension']) : '';
                $newPath = ($dir ? ($dir . '/') : '') . $base . '-copy-' . time() . $ext;

                try {
                    [$filesystem] = $this->createFilesystemForDisk($media->disk ?: 'media');
                    $filesystem->copy($oldPath, $newPath);
                } catch (\Throwable $e) {
                    $errors[] = "Error copiando archivo ID: {$mediaId}";
                    continue;
                }

                $newToken = Media::generatePublicToken();
                $newSlug = Media::generateSlug($media->filename);
                $newSeoFilename = $newSlug . '-' . $newToken . '.' . strtolower(pathinfo($media->filename, PATHINFO_EXTENSION));

                $meta = is_array($media->metadata) ? $media->metadata : (is_string($media->metadata) ? json_decode($media->metadata, true) : null);
                if (is_array($meta) && !empty($meta['thumbnail']['path'])) {
                    $thumbOld = (string)$meta['thumbnail']['path'];
                    $thumbInfo = pathinfo($thumbOld);
                    $thumbDir = $thumbInfo['dirname'] ?? '';
                    $thumbDir = $thumbDir === '.' ? '' : $thumbDir;
                    $thumbBase = $thumbInfo['filename'] ?? 'thumb';
                    $thumbExt = isset($thumbInfo['extension']) ? ('.' . $thumbInfo['extension']) : '';
                    $thumbNew = ($thumbDir ? ($thumbDir . '/') : '') . $thumbBase . '-copy-' . time() . $thumbExt;
                    try {
                        [$filesystem] = $this->createFilesystemForDisk($media->disk ?: 'media');
                        $filesystem->copy($thumbOld, $thumbNew);
                        $meta['thumbnail']['path'] = $thumbNew;
                    } catch (\Throwable $e) {
                        // si falla thumb, continuar sin thumb
                        unset($meta['thumbnail']);
                    }
                }

                $copy = Media::create([
                    'tenant_id' => $media->tenant_id,
                    'user_id' => $media->user_id,
                    'folder_id' => $targetFolderId,
                    'disk' => $media->disk,
                    'path' => $newPath,
                    'public_token' => $newToken,
                    'slug' => $newSlug,
                    'seo_filename' => $newSeoFilename,
                    'filename' => $media->filename,
                    'mime_type' => $media->mime_type,
                    'size' => $media->size,
                    'alt_text' => $media->alt_text,
                    'caption' => $media->caption,
                    'metadata' => $meta,
                ]);

                if ($copy) {
                    $bytesChange = (int)($media->size ?? 0);
                    if (is_array($meta) && isset($meta['thumbnail']['size']) && is_numeric($meta['thumbnail']['size'])) {
                        $bytesChange += (int)$meta['thumbnail']['size'];
                    }
                    if (is_array($meta) && isset($meta['medium']['size']) && is_numeric($meta['medium']['size'])) {
                        $bytesChange += (int)$meta['medium']['size'];
                    }
                    if ($bytesChange > 0) {
                        $this->updateTenantStorageUsed($bytesChange);
                    }
                    $copiedCount++;
                } else {
                    $errors[] = "Error copiando archivo ID: {$mediaId}";
                }
            }

            $message = "{$copiedCount} archivo(s) copiado(s) correctamente.";
            if (!empty($errors)) {
                $message .= " Errores: " . implode(', ', $errors);
            }

            return $this->jsonResponse([
                'success' => $copiedCount > 0,
                'message' => $message,
                'copied_count' => $copiedCount,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'copyMedia']);
            return $this->jsonResponse(['success' => false, 'message' => 'Error al copiar archivos.'], 500);
        }
    }

    /**
     * Crea un sistema de archivos S3/R2 usando Flysystem
     *
     * @param array $config Configuración del disco
     * @return \League\Flysystem\Filesystem|null
     */
    private function createS3Filesystem(array $config): ?\League\Flysystem\Filesystem
    {
        try {
            // Verificar que tenemos las credenciales necesarias
            if (empty($config['key']) || empty($config['secret']) || empty($config['bucket'])) {
                Logger::warning('S3/R2: Credenciales incompletas', [
                    'has_key' => !empty($config['key']),
                    'has_secret' => !empty($config['secret']),
                    'has_bucket' => !empty($config['bucket'])
                ]);
                return null;
            }

            // Verificar que la clase de AWS SDK existe
            if (!class_exists('\Aws\S3\S3Client')) {
                Logger::error('S3/R2: AWS SDK no está instalado. Ejecuta: composer require aws/aws-sdk-php');
                return null;
            }

            // Crear cliente S3
            $clientConfig = [
                'credentials' => [
                    'key'    => $config['key'],
                    'secret' => $config['secret'],
                ],
                'region'  => $config['region'] ?? 'auto',
                'version' => 'latest',
            ];

            // Para R2 y otros S3-compatible, añadir endpoint
            if (!empty($config['endpoint'])) {
                $clientConfig['endpoint'] = $config['endpoint'];
                $clientConfig['use_path_style_endpoint'] = $config['use_path_style_endpoint'] ?? false;
            }

            $client = new \Aws\S3\S3Client($clientConfig);

            // Crear adaptador S3
            $adapter = new \League\Flysystem\AwsS3V3\AwsS3V3Adapter(
                $client,
                $config['bucket'],
                '', // prefix
                new \League\Flysystem\AwsS3V3\PortableVisibilityConverter(
                    \League\Flysystem\Visibility::PUBLIC
                )
            );

            return new \League\Flysystem\Filesystem($adapter);

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'createS3Filesystem']);
            return null;
        }
    }

    /**
     * Obtiene los discos disponibles (endpoint API)
     */
    public function getAvailableDisksApi()
    {
        SessionSecurity::startSession();

        $disks = $this->getAvailableDisks();

        return $this->jsonResponse([
            'success' => true,
            'disks' => $disks
        ]);
    }

    /**
     * Obtiene información de cuota de almacenamiento del tenant (endpoint API)
     */
    public function getStorageQuotaApi()
    {
        SessionSecurity::startSession();

        $storageInfo = $this->getTenantStorageInfo();

        // Formatear para mejor legibilidad
        $quotaBytes = $storageInfo['quota_mb'] * 1024 * 1024;

        return $this->jsonResponse([
            'success' => true,
            'storage' => [
                'quota_mb' => $storageInfo['quota_mb'],
                'quota_bytes' => $quotaBytes,
                'quota_formatted' => $this->formatBytes($quotaBytes),
                'used_bytes' => $storageInfo['used_bytes'],
                'used_formatted' => $this->formatBytes($storageInfo['used_bytes']),
                'available_bytes' => $storageInfo['available_bytes'],
                'available_formatted' => $this->formatBytes($storageInfo['available_bytes']),
                'percentage' => $storageInfo['percentage'],
                'unlimited' => $storageInfo['unlimited']
            ]
        ]);
    }

    /**
     * Formatea bytes a unidad legible (KB, MB, GB)
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    private function jsonResponse(array $data, int $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
