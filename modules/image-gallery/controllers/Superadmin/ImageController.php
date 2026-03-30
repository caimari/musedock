<?php

namespace ImageGallery\Controllers\Superadmin;

use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Traits\RequiresPermission;
use ImageGallery\Models\Gallery;
use ImageGallery\Models\GalleryImage;
use ImageGallery\Models\GallerySetting;
use MediaManager\Models\Media;
use Screenart\Musedock\Env;

/**
 * ImageController - Superadmin
 *
 * Gestión de imágenes dentro de las galerías (subida, edición, eliminación)
 */
class ImageController
{
    use RequiresPermission;

    private string $uploadDir = '/uploads/galleries/';
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
        $this->checkPermission('image_gallery.create');

        header('Content-Type: application/json');

        try {
            $gallery = Gallery::find((int) $galleryId);

            if (!$gallery) {
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

            $settings = GallerySetting::getAll($gallery->tenant_id);
            $maxSize = ((int) ($settings['max_upload_size_mb'] ?? 10)) * 1024 * 1024;

            $uploadedImages = [];
            $errors = [];

            // Obtener disk seleccionado (default: media; soporta FILESYSTEM_DISK para instalaciones en R2)
            $defaultDisk = (string) Env::get('FILESYSTEM_DISK', 'media');
            if ($defaultDisk === 'local') {
                // "local" es legacy (/public/assets/uploads). Para galerías preferimos "media".
                $defaultDisk = 'media';
            }
            $disk = $_POST['disk'] ?? $defaultDisk;

            // Procesar múltiples archivos
            $files = $this->normalizeFilesArray($_FILES['images']);

            foreach ($files as $file) {
                $result = $this->processUpload($file, $gallery, $maxSize, $settings, $disk);

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
            error_log("ImageGallery upload error: " . $e->getMessage());
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
    private function processUpload(array $file, Gallery $gallery, int $maxSize, array $settings, string $disk = 'media'): array
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

        // Validar disco
        $validDisks = ['local', 'media', 'r2', 's3'];
        if (!in_array($disk, $validDisks)) {
            $disk = 'media';
        }

        // Obtener configuración del disco desde filesystems.php
        $filesystemsConfig = require __DIR__ . '/../../../../config/filesystems.php';
        $diskConfig = $filesystemsConfig['disks'][$disk] ?? null;

        if (!$diskConfig || !is_array($diskConfig)) {
            return ['success' => false, 'error' => __gallery('image.disk_not_configured')];
        }

        try {
            // Generar nombre único para el archivo
            $extension = $this->allowedMimes[$mimeType];
            $hash = hash_file('sha256', $file['tmp_name']);
            $uniqueName = $this->generateUniqueFileName($file['name'], $extension, $hash);

            // Preparar ruta relativa dentro del disco (convención tipo Media Manager)
            $subPath = ($gallery->tenant_id ? ('tenant_' . (int)$gallery->tenant_id) : 'global') . '/galleries/gallery_' . $gallery->id;
            $yearMonth = date('Y/m');
            $relativePath = "{$subPath}/{$yearMonth}/{$uniqueName}";
            $dirPath = dirname($relativePath);

            // Crear filesystem según el tipo de disco
            if ($diskConfig['driver'] === 's3') {
                // Para S3/R2: usar el adaptador S3
                $filesystem = $this->createS3Filesystem($diskConfig);
                if (!$filesystem) {
                    return ['success' => false, 'error' => __gallery('image.cloud_storage_error')];
                }
                $isCloudStorage = true;
                $localRoot = null;
            } else {
                // Para discos locales
                $appRoot = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 4);
                $localRoot = $appRoot . $diskConfig['root'];
                $adapter = new \League\Flysystem\Local\LocalFilesystemAdapter(
                    $localRoot,
                    null,
                    LOCK_EX,
                    \League\Flysystem\Local\LocalFilesystemAdapter::DISALLOW_LINKS
                );
                $filesystem = new \League\Flysystem\Filesystem($adapter);
                $isCloudStorage = false;
            }

            // Crear directorio si no existe
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

            $filesystem->writeStream($relativePath, $stream);
            fclose($stream);

            // Establecer permisos solo para discos locales
            if ($localRoot) {
                @chmod($localRoot . '/' . $relativePath, 0644);
            }

            // Obtener dimensiones de la imagen desde el archivo temporal
            $dimensions = $this->getImageDimensions($file['tmp_name']);

            // Generar thumbnails
            $thumbnailUrl = null;
            $mediumUrl = null;
            $thumbnailPath = null;
            $mediumPath = null;

            if ($mimeType !== 'image/svg+xml') {
                // Generar thumbnail
                $thumbResult = $this->generateThumbnailToFilesystem(
                    $file['tmp_name'],
                    $filesystem,
                    $dirPath,
                    $uniqueName,
                    (int) ($settings['thumbnail_width'] ?? 150),
                    (int) ($settings['thumbnail_height'] ?? 150),
                    'thumb',
                    $mimeType,
                    $localRoot
                );

                if ($thumbResult) {
                    $thumbnailPath = $thumbResult['path'];
                    $thumbnailUrl = $this->generatePublicUrl($disk, $diskConfig, $thumbnailPath);
                }

                // Generar versión media
                $mediumResult = $this->generateThumbnailToFilesystem(
                    $file['tmp_name'],
                    $filesystem,
                    $dirPath,
                    $uniqueName,
                    (int) ($settings['medium_width'] ?? 600),
                    (int) ($settings['medium_height'] ?? 600),
                    'medium',
                    $mimeType,
                    $localRoot
                );

                if ($mediumResult) {
                    $mediumPath = $mediumResult['path'];
                    $mediumUrl = $this->generatePublicUrl($disk, $diskConfig, $mediumPath);
                }
            }

        // Crear registro en Media Manager (fuente de verdad para /admin/media)
        $userId = $_SESSION['super_admin']['id'] ?? ($_SESSION['admin']['id'] ?? ($_SESSION['user']['id'] ?? null));
        $publicToken = Media::generatePublicToken();
        $slug = Media::generateSlug($file['name']);
        $extensionForSeo = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $seoFilename = $slug . '-' . $publicToken . '.' . $extensionForSeo;

        Media::create([
            'tenant_id' => $gallery->tenant_id ?: null,
            'user_id' => $userId,
            'folder_id' => null,
            'disk' => $disk,
            'path' => $relativePath,
            'public_token' => $publicToken,
            'slug' => $slug,
            'seo_filename' => $seoFilename,
            'filename' => $file['name'],
            'mime_type' => $mimeType,
            'size' => $file['size'],
            'metadata' => null,
        ]);

        // URLs públicas basadas en el token del Media Manager
        $imageUrl = '/media/t/' . $publicToken;

        // Si generamos archivos extra en el mismo filesystem local "media", servirlos via /media/file con validación
        $thumbnailUrlFinal = null;
        $mediumUrlFinal = null;
        if (in_array($disk, ['media', 'private'], true)) {
            if ($thumbnailPath) {
                $thumbnailUrlFinal = '/media/file/' . ltrim($thumbnailPath, '/') . '?token=' . $publicToken . '-thumb';
            }
            if ($mediumPath) {
                $mediumUrlFinal = '/media/file/' . ltrim($mediumPath, '/') . '?token=' . $publicToken . '-medium';
            }
        } else {
            // Para cloud: usar URL directa generada anteriormente (si existe)
            $thumbnailUrlFinal = $thumbnailUrl ?: null;
            $mediumUrlFinal = $mediumUrl ?: null;
        }

        // Crear registro en BD
        $image = GalleryImage::create([
            'gallery_id' => $gallery->id,
            'disk' => $disk,
            'public_token' => $publicToken,
            'file_name' => $file['name'],
            'file_path' => $relativePath,
            'file_hash' => $hash,
            'file_size' => $file['size'],
            'mime_type' => $mimeType,
            'image_url' => $imageUrl,
            'thumbnail_url' => $thumbnailUrlFinal,
            'medium_url' => $mediumUrlFinal,
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

        } catch (\Exception $e) {
            error_log("ImageGallery processUpload error: " . $e->getMessage());
            return ['success' => false, 'error' => __gallery('image.upload_error')];
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
                return null; // La imagen original es más pequeña
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

            // Devolver ruta relativa
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
        $this->checkPermission('image_gallery.edit');

        header('Content-Type: application/json');

        $image = GalleryImage::find((int) $imageId);

        if (!$image) {
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
        $this->checkPermission('image_gallery.delete');

        header('Content-Type: application/json');

        $image = GalleryImage::find((int) $imageId);

        if (!$image) {
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
        $this->checkPermission('image_gallery.edit');

        header('Content-Type: application/json');

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
        $this->checkPermission('image_gallery.edit');

        header('Content-Type: application/json');

        $image = GalleryImage::find((int) $imageId);

        if (!$image) {
            echo json_encode([
                'success' => false,
                'error' => __gallery('image.not_found')
            ]);
            exit;
        }

        $gallery = $image->gallery();

        if ($gallery) {
            $gallery->update([
                'thumbnail_url' => $image->thumbnail_url ?: $image->image_url
            ]);

            echo json_encode([
                'success' => true,
                'message' => __gallery('image.thumbnail_set')
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => __gallery('gallery.not_found')
            ]);
        }
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

    /**
     * Genera thumbnail y lo guarda en el filesystem
     */
    private function generateThumbnailToFilesystem(
        string $sourcePath,
        \League\Flysystem\Filesystem $filesystem,
        string $destDir,
        string $fileName,
        int $maxWidth,
        int $maxHeight,
        string $prefix,
        string $mimeType,
        ?string $localRoot
    ): ?array {
        try {
            $imageInfo = @getimagesize($sourcePath);
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
            $thumbPath = $destDir . '/' . $thumbName;

            // Guardar en un archivo temporal
            $tempFile = tempnam(sys_get_temp_dir(), 'gallery_thumb_');
            $quality = (int) (GallerySetting::get('image_quality', null, 85));

            switch ($type) {
                case IMAGETYPE_JPEG:
                    imagejpeg($dest, $tempFile, $quality);
                    break;
                case IMAGETYPE_PNG:
                    imagepng($dest, $tempFile, 9);
                    break;
                case IMAGETYPE_GIF:
                    imagegif($dest, $tempFile);
                    break;
                case IMAGETYPE_WEBP:
                    imagewebp($dest, $tempFile, $quality);
                    break;
            }

            // Liberar memoria
            imagedestroy($source);
            imagedestroy($dest);

            // Subir el thumbnail al filesystem
            $stream = fopen($tempFile, 'r');
            if ($stream) {
                $filesystem->writeStream($thumbPath, $stream);
                fclose($stream);

                // Establecer permisos solo para discos locales
                if ($localRoot) {
                    @chmod($localRoot . '/' . $thumbPath, 0644);
                }
            }

            // Eliminar archivo temporal
            @unlink($tempFile);

            return ['path' => $thumbPath];

        } catch (\Exception $e) {
            error_log("Error generating thumbnail: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Genera URL pública según el tipo de disco
     */
    private function generatePublicUrl(string $disk, array $diskConfig, string $relativePath): string
    {
        $baseUrl = rtrim(getenv('APP_URL') ?: '', '/');

        if ($diskConfig['driver'] === 's3') {
            // Para S3/R2: usar URL configurada
            if (!empty($diskConfig['url'])) {
                return rtrim($diskConfig['url'], '/') . '/' . ltrim($relativePath, '/');
            }
            // Fallback: construir URL con endpoint
            $endpoint = $diskConfig['endpoint'] ?? '';
            $bucket = $diskConfig['bucket'] ?? '';
            return rtrim($endpoint, '/') . '/' . $bucket . '/' . ltrim($relativePath, '/');
        }

        // Para discos locales: usar URL base configurada
        $urlPath = $diskConfig['url'] ?? '/storage';
        return $baseUrl . rtrim($urlPath, '/') . '/' . ltrim($relativePath, '/');
    }

    /**
     * Crea un filesystem S3/R2
     */
    private function createS3Filesystem(array $config): ?\League\Flysystem\Filesystem
    {
        try {
            // Verificar que tenemos las credenciales necesarias
            if (empty($config['key']) || empty($config['secret']) || empty($config['bucket'])) {
                error_log('S3/R2: Credenciales incompletas');
                return null;
            }

            // Verificar que la clase de AWS SDK existe
            if (!class_exists('\Aws\S3\S3Client')) {
                error_log('S3/R2: AWS SDK no está instalado. Ejecuta: composer require aws/aws-sdk-php');
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
            }

            // Para algunos proveedores como R2
            if (isset($config['use_path_style_endpoint'])) {
                $clientConfig['use_path_style_endpoint'] = $config['use_path_style_endpoint'];
            }

            $client = new \Aws\S3\S3Client($clientConfig);

            // Crear adaptador y filesystem
            $adapter = new \League\Flysystem\AwsS3V3\AwsS3V3Adapter(
                $client,
                $config['bucket'],
                '', // prefix
                null, // visibility converter
                null, // mime type detector
                ['ACL' => 'public-read'] // opciones de upload
            );

            return new \League\Flysystem\Filesystem($adapter);

        } catch (\Exception $e) {
            error_log("Error creating S3 filesystem: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Genera un token público permanente (como en sliders/media manager)
     */
    private function generatePublicToken(): string
    {
        return bin2hex(random_bytes(32)); // 64 caracteres hexadecimales
    }

    /**
     * Genera URL pública con token para servir archivos de forma segura
     * Usa el mismo sistema que Media Manager para servir archivos desde storage
     */
    private function generatePublicUrlWithToken(string $disk, string $relativePath, string $token): string
    {
        $baseUrl = rtrim(getenv('APP_URL') ?: '', '/');

        // Para discos en storage (media, private), usar el controlador de Media Manager
        if (in_array($disk, ['media', 'private'])) {
            // URL: /media/file/gallery-8/filename.jpg?token=xxx
            return $baseUrl . '/media/file/' . ltrim($relativePath, '/') . '?token=' . $token;
        }

        // Para discos públicos (local), usar URL directa
        if ($disk === 'local') {
            return $baseUrl . '/uploads/galleries/' . ltrim($relativePath, '/');
        }

        // Para S3/R2, usar URL configurada
        $filesystemsConfig = require __DIR__ . '/../../../../config/filesystems.php';
        $diskConfig = $filesystemsConfig['disks'][$disk] ?? null;

        if ($diskConfig && $diskConfig['driver'] === 's3') {
            if (!empty($diskConfig['url'])) {
                return rtrim($diskConfig['url'], '/') . '/' . ltrim($relativePath, '/');
            }
        }

        // Fallback
        return $baseUrl . '/storage/' . ltrim($relativePath, '/');
    }
}
