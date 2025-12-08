<?php

namespace MediaManager\Controllers;

use MediaManager\Models\Media;
use Screenart\Musedock\Logger;

/**
 * Controlador para servir archivos de media de forma segura
 *
 * Los archivos se almacenan fuera de /public/ en /storage/app/media/
 * y se sirven a través de este controlador con URLs permanentes.
 *
 * Ventajas:
 * - Los archivos no son accesibles directamente por URL
 * - Control total sobre quién puede acceder (aunque para contenido público se permite a todos)
 * - URLs limpias y permanentes: /media/file/{path}
 * - Headers de caché apropiados para rendimiento
 * - Protección contra path traversal
 */
class MediaServeController
{
    /**
     * Sirve un archivo de media por su path
     * URL: /media/file/{path} o /media/file/{path}?token={token}
     *
     * @param string $path Path relativo del archivo (ej: 2024/12/imagen.jpg)
     */
    public function serve($path = '')
    {
        try {
            // Sanitizar path para prevenir path traversal
            $path = $this->sanitizePath($path);

            if (empty($path)) {
                return $this->notFound('Archivo no especificado');
            }

            // Si hay un token en la query string, validar contra gallery_images
            if (isset($_GET['token']) && !empty($_GET['token'])) {
                $token = $_GET['token'];

                // El token puede tener sufijos como -thumb o -medium
                $baseToken = $token;
                $suffix = '';
                if (strpos($token, '-thumb') !== false) {
                    $baseToken = str_replace('-thumb', '', $token);
                    $suffix = '-thumb';
                } elseif (strpos($token, '-medium') !== false) {
                    $baseToken = str_replace('-medium', '', $token);
                    $suffix = '-medium';
                }

                // Buscar la imagen en gallery_images
                $db = \Screenart\Musedock\Database::connect();
                $stmt = $db->prepare("SELECT * FROM gallery_images WHERE public_token = ?");
                $stmt->execute([$baseToken]);
                $galleryImage = $stmt->fetch(\PDO::FETCH_ASSOC);

                if (!$galleryImage) {
                    Logger::warning('MediaServe: Token de galería inválido', [
                        'token' => $baseToken,
                        'path' => $path
                    ]);
                    return $this->notFound('Token inválido');
                }

                // El path debe coincidir con alguna de las URLs de la imagen
                // Extraer el filename del path (sin el directorio gallery-X/)
                $pathParts = explode('/', $path);
                $filename = end($pathParts);

                // Verificar que el filename esté en alguna de las URLs de la imagen
                $imageUrl = $galleryImage['image_url'] ?? '';
                $thumbnailUrl = $galleryImage['thumbnail_url'] ?? '';
                $mediumUrl = $galleryImage['medium_url'] ?? '';

                $validFile = false;
                if (strpos($imageUrl, $filename) !== false && $suffix === '') {
                    $validFile = true;
                } elseif (strpos($thumbnailUrl, $filename) !== false && $suffix === '-thumb') {
                    $validFile = true;
                } elseif (strpos($mediumUrl, $filename) !== false && $suffix === '-medium') {
                    $validFile = true;
                }

                if (!$validFile) {
                    Logger::warning('MediaServe: Path no coincide con token de galería', [
                        'token' => $baseToken,
                        'path' => $path,
                        'filename' => $filename
                    ]);
                    return $this->notFound('Acceso no autorizado');
                }
            }

            // Construir ruta completa al archivo
            $mediaRoot = APP_ROOT . '/storage/app/media';
            $fullPath = $mediaRoot . '/' . $path;

            // Verificar que el archivo existe y está dentro del directorio permitido
            $realPath = realpath($fullPath);
            $realMediaRoot = realpath($mediaRoot);

            if (!$realPath || strpos($realPath, $realMediaRoot) !== 0) {
                Logger::warning('MediaServe: Intento de acceso a archivo fuera del directorio', [
                    'requested_path' => $path,
                    'full_path' => $fullPath,
                    'real_path' => $realPath ? $realPath : 'null',
                    'real_media_root' => $realMediaRoot ? $realMediaRoot : 'null'
                ]);
                return $this->notFound('Archivo no encontrado');
            }

            if (!file_exists($realPath) || !is_file($realPath)) {
                return $this->notFound('Archivo no encontrado');
            }

            // Obtener información del archivo
            $mimeType = $this->getMimeType($realPath);
            $fileSize = filesize($realPath);
            $lastModified = filemtime($realPath);
            $etag = md5($realPath . $lastModified);

            // Verificar caché del navegador (304 Not Modified)
            if ($this->checkBrowserCache($etag, $lastModified)) {
                http_response_code(304);
                exit;
            }

            // Headers de respuesta
            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . $fileSize);
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
            header('ETag: "' . $etag . '"');

            // Cache headers - archivos públicos, cachear por 1 año
            header('Cache-Control: public, max-age=31536000, immutable');
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');

            // Headers de seguridad
            header('X-Content-Type-Options: nosniff');

            // Para imágenes, permitir inline; para otros, forzar descarga
            $isImage = strpos($mimeType, 'image/') === 0;
            $filename = basename($realPath);

            if ($isImage) {
                header('Content-Disposition: inline; filename="' . $filename . '"');
            } else {
                header('Content-Disposition: attachment; filename="' . $filename . '"');
            }

            // Servir el archivo
            readfile($realPath);
            exit;

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'MediaServeController']);
            return $this->notFound('Error al servir archivo');
        }
    }

    /**
     * Sirve un archivo de media por su token público (MÉTODO SEGURO RECOMENDADO)
     * URL: /media/t/{token}
     *
     * Este es el método principal y seguro para servir archivos.
     * El token es un identificador único de 16 caracteres que no puede ser enumerado.
     *
     * @param string $token Token público único del archivo
     */
    public function serveByToken($token)
    {
        try {
            // Validar formato del token (16 caracteres alfanuméricos)
            if (empty($token) || !preg_match('/^[a-zA-Z0-9]{8,32}$/', $token)) {
                return $this->notFound('Token inválido');
            }

            $media = Media::findByToken($token);

            if (!$media) {
                return $this->notFound('Archivo no encontrado');
            }

            // Si el archivo está en el disco 'media' (nuevo sistema seguro)
            if ($media->disk === 'media') {
                return $this->serve($media->path);
            }

            // Si está en el disco 'local' (sistema legacy en /public/)
            // Redirigir a la URL pública directa
            $publicUrl = '/assets/uploads/' . $media->path;
            header('Location: ' . $publicUrl, true, 301);
            exit;

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'MediaServeController::serveByToken']);
            return $this->notFound('Error al servir archivo');
        }
    }

    /**
     * Sirve un archivo de media por su ID en la base de datos
     * URL: /media/id/{id}
     *
     * @deprecated Usar serveByToken() en su lugar. Este método expone IDs secuenciales.
     *
     * @param int $id ID del media en la base de datos
     */
    public function serveById($id)
    {
        try {
            $media = Media::find((int)$id);

            if (!$media) {
                return $this->notFound('Archivo no encontrado');
            }

            // Si el archivo está en el disco 'media' (nuevo sistema)
            if ($media->disk === 'media') {
                return $this->serve($media->path);
            }

            // Si está en el disco 'local' (sistema antiguo en /public/)
            // Redirigir a la URL pública directa
            $publicUrl = '/assets/uploads/' . $media->path;
            header('Location: ' . $publicUrl, true, 301);
            exit;

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'MediaServeController::serveById']);
            return $this->notFound('Error al servir archivo');
        }
    }

    /**
     * Sirve un thumbnail/miniatura del archivo
     * URL: /media/thumb/{path}
     *
     * @param string $path Path relativo del archivo
     */
    public function serveThumbnail($path = '')
    {
        // Por ahora, servir el archivo original
        // TODO: Implementar generación de thumbnails on-the-fly
        return $this->serve($path);
    }

    /**
     * Sanitiza el path para prevenir path traversal attacks
     */
    private function sanitizePath(string $path): string
    {
        // Decodificar URL
        $path = urldecode($path);

        // Remover caracteres peligrosos
        $path = str_replace(['..', "\0", "\n", "\r"], '', $path);

        // Normalizar separadores
        $path = str_replace('\\', '/', $path);

        // Remover slashes múltiples
        $path = preg_replace('#/+#', '/', $path);

        // Remover slash inicial
        $path = ltrim($path, '/');

        return $path;
    }

    /**
     * Obtiene el MIME type del archivo
     */
    private function getMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $mimeTypes = [
            // Imágenes
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'bmp' => 'image/bmp',

            // Documentos
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',

            // Video
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'ogg' => 'video/ogg',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',

            // Audio
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'flac' => 'audio/flac',

            // Otros
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'txt' => 'text/plain',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
        ];

        if (isset($mimeTypes[$extension])) {
            return $mimeTypes[$extension];
        }

        // Fallback: usar finfo si está disponible
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $path);
            finfo_close($finfo);
            return $mime ?: 'application/octet-stream';
        }

        return 'application/octet-stream';
    }

    /**
     * Verifica si el navegador tiene el archivo en caché
     */
    private function checkBrowserCache(string $etag, int $lastModified): bool
    {
        // Verificar If-None-Match (ETag)
        if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
            $clientEtag = trim($_SERVER['HTTP_IF_NONE_MATCH'], '"');
            if ($clientEtag === $etag) {
                return true;
            }
        }

        // Verificar If-Modified-Since
        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $clientTime = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
            if ($clientTime >= $lastModified) {
                return true;
            }
        }

        return false;
    }

    /**
     * Responde con 404 Not Found
     */
    private function notFound(string $message = 'Not Found')
    {
        http_response_code(404);
        header('Content-Type: text/plain');
        echo $message;
        exit;
    }
}
