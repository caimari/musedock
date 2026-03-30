<?php

namespace Screenart\Musedock\Storage\Drivers;

use Screenart\Musedock\Storage\ImageDriver;

/**
 * Driver especializado para galerías de imágenes
 *
 * CARACTERÍSTICAS:
 * - Múltiples resoluciones (thumbnail, medium, large, full)
 * - Lazy loading friendly
 * - Soporte para álbumes
 * - Máximo 10MB
 * - Optimización balanceada (calidad vs tamaño)
 *
 * @package Screenart\Musedock\Storage\Drivers
 */
class GalleryDriver extends ImageDriver
{
    /**
     * Configuración del driver de galería
     */
    protected function configure(): void
    {
        $this->driverType = 'gallery';
        $this->maxSize = 10485760; // 10MB
        $this->allowedMimes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ];
        $this->allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $this->shouldReencode = true;
        $this->generateThumbnails = true;
        $this->thumbnailSizes = [
            'thumbnail' => ['width' => 300, 'height' => 300],
            'medium' => ['width' => 800, 'height' => 600],
            'large' => ['width' => 1200, 'height' => 900],
        ];
    }

    /**
     * Validaciones adicionales para galería
     */
    protected function additionalValidation(array $file): array
    {
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return ['valid' => false, 'error' => 'No es una imagen válida'];
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];

        // Dimensiones mínimas
        if ($width < 300 || $height < 300) {
            return ['valid' => false, 'error' => 'Imagen muy pequeña. Mínimo 300x300 píxeles'];
        }

        // Dimensiones máximas
        if ($width > 8000 || $height > 8000) {
            return ['valid' => false, 'error' => 'Imagen muy grande. Máximo 8000x8000 píxeles'];
        }

        return ['valid' => true];
    }

    /**
     * Override: Guarda en base de datos con información de álbum
     */
    protected function saveToDatabase(string $path, array $metadata, array $options): ?array
    {
        try {
            $data = [
                'filename' => basename($path),
                'path' => $path,
                'mime_type' => $metadata['mime_type'],
                'size' => $metadata['size'],
                'width' => $metadata['width'],
                'height' => $metadata['height'],
                'driver_type' => $this->driverType,
                'user_id' => $options['user_id'] ?? null,
                'tenant_id' => $options['tenant_id'] ?? null,
                'album_id' => $options['album_id'] ?? null,
                'alt_text' => $options['alt_text'] ?? '',
                'caption' => $options['caption'] ?? '',
                'created_at' => date('Y-m-d H:i:s'),
            ];

            // Insertar en la tabla media
            $db = \Screenart\Musedock\Database::connect();
            $stmt = $db->prepare("
                INSERT INTO media
                (filename, path, mime_type, size, width, height, driver_type, user_id, tenant_id, album_id, alt_text, caption, created_at)
                VALUES
                (:filename, :path, :mime_type, :size, :width, :height, :driver_type, :user_id, :tenant_id, :album_id, :alt_text, :caption, :created_at)
            ");

            $stmt->execute($data);
            $id = $db->lastInsertId();

            return ['id' => $id, 'data' => $data];

        } catch (\Exception $e) {
            \Screenart\Musedock\Logger::log("Failed to save gallery image to database: " . $e->getMessage(), 'ERROR');
            return null;
        }
    }
}
