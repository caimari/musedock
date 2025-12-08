<?php

namespace Screenart\Musedock\Helpers;

/**
 * 🔒 SECURITY: Helper para validación segura de archivos subidos
 * Previene: File upload attacks, RCE, MIME spoofing
 */
class FileUploadValidator
{
    /**
     * Validar imagen subida
     *
     * @param array $file El archivo de $_FILES
     * @param int $maxSize Tamaño máximo en bytes (default: 10MB)
     * @return array ['valid' => bool, 'error' => string|null, 'extension' => string|null]
     */
    public static function validateImage(array $file, int $maxSize = 10485760): array
    {
        // Validar errores de upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'Error al subir el archivo. Código: ' . $file['error']];
        }

        // Validar tamaño
        if ($file['size'] > $maxSize) {
            $maxMB = round($maxSize / 1024 / 1024, 1);
            return ['valid' => false, 'error' => "La imagen no puede superar {$maxMB}MB."];
        }

        // Validar extensión
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

        if (!in_array($extension, $allowedExtensions, true)) {
            return ['valid' => false, 'error' => 'Formato de imagen no permitido. Solo JPG, PNG, GIF, WEBP, SVG.'];
        }

        // Validar MIME type real (previene MIME spoofing)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml'
        ];

        if (!in_array($mimeType, $allowedMimes, true)) {
            return ['valid' => false, 'error' => 'Tipo de archivo no permitido.'];
        }

        // Si es SVG, validación adicional (no puede contener scripts)
        if ($extension === 'svg') {
            $content = file_get_contents($file['tmp_name']);
            if (preg_match('/<script/i', $content) || preg_match('/on\w+\s*=/i', $content)) {
                return ['valid' => false, 'error' => 'El archivo SVG contiene código no permitido.'];
            }
        }

        return ['valid' => true, 'error' => null, 'extension' => $extension];
    }

    /**
     * Validar archivo ZIP
     *
     * @param array $file El archivo de $_FILES
     * @param int $maxSize Tamaño máximo en bytes (default: 20MB)
     * @param bool $validateContent Validar contenido del ZIP (default: true)
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateZip(array $file, int $maxSize = 20971520, bool $validateContent = true): array
    {
        // Validar errores de upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'Error al subir el archivo. Código: ' . $file['error']];
        }

        // Validar tamaño
        if ($file['size'] > $maxSize) {
            $maxMB = round($maxSize / 1024 / 1024, 1);
            return ['valid' => false, 'error' => "El archivo no puede superar {$maxMB}MB."];
        }

        // Validar extensión
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($extension !== 'zip') {
            return ['valid' => false, 'error' => 'Solo se permiten archivos ZIP.'];
        }

        // Validar MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = ['application/zip', 'application/x-zip-compressed'];
        if (!in_array($mimeType, $allowedMimes, true)) {
            return ['valid' => false, 'error' => 'Tipo de archivo no permitido. Debe ser ZIP.'];
        }

        // 🔒 SECURITY: Validar contenido del ZIP para plugins
        if ($validateContent) {
            $contentValidation = self::validateZipContent($file['tmp_name']);
            if (!$contentValidation['valid']) {
                return $contentValidation;
            }
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * 🔒 SECURITY: Validar contenido interno de un archivo ZIP
     * Previene: Path traversal, archivos maliciosos, RCE
     *
     * @param string $zipPath Ruta al archivo ZIP
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateZipContent(string $zipPath): array
    {
        $zip = new \ZipArchive();

        if ($zip->open($zipPath) !== TRUE) {
            return ['valid' => false, 'error' => 'No se pudo abrir el archivo ZIP.'];
        }

        // Extensiones permitidas dentro del ZIP (plugins PHP)
        $allowedExtensions = ['php', 'js', 'css', 'json', 'md', 'txt', 'html', 'sql', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];

        // Extensiones peligrosas que NUNCA deben estar en un ZIP
        $dangerousExtensions = ['exe', 'sh', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'jar'];

        $dangerousFiles = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);

            // 🔒 SECURITY: Detectar path traversal en nombres de archivo
            if (strpos($filename, '..') !== false) {
                $zip->close();
                return ['valid' => false, 'error' => 'El ZIP contiene rutas peligrosas (path traversal detectado).'];
            }

            // 🔒 SECURITY: Detectar rutas absolutas
            if (strpos($filename, '/') === 0 || preg_match('/^[A-Z]:\\//', $filename)) {
                $zip->close();
                return ['valid' => false, 'error' => 'El ZIP contiene rutas absolutas no permitidas.'];
            }

            // Solo validar archivos (no directorios)
            if (substr($filename, -1) === '/') {
                continue;
            }

            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            // 🔒 SECURITY: Detectar extensiones peligrosas
            if (in_array($ext, $dangerousExtensions, true)) {
                $zip->close();
                return ['valid' => false, 'error' => "El ZIP contiene archivos peligrosos (.{$ext}) que no están permitidos."];
            }

            // Advertir sobre extensiones no permitidas (pero permitir archivos sin extensión)
            if ($ext !== '' && !in_array($ext, $allowedExtensions, true)) {
                $dangerousFiles[] = $filename;
            }
        }

        $zip->close();

        if (!empty($dangerousFiles)) {
            return [
                'valid' => false,
                'error' => 'El ZIP contiene archivos con extensiones no permitidas: ' . implode(', ', array_slice($dangerousFiles, 0, 5))
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Generar nombre de archivo seguro
     *
     * @param string $extension Extensión del archivo
     * @param string $prefix Prefijo opcional
     * @return string Nombre de archivo seguro
     */
    public static function generateSecureFilename(string $extension, string $prefix = ''): string
    {
        $randomName = bin2hex(random_bytes(16));
        return $prefix ? "{$prefix}_{$randomName}.{$extension}" : "{$randomName}.{$extension}";
    }

    /**
     * Validar archivo PDF
     *
     * @param array $file El archivo de $_FILES
     * @param int $maxSize Tamaño máximo en bytes (default: 5MB)
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validatePDF(array $file, int $maxSize = 5242880): array
    {
        // Validar errores de upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'Error al subir el archivo. Código: ' . $file['error']];
        }

        // Validar tamaño
        if ($file['size'] > $maxSize) {
            $maxMB = round($maxSize / 1024 / 1024, 1);
            return ['valid' => false, 'error' => "El archivo no puede superar {$maxMB}MB."];
        }

        // Validar extensión
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($extension !== 'pdf') {
            return ['valid' => false, 'error' => 'Solo se permiten archivos PDF.'];
        }

        // Validar MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if ($mimeType !== 'application/pdf') {
            return ['valid' => false, 'error' => 'Tipo de archivo no permitido. Debe ser PDF.'];
        }

        return ['valid' => true, 'error' => null];
    }
}
