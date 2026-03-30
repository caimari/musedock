<?php

namespace Screenart\Musedock\Storage\Drivers;

use Screenart\Musedock\Storage\ImageDriver;

/**
 * Driver especializado para imágenes de cabecera/header
 *
 * CARACTERÍSTICAS:
 * - Dimensiones específicas (1920x400 recomendado)
 * - Solo JPEG, PNG, WebP
 * - Máximo 5MB
 * - Optimización agresiva
 * - Generación automática de versiones responsive
 *
 * @package Screenart\Musedock\Storage\Drivers
 */
class HeaderDriver extends ImageDriver
{
    /**
     * Configuración del driver de headers
     */
    protected function configure(): void
    {
        $this->driverType = 'headers';
        $this->maxSize = 5242880; // 5MB
        $this->allowedMimes = [
            'image/jpeg',
            'image/png',
            'image/webp',
        ];
        $this->allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $this->shouldReencode = true;
        $this->generateThumbnails = true;
        $this->thumbnailSizes = [
            'mobile' => ['width' => 768, 'height' => 300],
            'tablet' => ['width' => 1024, 'height' => 350],
            'desktop' => ['width' => 1920, 'height' => 400],
        ];
    }

    /**
     * Validaciones adicionales para headers
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
        if ($width < 768 || $height < 200) {
            return ['valid' => false, 'error' => 'Imagen muy pequeña. Mínimo 768x200 píxeles'];
        }

        // Dimensiones máximas
        if ($width > 5000 || $height > 2000) {
            return ['valid' => false, 'error' => 'Imagen muy grande. Máximo 5000x2000 píxeles'];
        }

        // Relación de aspecto (ancho > alto)
        if ($height > $width) {
            return ['valid' => false, 'error' => 'La imagen debe ser horizontal (ancho > alto)'];
        }

        return ['valid' => true];
    }

    /**
     * Override: Optimización agresiva para headers
     */
    protected function reencodeImage(string $sourcePath, string $destPath, string $extension)
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
            IMAGETYPE_WEBP => @imagecreatefromwebp($sourcePath),
            default => false,
        };

        if ($sourceImage === false) {
            return false;
        }

        // Redimensionar si excede tamaño máximo recomendado
        $maxWidth = 1920;
        $maxHeight = 500;

        if ($origWidth > $maxWidth || $origHeight > $maxHeight) {
            $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
            $newWidth = (int)($origWidth * $ratio);
            $newHeight = (int)($origHeight * $ratio);

            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

            // Preservar transparencia para PNG
            if ($type === IMAGETYPE_PNG) {
                imagealphablending($resizedImage, false);
                imagesavealpha($resizedImage, true);
                $transparent = imagecolorallocatealpha($resizedImage, 0, 0, 0, 127);
                imagefill($resizedImage, 0, 0, $transparent);
            }

            imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
            imagedestroy($sourceImage);
            $sourceImage = $resizedImage;
        }

        // Guardar con optimización agresiva
        $result = match($extension) {
            'jpg', 'jpeg' => imagejpeg($sourceImage, $destPath, 85), // Calidad 85
            'png' => imagepng($sourceImage, $destPath, 8), // Compresión 8
            'webp' => imagewebp($sourceImage, $destPath, 85),
            default => false,
        };

        imagedestroy($sourceImage);

        return $result ? $destPath : false;
    }
}
