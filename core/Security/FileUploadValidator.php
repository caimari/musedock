<?php

namespace Screenart\Musedock\Security;

use Screenart\Musedock\Logger;

/**
 * Validador de seguridad para archivos subidos
 */
class FileUploadValidator
{
    /**
     * Valida un archivo subido de forma segura
     *
     * @param array $file Archivo de $_FILES
     * @param array $options Opciones de validación
     * @return array ['valid' => bool, 'error' => string|null, 'safe_name' => string|null]
     */
    public static function validate($file, $options = [])
    {
        // Cargar configuración
        $config = require __DIR__ . '/../../config/config.php';
        $uploadConfig = $config['upload'] ?? [];

        // Opciones por defecto
        $maxSize = $options['max_size'] ?? ($uploadConfig['max_size'] ?? 10485760); // 10MB
        $allowedMimes = $options['allowed_mimes'] ?? array_merge(
            $uploadConfig['allowed_image_mimes'] ?? [],
            $uploadConfig['allowed_document_mimes'] ?? []
        );
        $allowedExtensions = $options['allowed_extensions'] ?? ($uploadConfig['allowed_extensions'] ?? []);
        $type = $options['type'] ?? 'any'; // 'image', 'document', 'any'

        // 1. Verificar que el archivo existe
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            Logger::log("Upload validation failed: File not uploaded properly", 'WARNING');
            return ['valid' => false, 'error' => 'El archivo no se subió correctamente.'];
        }

