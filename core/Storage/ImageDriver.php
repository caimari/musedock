<?php

namespace Screenart\Musedock\Storage;

use Screenart\Musedock\Security\FileUploadValidator;
use Screenart\Musedock\Logger;

/**
 * Clase base para drivers de imágenes
 *
 * Proporciona funcionalidad común para la subida, validación y procesamiento
 * seguro de imágenes. Todos los drivers especializados heredan de esta clase.
 *
 * CARACTERÍSTICAS DE SEGURIDAD:
 * - Validación estricta de tipo MIME
 * - Recodificación automática de imágenes
 * - Limpieza de metadatos EXIF
 * - Detección de contenido malicioso
 * - Nombres de archivo hasheados
 * - Almacenamiento fuera de public/
 *
 * @package Screenart\Musedock\Storage
 * @version 1.0.0
 */
abstract class ImageDriver
{
    /**
     * Directorio base de almacenamiento
     */
    protected string $storageBase = '';

    /**
     * Tipo de driver (avatar, header, gallery, private)
     */
    protected string $driverType = '';

    /**
     * Tamaño máximo permitido en bytes
     */
    protected int $maxSize = 10485760; // 10MB por defecto

    /**
     * MIME types permitidos
     */
    protected array $allowedMimes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /**
     * Extensiones permitidas
     */
    protected array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * Si debe recodificar la imagen
     */
    protected bool $shouldReencode = true;

    /**
     * Si debe generar thumbnails
     */
    protected bool $generateThumbnails = false;

