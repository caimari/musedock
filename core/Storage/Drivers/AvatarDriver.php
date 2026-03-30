<?php

namespace Screenart\Musedock\Storage\Drivers;

use Screenart\Musedock\Storage\ImageDriver;

/**
 * Driver especializado para imágenes de avatar
 *
 * CARACTERÍSTICAS:
 * - Forzado de dimensiones cuadradas (200x200, 400x400)
 * - Recorte automático al centro
 * - Solo JPEG, PNG, WebP
 * - Máximo 2MB
 * - Limpieza total de metadatos EXIF (privacidad)
 * - Generación automática de thumbnails
 *
 * @package Screenart\Musedock\Storage\Drivers
 */
class AvatarDriver extends ImageDriver
{
    /**
     * Configuración del driver de avatares
     */
    protected function configure(): void
    {
        $this->driverType = 'avatars';
        $this->maxSize = 2097152; // 2MB
        $this->allowedMimes = [
            'image/jpeg',
            'image/png',
            'image/webp',
        ];
        $this->allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $this->shouldReencode = true; // CRÍTICO: Elimina exploits EXIF
        $this->generateThumbnails = true;
        $this->thumbnailSizes = [
            'small' => ['width' => 50, 'height' => 50],
            'medium' => ['width' => 150, 'height' => 150],
            'large' => ['width' => 400, 'height' => 400],
        ];
    }

    /**
     * Validaciones adicionales para avatares
     */
    protected function additionalValidation(array $file): array
    {
        // Verificar dimensiones mínimas
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return ['valid' => false, 'error' => 'No es una imagen válida'];
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];

        // Mínimo 50x50 píxeles
        if ($width < 50 || $height < 50) {
            return ['valid' => false, 'error' => 'La imagen es demasiado pequeña. Mínimo 50x50 píxeles'];
        }

        // Máximo 4000x4000 píxeles
        if ($width > 4000 || $height > 4000) {
            return ['valid' => false, 'error' => 'La imagen es demasiado grande. Máximo 4000x4000 píxeles'];
        }

        return ['valid' => true];
    }

    /**
     * Override: Recodifica y recorta al centro en formato cuadrado
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

        // Recortar al centro en formato cuadrado 400x400
        $targetSize = 400;
        $size = min($origWidth, $origHeight);

        // Calcular posición central
        $x = ($origWidth - $size) / 2;
        $y = ($origHeight - $size) / 2;

        // Crear imagen cuadrada
        $croppedImage = imagecreatetruecolor($targetSize, $targetSize);

        // Preservar transparencia para PNG
        if ($type === IMAGETYPE_PNG) {
            imagealphablending($croppedImage, false);
            imagesavealpha($croppedImage, true);
            $transparent = imagecolorallocatealpha($croppedImage, 0, 0, 0, 127);
            imagefill($croppedImage, 0, 0, $transparent);
        }

        // Recortar y redimensionar
        imagecopyresampled($croppedImage, $sourceImage, 0, 0, $x, $y, $targetSize, $targetSize, $size, $size);

        // Guardar imagen procesada
        $result = match($extension) {
            'jpg', 'jpeg' => imagejpeg($croppedImage, $destPath, 92),
            'png' => imagepng($croppedImage, $destPath, 8),
            'webp' => imagewebp($croppedImage, $destPath, 92),
            default => false,
        };

        // Liberar memoria
        imagedestroy($sourceImage);
        imagedestroy($croppedImage);

        return $result ? $destPath : false;
    }
}
