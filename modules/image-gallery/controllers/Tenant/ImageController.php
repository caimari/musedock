<?php

namespace ImageGallery\Controllers\Tenant;

use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Services\TenantManager;
use Screenart\Musedock\Env;
use ImageGallery\Models\Gallery;
use ImageGallery\Models\GalleryImage;
use ImageGallery\Models\GallerySetting;
use MediaManager\Models\Media;

/**
 * ImageController - Tenant
 *
 * Gestión de imágenes dentro de las galerías del tenant
 */
class ImageController
{
    private array $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg'
    ];

    /**
     * Subida de imágenes (AJAX)
     */
    public function upload($galleryId)
    {
        SessionSecurity::startSession();
        $tenantId = TenantManager::currentTenantId();

        header('Content-Type: application/json');

        if ($tenantId === null) {
            echo json_encode([
                'success' => false,
                'error' => __gallery('gallery.tenant_required')
            ]);
            exit;
        }

        try {
            $gallery = Gallery::find((int) $galleryId);

            // Verificar propiedad de la galería
            if (!$gallery || $gallery->tenant_id !== $tenantId) {
                echo json_encode([
                    'success' => false,
                    'error' => __gallery('gallery.not_found')
                ]);
                exit;
            }

            // Verificar que hay archivos
            if (empty($_FILES['images'])) {
                echo json_encode([
                    'success' => false,
                    'error' => __gallery('image.no_files')
                ]);
                exit;
            }

            $settings = GallerySetting::getAll($tenantId);
            $maxSize = ((int) ($settings['max_upload_size_mb'] ?? 10)) * 1024 * 1024;

            $uploadedImages = [];
            $errors = [];

            // Procesar múltiples archivos
            $files = $this->normalizeFilesArray($_FILES['images']);

            foreach ($files as $file) {
                $result = $this->processUpload($file, $gallery, $tenantId, $maxSize, $settings);

                if ($result['success']) {
                    $uploadedImages[] = $result['image'];
                } else {
                    $errors[] = $file['name'] . ': ' . $result['error'];
                }
            }

            // Actualizar thumbnail de la galería si es la primera imagen
            if (!$gallery->thumbnail_url && !empty($uploadedImages)) {
                $gallery->setThumbnailFromFirstImage();
            }

            echo json_encode([
                'success' => true,
                'uploaded' => count($uploadedImages),
                'images' => $uploadedImages,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            error_log("ImageGallery tenant upload error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => __gallery('image.upload_error')
            ]);
        }
        exit;
    }

    /**
     * Procesa la subida de un archivo individual
     */
    private function processUpload(array $file, Gallery $gallery, int $tenantId, int $maxSize, array $settings): array
    {
        // Validar errores de subida
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => $this->getUploadError($file['error'])];
        }

        // Validar tamaño
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'error' => __gallery('validation.file_too_large')];
        }

        // Validar tipo MIME
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!isset($this->allowedMimes[$mimeType])) {
            return ['success' => false, 'error' => __gallery('validation.invalid_type')];
        }

        $defaultDisk = (string) Env::get('FILESYSTEM_DISK', 'media');
        if ($defaultDisk === 'local') {
            // "local" en MuseDock es legacy (/public/assets/uploads). Para galerías preferimos "media".
            $defaultDisk = 'media';
        }
        $diskName = $this->getDiskNameForContext($_POST['disk'] ?? $defaultDisk);
        [$filesystem, $localRoot, $diskConfig] = $this->createFilesystemForDisk($diskName);

        $extension = $this->allowedMimes[$mimeType];
        $hash = hash_file('sha256', $file['tmp_name']);
        $uniqueName = $this->generateUniqueFileName($file['name'], $extension, $hash);

        // Guardar siguiendo la convención de Media Manager (tenant_{id}/Y/m/...)
        $subPath = "tenant_{$tenantId}/galleries/gallery_{$gallery->id}";
        $yearMonth = date('Y/m');
        $relativePath = "{$subPath}/{$yearMonth}/{$uniqueName}";
        $dirPath = dirname($relativePath);

        if (!$filesystem->directoryExists($dirPath)) {
            $filesystem->createDirectory($dirPath);
            if ($localRoot) {
                @chmod($localRoot . '/' . $dirPath, 0755);
            }
        }

        // Subir archivo usando Flysystem
        $stream = fopen($file['tmp_name'], 'r+');
        if (!$stream) {
            return ['success' => false, 'error' => __gallery('image.move_error')];
        }
        $filesystem->writeStream($relativePath, $stream, $this->buildWriteConfig($diskName, $mimeType));
        fclose($stream);

        // Para discos locales, intentar asegurar permisos
        if ($localRoot) {
            @chmod($localRoot . '/' . $relativePath, 0644);
        }

        // Obtener dimensiones (desde tmp para evitar depender de path remoto)
        $dimensions = $this->getImageDimensions($file['tmp_name']);

        // Crear registro en Media Manager (para que aparezca en /admin/media)
        $userId = $_SESSION['admin']['id'] ?? ($_SESSION['user']['id'] ?? null);
        $publicToken = Media::generatePublicToken();
        $slug = Media::generateSlug($file['name']);
        $extensionForSeo = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $seoFilename = $slug . '-' . $publicToken . '.' . $extensionForSeo;

        Media::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'folder_id' => null,
            'disk' => $diskName,
            'path' => $relativePath,
            'public_token' => $publicToken,
            'slug' => $slug,
            'seo_filename' => $seoFilename,
            'filename' => $file['name'],
            'mime_type' => $mimeType,
            'size' => $file['size'],
            'metadata' => null,
        ]);

        // Usar el mismo patrón de Media Manager: /media/t/{token} (redirige según el disk)
        $imageUrl = '/media/t/' . $publicToken;

        $image = GalleryImage::create([
            'gallery_id' => $gallery->id,
            'disk' => $diskName,
            'public_token' => $publicToken,
            'file_name' => $file['name'],
            'file_path' => $relativePath,
            'file_hash' => $hash,
            'file_size' => $file['size'],
            'mime_type' => $mimeType,
            'image_url' => $imageUrl,
            'thumbnail_url' => null,
            'medium_url' => null,
            'title' => pathinfo($file['name'], PATHINFO_FILENAME),
            'alt_text' => pathinfo($file['name'], PATHINFO_FILENAME),
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'sort_order' => GalleryImage::getNextSortOrder($gallery->id),
            'is_active' => true,
            'metadata' => $this->extractMetadata($file['tmp_name'])
        ]);

        if ($image) {
            return ['success' => true, 'image' => $image->toArray()];
        }

        return ['success' => false, 'error' => __gallery('image.save_error')];
    }

    private function getDiskNameForContext(?string $requestedDisk): string
    {
        $validDisks = ['local', 'media', 'r2', 's3'];
        $diskName = in_array((string)$requestedDisk, $validDisks, true) ? (string)$requestedDisk : 'media';

        $filesystemsConfig = require APP_ROOT . '/config/filesystems.php';
        $diskConfig = $filesystemsConfig['disks'][$diskName] ?? null;
        if (!$diskConfig || !is_array($diskConfig)) {
            $diskName = 'media';
        }

        // Si es un disco S3-compatible pero no tiene credenciales, fallback a media.
        $diskConfig = $filesystemsConfig['disks'][$diskName] ?? null;
        if (is_array($diskConfig) && ($diskConfig['driver'] ?? null) === 's3') {
            if (empty($diskConfig['key']) || empty($diskConfig['secret']) || empty($diskConfig['bucket'])) {
                return 'media';
            }
        }

        // Validar disponibilidad del disco en tenant (flags)
        if ($diskName === 'r2' && !Env::get('TENANT_DISK_R2_ENABLED', true)) {
            return 'media';
        }
        if ($diskName === 's3' && !Env::get('TENANT_DISK_S3_ENABLED', false)) {
            return 'media';
        }
        if ($diskName === 'local' && !Env::get('TENANT_DISK_LOCAL_ENABLED', false)) {
            return 'media';
        }
        if ($diskName === 'media' && !Env::get('TENANT_DISK_MEDIA_ENABLED', true)) {
            return 'media';
        }

        return $diskName;
    }

    private function createFilesystemForDisk(string $diskName): array
    {
        $filesystemsConfig = require APP_ROOT . '/config/filesystems.php';
        $diskConfig = $filesystemsConfig['disks'][$diskName] ?? null;

        if (!$diskConfig || !is_array($diskConfig)) {
            $diskName = 'media';
            $diskConfig = $filesystemsConfig['disks']['media'] ?? [];
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
        $filesystemsConfig = require APP_ROOT . '/config/filesystems.php';
        $diskConfig = $filesystemsConfig['disks'][$diskName] ?? [];

        $cfg = [
            'visibility' => 'public',
        ];

        if (($diskConfig['driver'] ?? null) === 's3') {
            $cfg['ContentType'] = $mimeType ?: 'application/octet-stream';
            $cfg['CacheControl'] = 'public, max-age=31536000, immutable';
        }

        return $cfg;
    }

    private function createS3Filesystem(array $config): ?\League\Flysystem\Filesystem
    {
        try {
            if (empty($config['key']) || empty($config['secret']) || empty($config['bucket'])) {
                return null;
            }

            $clientConfig = [
                'version' => 'latest',
                'region' => $config['region'] ?? 'auto',
                'credentials' => [
                    'key' => $config['key'],
                    'secret' => $config['secret'],
                ],
            ];

            if (!empty($config['endpoint'])) {
                $clientConfig['endpoint'] = $config['endpoint'];
            }
            if (isset($config['use_path_style_endpoint'])) {
                $clientConfig['use_path_style_endpoint'] = $config['use_path_style_endpoint'];
            }

            $client = new \Aws\S3\S3Client($clientConfig);
            $adapter = new \League\Flysystem\AwsS3V3\AwsS3V3Adapter(
                $client,
                $config['bucket'],
                '',
                null,
                null,
                ['ACL' => 'public-read']
            );

            return new \League\Flysystem\Filesystem($adapter);
        } catch (\Exception $e) {
            error_log("Error creating S3 filesystem (tenant gallery): " . $e->getMessage());
            return null;
        }
    }

    /**
     * Genera thumbnail de una imagen
     */
    private function generateThumbnail(
        string $sourcePath,
        string $destDir,
        string $fileName,
        int $maxWidth,
        int $maxHeight,
        string $prefix
    ): ?string {
        try {
            $imageInfo = getimagesize($sourcePath);
            if (!$imageInfo) {
                return null;
            }

            list($origWidth, $origHeight, $type) = $imageInfo;

            // Calcular nuevas dimensiones manteniendo proporción
            $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);

            // Solo redimensionar si es necesario
            if ($ratio >= 1) {
                return null;
            }

            $newWidth = (int) ($origWidth * $ratio);
            $newHeight = (int) ($origHeight * $ratio);

            // Crear imagen desde el archivo original
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $source = imagecreatefromjpeg($sourcePath);
                    break;
                case IMAGETYPE_PNG:
                    $source = imagecreatefrompng($sourcePath);
                    break;
                case IMAGETYPE_GIF:
                    $source = imagecreatefromgif($sourcePath);
                    break;
                case IMAGETYPE_WEBP:
                    $source = imagecreatefromwebp($sourcePath);
                    break;
                default:
                    return null;
            }

            if (!$source) {
                return null;
            }

            // Crear imagen de destino
            $dest = imagecreatetruecolor($newWidth, $newHeight);

            // Preservar transparencia para PNG y GIF
            if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
                imagealphablending($dest, false);
                imagesavealpha($dest, true);
                $transparent = imagecolorallocatealpha($dest, 255, 255, 255, 127);
                imagefilledrectangle($dest, 0, 0, $newWidth, $newHeight, $transparent);
            }

            // Redimensionar
            imagecopyresampled(
                $dest,
                $source,
                0, 0, 0, 0,
                $newWidth, $newHeight,
                $origWidth, $origHeight
            );

            // Generar nombre del thumbnail
            $ext = pathinfo($fileName, PATHINFO_EXTENSION);
            $base = pathinfo($fileName, PATHINFO_FILENAME);
            $thumbName = $base . '-' . $prefix . '.' . $ext;
            $thumbPath = $destDir . $thumbName;

            // Guardar
            $quality = (int) (GallerySetting::get('image_quality', null, 85));

            switch ($type) {
                case IMAGETYPE_JPEG:
                    imagejpeg($dest, $thumbPath, $quality);
                    break;
                case IMAGETYPE_PNG:
                    imagepng($dest, $thumbPath, 9);
                    break;
                case IMAGETYPE_GIF:
                    imagegif($dest, $thumbPath);
                    break;
                case IMAGETYPE_WEBP:
                    imagewebp($dest, $thumbPath, $quality);
                    break;
            }

            // Liberar memoria
            imagedestroy($source);
            imagedestroy($dest);

            // Tenant ahora usa storage (media manager). Mantener compatibilidad solo para legacy.
            return str_replace(rtrim($_SERVER['DOCUMENT_ROOT'], '/'), '', $thumbPath);

        } catch (\Exception $e) {
            error_log("Error generating thumbnail: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Actualiza información de una imagen
     */
    public function update($imageId)
    {
        SessionSecurity::startSession();
        $tenantId = TenantManager::currentTenantId();

        header('Content-Type: application/json');

        if ($tenantId === null) {
            echo json_encode([
                'success' => false,
                'error' => __gallery('gallery.tenant_required')
            ]);
            exit;
        }

        $image = GalleryImage::find((int) $imageId);

        if (!$image) {
            echo json_encode([
                'success' => false,
                'error' => __gallery('image.not_found')
            ]);
            exit;
        }

        // Verificar propiedad
        $gallery = $image->gallery();
        if (!$gallery || $gallery->tenant_id !== $tenantId) {
            echo json_encode([
                'success' => false,
                'error' => __gallery('image.not_found')
            ]);
            exit;
        }

        // Obtener datos del body (JSON)
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

        $image->update([
            'title' => trim($input['title'] ?? $image->title),
            'alt_text' => trim($input['alt_text'] ?? $image->alt_text),
            'caption' => trim($input['caption'] ?? $image->caption),
            'link_url' => trim($input['link_url'] ?? $image->link_url),
            'link_target' => $input['link_target'] ?? $image->link_target,
            'is_active' => isset($input['is_active']) ? (bool) $input['is_active'] : $image->is_active
        ]);

        echo json_encode([
            'success' => true,
            'image' => $image->toArray()
        ]);
        exit;
    }

    /**
     * Elimina una imagen
     */
    public function destroy($imageId)
    {
        SessionSecurity::startSession();
        $tenantId = TenantManager::currentTenantId();

        header('Content-Type: application/json');

        if ($tenantId === null) {
            echo json_encode([
                'success' => false,
                'error' => __gallery('gallery.tenant_required')
            ]);
            exit;
        }

        $image = GalleryImage::find((int) $imageId);

        if (!$image) {
            echo json_encode([
                'success' => false,
                'error' => __gallery('image.not_found')
            ]);
            exit;
        }

        // Verificar propiedad
        $gallery = $image->gallery();
        if (!$gallery || $gallery->tenant_id !== $tenantId) {
            echo json_encode([
                'success' => false,
                'error' => __gallery('image.not_found')
            ]);
            exit;
        }

        $galleryId = $image->gallery_id;

        // Eliminar archivo físico y registro
        $deleted = $image->deleteWithFile();

        if ($deleted) {
            // Actualizar thumbnail de la galería si era la imagen de portada
            $gallery = Gallery::find($galleryId);
            if ($gallery) {
                $gallery->setThumbnailFromFirstImage();
            }

            echo json_encode([
                'success' => true,
                'message' => __gallery('image.deleted')
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => __gallery('image.delete_error')
            ]);
        }
        exit;
    }

    /**
     * Reordena las imágenes de una galería
     */
    public function reorder($galleryId)
    {
        SessionSecurity::startSession();
        $tenantId = TenantManager::currentTenantId();

        header('Content-Type: application/json');

        if ($tenantId === null) {
            echo json_encode([
                'success' => false,
                'error' => __gallery('gallery.tenant_required')
            ]);
            exit;
        }

        // Verificar propiedad de la galería
        $gallery = Gallery::find((int) $galleryId);
        if (!$gallery || $gallery->tenant_id !== $tenantId) {
            echo json_encode([
                'success' => false,
                'error' => __gallery('gallery.not_found')
            ]);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['order']) || !is_array($input['order'])) {
            echo json_encode([
                'success' => false,
                'error' => __gallery('image.invalid_order')
            ]);
            exit;
        }

        $success = GalleryImage::reorder((int) $galleryId, $input['order']);

        echo json_encode([
            'success' => $success,
            'message' => $success ? __gallery('image.reordered') : __gallery('image.reorder_error')
        ]);
        exit;
    }

    /**
     * Establece una imagen como portada de la galería
     */
    public function setThumbnail($imageId)
    {
        SessionSecurity::startSession();
        $tenantId = TenantManager::currentTenantId();

        header('Content-Type: application/json');

        if ($tenantId === null) {
            echo json_encode([
                'success' => false,
                'error' => __gallery('gallery.tenant_required')
            ]);
            exit;
        }

        $image = GalleryImage::find((int) $imageId);

        if (!$image) {
            echo json_encode([
                'success' => false,
                'error' => __gallery('image.not_found')
            ]);
            exit;
        }

        $gallery = $image->gallery();

        // Verificar propiedad
        if (!$gallery || $gallery->tenant_id !== $tenantId) {
            echo json_encode([
                'success' => false,
                'error' => __gallery('gallery.not_found')
            ]);
            exit;
        }

        $gallery->update([
            'thumbnail_url' => $image->thumbnail_url ?: $image->image_url
        ]);

        echo json_encode([
            'success' => true,
            'message' => __gallery('image.thumbnail_set')
        ]);
        exit;
    }

    /**
     * Normaliza el array de archivos para múltiples uploads
     */
    private function normalizeFilesArray(array $files): array
    {
        $normalized = [];

        if (is_array($files['name'])) {
            $count = count($files['name']);

            for ($i = 0; $i < $count; $i++) {
                $normalized[] = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                ];
            }
        } else {
            $normalized[] = $files;
        }

        return $normalized;
    }

    /**
     * Obtiene las dimensiones de una imagen
     */
    private function getImageDimensions(string $path): array
    {
        $info = @getimagesize($path);

        return [
            'width' => $info[0] ?? null,
            'height' => $info[1] ?? null
        ];
    }

    /**
     * Extrae metadatos de la imagen
     */
    private function extractMetadata(string $path): array
    {
        $metadata = [];

        // Intentar extraer EXIF
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($path, 'ANY_TAG', true);

            if ($exif) {
                $metadata['exif'] = [
                    'camera' => $exif['IFD0']['Model'] ?? null,
                    'date_taken' => $exif['EXIF']['DateTimeOriginal'] ?? null,
                    'exposure' => $exif['EXIF']['ExposureTime'] ?? null,
                    'aperture' => $exif['EXIF']['FNumber'] ?? null,
                    'iso' => $exif['EXIF']['ISOSpeedRatings'] ?? null,
                    'focal_length' => $exif['EXIF']['FocalLength'] ?? null,
                ];
            }
        }

        return $metadata;
    }

    /**
     * Genera un nombre de archivo único
     */
    private function generateUniqueFileName(string $originalName, string $extension, string $hash): string
    {
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $baseName = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $baseName);
        $baseName = substr($baseName, 0, 50);

        $shortHash = substr($hash, 0, 8);
        $timestamp = time();

        return "{$baseName}-{$timestamp}-{$shortHash}.{$extension}";
    }

    /**
     * Obtiene mensaje de error de subida
     */
    private function getUploadError(int $errorCode): string
    {
        $errors = [
            UPLOAD_ERR_INI_SIZE => __gallery('validation.file_too_large'),
            UPLOAD_ERR_FORM_SIZE => __gallery('validation.file_too_large'),
            UPLOAD_ERR_PARTIAL => __gallery('validation.upload_partial'),
            UPLOAD_ERR_NO_FILE => __gallery('image.no_files'),
            UPLOAD_ERR_NO_TMP_DIR => __gallery('image.server_error'),
            UPLOAD_ERR_CANT_WRITE => __gallery('image.server_error'),
            UPLOAD_ERR_EXTENSION => __gallery('image.server_error'),
        ];

        return $errors[$errorCode] ?? __gallery('image.upload_error');
    }
}