    /**
     * Configuración de thumbnails
     */
    protected array $thumbnailSizes = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->storageBase = APP_ROOT . '/storage/app/public';
        $this->configure();
    }

    /**
     * Configuración específica del driver (override en clases hijas)
     */
    abstract protected function configure(): void;

    /**
     * Validaciones adicionales específicas del driver (override en clases hijas)
     */
    protected function additionalValidation(array $file): array
    {
        return ['valid' => true];
    }

    /**
     * Sube una imagen de forma segura
     *
     * @param array $file Archivo de $_FILES
     * @param array $options Opciones adicionales (user_id, tenant_id, etc.)
     * @return array Resultado de la subida
     */
    public function upload(array $file, array $options = []): array
    {
        try {
            // 1. Validación base con FileUploadValidator
            $validation = FileUploadValidator::validate($file, [
                'type' => 'image',
                'max_size' => $this->maxSize,
                'allowed_mimes' => $this->allowedMimes,
                'allowed_extensions' => $this->allowedExtensions,
            ]);

            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => $validation['error'],
                ];
            }

            // 2. Validaciones adicionales específicas del driver
            $additionalCheck = $this->additionalValidation($file);
            if (!$additionalCheck['valid']) {
                return [
                    'success' => false,
                    'error' => $additionalCheck['error'],
                ];
            }

            // 3. Generar nombre seguro y ruta
            $extension = $validation['extension'];
            $safeName = $this->generateSafeName($options);
            $filename = $safeName . '.' . $extension;
            $relativePath = $this->driverType . '/' . $filename;
            $fullPath = $this->storageBase . '/' . $relativePath;

            // 4. Crear directorio si no existe
            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // 5. Recodificar imagen si está habilitado (SEGURIDAD)
            if ($this->shouldReencode) {
                $reencodedPath = $this->reencodeImage($file['tmp_name'], $fullPath, $extension);
                if ($reencodedPath === false) {
                    return [
                        'success' => false,
                        'error' => 'Error al procesar la imagen',
                    ];
                }
            } else {
                // Mover archivo sin recodificar
                if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                    return [
                        'success' => false,
                        'error' => 'Error al guardar el archivo',
                    ];
                }
            }

            // 6. Generar thumbnails si está habilitado
            $thumbnails = [];
            if ($this->generateThumbnails) {
                $thumbnails = $this->createThumbnails($fullPath, $safeName, $extension);
            }

            // 7. Obtener metadatos de la imagen
            $metadata = $this->getImageMetadata($fullPath);

            // 8. Guardar en base de datos (si se proporciona)
            $dbRecord = null;
            if (isset($options['save_to_db']) && $options['save_to_db']) {
                $dbRecord = $this->saveToDatabase($relativePath, $metadata, $options);
            }

            // 9. Log de éxito
            Logger::log("Image uploaded successfully", 'INFO', [
                'driver' => $this->driverType,
                'filename' => $filename,
                'size' => $metadata['size'],
            ]);

            // 10. Retornar resultado
            return [
                'success' => true,
                'url' => '/storage/' . $relativePath,
                'path' => $relativePath,
                'full_path' => $fullPath,
                'filename' => $filename,
                'thumbnails' => $thumbnails,
                'metadata' => $metadata,
                'db_id' => $dbRecord['id'] ?? null,
            ];

        } catch (\Exception $e) {
            Logger::log("Image upload failed: " . $e->getMessage(), 'ERROR', [
                'driver' => $this->driverType,
                'file' => $file['name'] ?? 'unknown',
            ]);

            return [
                'success' => false,
                'error' => 'Error inesperado al subir la imagen',
            ];
        }
    }

    /**
     * Recodifica una imagen para eliminar exploits y optimizar
     *
     * @param string $sourcePath Ruta de la imagen original
     * @param string $destPath Ruta de destino
     * @param string $extension Extensión del archivo
     * @return string|false Ruta de destino o false si falla
     */
    protected function reencodeImage(string $sourcePath, string $destPath, string $extension)
    {
        // Detectar tipo de imagen
        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            return false;
        }

        $sourceType = $imageInfo[2];

        // Cargar imagen según tipo
        $sourceImage = match($sourceType) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
            IMAGETYPE_GIF => @imagecreatefromgif($sourcePath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($sourcePath),
            default => false,
        };

        if ($sourceImage === false) {
            return false;
        }

        // Preservar transparencia para PNG y GIF
        if ($sourceType === IMAGETYPE_PNG || $sourceType === IMAGETYPE_GIF) {
            imagealphablending($sourceImage, false);
            imagesavealpha($sourceImage, true);
        }

        // Guardar imagen recodificada
        $result = match($extension) {
            'jpg', 'jpeg' => imagejpeg($sourceImage, $destPath, 90),
            'png' => imagepng($sourceImage, $destPath, 9),
            'gif' => imagegif($sourceImage, $destPath),
            'webp' => imagewebp($sourceImage, $destPath, 90),
            default => false,
        };

        // Liberar memoria
        imagedestroy($sourceImage);

        return $result ? $destPath : false;
    }

    /**
     * Crea thumbnails de la imagen
     *
     * @param string $sourcePath Ruta de la imagen original
     * @param string $baseName Nombre base del archivo
     * @param string $extension Extensión
     * @return array Array con URLs de thumbnails
     */
    protected function createThumbnails(string $sourcePath, string $baseName, string $extension): array
    {
        $thumbnails = [];

        foreach ($this->thumbnailSizes as $sizeName => $dimensions) {
            $thumbName = $baseName . '_' . $sizeName . '.' . $extension;
            $thumbPath = $this->storageBase . '/' . $this->driverType . '/' . $thumbName;

            if ($this->resizeImage($sourcePath, $thumbPath, $dimensions['width'], $dimensions['height'])) {
                $thumbnails[$sizeName] = '/storage/' . $this->driverType . '/' . $thumbName;
            }
        }

        return $thumbnails;
    }

    /**
     * Redimensiona una imagen
     *
     * @param string $sourcePath Ruta origen
     * @param string $destPath Ruta destino
     * @param int $maxWidth Ancho máximo
     * @param int $maxHeight Alto máximo
     * @return bool
     */
    protected function resizeImage(string $sourcePath, string $destPath, int $maxWidth, int $maxHeight): bool
    {
        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            return false;
        }

        list($origWidth, $origHeight, $type) = $imageInfo;

        // Cargar imagen
        $sourceImage = match($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
            IMAGETYPE_GIF => @imagecreatefromgif($sourcePath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($sourcePath),
            default => false,
        };

        if ($sourceImage === false) {
            return false;
        }

        // Calcular nuevas dimensiones manteniendo aspecto
        $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
        $newWidth = (int)($origWidth * $ratio);
        $newHeight = (int)($origHeight * $ratio);

        // Crear imagen redimensionada
        $destImage = imagecreatetruecolor($newWidth, $newHeight);

        // Preservar transparencia
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
            imagealphablending($destImage, false);
            imagesavealpha($destImage, true);
            $transparent = imagecolorallocatealpha($destImage, 0, 0, 0, 127);
            imagefill($destImage, 0, 0, $transparent);
        }

        // Redimensionar
        imagecopyresampled($destImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        // Guardar
        $extension = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));
        $result = match($extension) {
            'jpg', 'jpeg' => imagejpeg($destImage, $destPath, 85),
            'png' => imagepng($destImage, $destPath, 8),
            'gif' => imagegif($destImage, $destPath),
            'webp' => imagewebp($destImage, $destPath, 85),
            default => false,
        };

        // Liberar memoria
        imagedestroy($sourceImage);
        imagedestroy($destImage);

        return $result;
    }

    /**
     * Obtiene metadatos de la imagen
     *
     * @param string $path Ruta de la imagen
     * @return array
     */
    protected function getImageMetadata(string $path): array
    {
        $info = @getimagesize($path);
        $fileSize = filesize($path);

        return [
            'width' => $info[0] ?? 0,
            'height' => $info[1] ?? 0,
            'mime_type' => $info['mime'] ?? 'unknown',
            'size' => $fileSize,
            'size_human' => $this->formatBytes($fileSize),
        ];
    }

    /**
     * Genera un nombre seguro único
     *
     * @param array $options Opciones con metadatos
     * @return string
     */
    protected function generateSafeName(array $options): string
    {
        // Hash único basado en timestamp y random
        $timestamp = time();
        $random = bin2hex(random_bytes(8));

        // Incluir user_id o tenant_id si están disponibles
        $prefix = '';
        if (isset($options['user_id'])) {
            $prefix = 'u' . $options['user_id'] . '_';
        } elseif (isset($options['tenant_id'])) {
            $prefix = 't' . $options['tenant_id'] . '_';
        }

        return $prefix . $timestamp . '_' . $random;
    }

    /**
     * Formatea bytes a formato legible
     *
     * @param int $bytes
     * @return string
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Guarda registro en base de datos (override en clases hijas si necesario)
     *
     * @param string $path Ruta relativa
     * @param array $metadata Metadatos
     * @param array $options Opciones
     * @return array|null
     */
    protected function saveToDatabase(string $path, array $metadata, array $options): ?array
    {
        // Implementación base, puede ser overrideada
        return null;
    }

    /**
     * Elimina una imagen y sus thumbnails
     *
     * @param string $path Ruta relativa
     * @return bool
     */
    public function delete(string $path): bool
    {
        $fullPath = $this->storageBase . '/' . $path;

        if (!file_exists($fullPath)) {
            return false;
        }

        // Eliminar archivo principal
        $deleted = @unlink($fullPath);

        // Eliminar thumbnails si existen
        if ($this->generateThumbnails) {
            $pathInfo = pathinfo($fullPath);
            $baseName = $pathInfo['filename'];
            $extension = $pathInfo['extension'];
            $dir = $pathInfo['dirname'];

            foreach (array_keys($this->thumbnailSizes) as $sizeName) {
                $thumbPath = $dir . '/' . $baseName . '_' . $sizeName . '.' . $extension;
                if (file_exists($thumbPath)) {
                    @unlink($thumbPath);
                }
            }
        }

        if ($deleted) {
            Logger::log("Image deleted successfully", 'INFO', [
                'driver' => $this->driverType,
                'path' => $path,
            ]);
        }

        return $deleted;
    }
}