        // 2. Verificar errores de upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = self::getUploadErrorMessage($file['error']);
            Logger::log("Upload validation failed: {$error}", 'WARNING');
            return ['valid' => false, 'error' => $error];
        }

        // 3. Verificar tamaño
        if ($file['size'] > $maxSize) {
            $maxSizeMB = round($maxSize / 1048576, 2);
            Logger::log("Upload validation failed: File too large ({$file['size']} bytes, max {$maxSize})", 'WARNING');
            return ['valid' => false, 'error' => "El archivo es demasiado grande. Máximo permitido: {$maxSizeMB} MB."];
        }

        // 4. Verificar tamaño mínimo (prevenir archivos vacíos)
        if ($file['size'] < 1) {
            Logger::log("Upload validation failed: Empty file", 'WARNING');
            return ['valid' => false, 'error' => 'El archivo está vacío.'];
        }

        // 5. Verificar MIME type real (no confiar en el cliente)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($realMimeType, $allowedMimes)) {
            Logger::log("Upload validation failed: Invalid MIME type '{$realMimeType}'", 'WARNING', [
                'allowed' => $allowedMimes
            ]);
            return ['valid' => false, 'error' => 'Tipo de archivo no permitido.'];
        }

        // 6. Verificar extensión (detectar dobles extensiones)
        $originalName = $file['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // SEGURIDAD: Detectar intentos de bypass con doble extensión
        // Ejemplo: malware.php.jpg
        $nameParts = explode('.', $originalName);
        if (count($nameParts) > 2) {
            $dangerousSecondaryExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'pht', 'phar',
                                              'pl', 'py', 'jsp', 'asp', 'aspx', 'sh', 'cgi', 'exe', 'bat'];

            foreach ($nameParts as $part) {
                $partExt = strtolower($part);
                if (in_array($partExt, $dangerousSecondaryExtensions)) {
                    Logger::log("Upload validation failed: Dangerous double extension detected", 'CRITICAL', [
                        'filename' => $originalName,
                        'dangerous_ext' => $partExt
                    ]);
                    return ['valid' => false, 'error' => 'Nombre de archivo sospechoso detectado.'];
                }
            }
        }

        if (!in_array($extension, $allowedExtensions)) {
            Logger::log("Upload validation failed: Invalid extension '{$extension}'", 'WARNING', [
                'allowed' => $allowedExtensions
            ]);
            return ['valid' => false, 'error' => 'Extensión de archivo no permitida.'];
        }

        // 7. Verificar que extensión y MIME coincidan
        if (!self::mimeMatchesExtension($realMimeType, $extension)) {
            Logger::log("Upload validation failed: MIME/Extension mismatch", 'WARNING', [
                'mime' => $realMimeType,
                'extension' => $extension
            ]);
            return ['valid' => false, 'error' => 'El archivo parece estar disfrazado.'];
        }

        // 8. Validaciones específicas por tipo
        if ($type === 'image') {
            $imageValidation = self::validateImage($file['tmp_name']);
            if (!$imageValidation['valid']) {
                return $imageValidation;
            }
        }

        // 9. Buscar contenido malicioso en archivos
        if (self::containsMaliciousContent($file['tmp_name'])) {
            Logger::log("Upload validation failed: Malicious content detected", 'CRITICAL', [
                'file' => $originalName
            ]);
            return ['valid' => false, 'error' => 'El archivo contiene contenido potencialmente peligroso.'];
        }

        // 10. Generar nombre seguro
        $safeName = self::generateSafeName($originalName, $extension);

        Logger::log("Upload validation passed", 'INFO', [
            'original_name' => $originalName,
            'safe_name' => $safeName,
            'mime' => $realMimeType,
            'size' => $file['size']
        ]);

        return [
            'valid' => true,
            'error' => null,
            'safe_name' => $safeName,
            'extension' => $extension,
            'mime_type' => $realMimeType,
            'size' => $file['size']
        ];
    }

    /**
     * Valida que un archivo sea una imagen legítima
     *
     * @param string $filePath
     * @return array
     */
    private static function validateImage($filePath)
    {
        // Intentar obtener información de la imagen
        $imageInfo = @getimagesize($filePath);

        if ($imageInfo === false) {
            Logger::log("Image validation failed: Not a valid image", 'WARNING');
            return ['valid' => false, 'error' => 'El archivo no es una imagen válida.'];
        }

        // Verificar dimensiones razonables (prevenir ataques de memoria)
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $maxDimension = 10000; // 10000px

        if ($width > $maxDimension || $height > $maxDimension) {
            Logger::log("Image validation failed: Dimensions too large ({$width}x{$height})", 'WARNING');
            return ['valid' => false, 'error' => 'Las dimensiones de la imagen son demasiado grandes.'];
        }

        return ['valid' => true];
    }

    /**
     * Busca contenido potencialmente malicioso en el archivo
     *
     * @param string $filePath
     * @return bool True si se detecta contenido malicioso
     */
    private static function containsMaliciousContent($filePath)
    {
        // Leer primeros 8KB del archivo
        $handle = fopen($filePath, 'rb');
        $content = fread($handle, 8192);
        fclose($handle);

        // Buscar patrones peligrosos
        $dangerousPatterns = [
            '/<\?php/i',
            '/<script/i',
            '/eval\s*\(/i',
            '/base64_decode/i',
            '/system\s*\(/i',
            '/exec\s*\(/i',
            '/shell_exec/i',
            '/passthru/i',
            '/popen/i',
            '/proc_open/i',
            '/pcntl_exec/i',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica que el MIME type coincida con la extensión
     *
     * @param string $mimeType
     * @param string $extension
     * @return bool
     */
    private static function mimeMatchesExtension($mimeType, $extension)
    {
        $validCombinations = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp'],
            'application/pdf' => ['pdf'],
            'application/msword' => ['doc'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
            'application/vnd.ms-excel' => ['xls'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
        ];

        foreach ($validCombinations as $mime => $exts) {
            if ($mimeType === $mime && in_array($extension, $exts)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Genera un nombre de archivo seguro y único
     *
     * @param string $originalName
     * @param string $extension
     * @return string
     */
    private static function generateSafeName($originalName, $extension)
    {
        // Generar un nombre único usando hash
        $hash = bin2hex(random_bytes(16));

        // Opcionalmente, incluir parte del nombre original (sanitizado)
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        $safeBasename = preg_replace('/[^a-zA-Z0-9_-]/', '', $basename);
        $safeBasename = substr($safeBasename, 0, 20); // Limitar longitud

        if (!empty($safeBasename)) {
            return $safeBasename . '_' . $hash . '.' . $extension;
        }

        return $hash . '.' . $extension;
    }

    /**
     * Obtiene el mensaje de error de PHP para uploads
     *
     * @param int $errorCode
     * @return string
     */
    private static function getUploadErrorMessage($errorCode)
    {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por el servidor.',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo permitido.',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió solo parcialmente.',
            UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo.',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal.',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en el disco.',
            UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la subida del archivo.',
        ];

        return $errors[$errorCode] ?? 'Error desconocido al subir el archivo.';
    }
}
