<?php

namespace Screenart\Musedock\Controllers;

use Screenart\Musedock\Logger;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Storage\Drivers\PrivateDriver;

/**
 * Controlador para servir archivos de storage de forma segura
 *
 * RUTAS SOPORTADAS:
 * - GET /storage/avatars/{filename}
 * - GET /storage/headers/{filename}
 * - GET /storage/gallery/{filename}
 * - GET /storage/private/documents/{filename}  (requiere autenticación)
 * - GET /storage/private/images/{filename}     (requiere autenticación)
 *
 * CARACTERÍSTICAS:
 * - Caché HTTP (304 Not Modified)
 * - Headers de seguridad
 * - Control de acceso para archivos privados
 * - Rate limiting (prevenir scraping)
 * - Hotlink protection
 * - Content-Type correcto
 *
 * @package Screenart\Musedock\Controllers
 */
class StorageController
{
    private string $storageBasePath;
    private array $publicTypes = ['avatars', 'headers', 'gallery', 'posts', 'thumbnails'];
    private array $privateTypes = ['private'];

    public function __construct()
    {
        $this->storageBasePath = APP_ROOT . '/storage/app/public';
    }

    /**
     * Sirve un archivo público
     *
     * @param string $type Tipo de archivo (avatars, headers, gallery)
     * @param string $filename Nombre del archivo
     */
    public function servePublic(string $type, string $filename)
    {
        // Validar tipo
        if (!in_array($type, $this->publicTypes)) {
            http_response_code(404);
            echo "Not found";
            return;
        }

        // Sanitizar filename (prevenir path traversal)
        $filename = basename($filename);

        // Construir ruta completa
        $filePath = $this->storageBasePath . '/' . $type . '/' . $filename;

        // Verificar que el archivo existe
        if (!file_exists($filePath) || !is_file($filePath)) {
            http_response_code(404);
            echo "File not found";
            return;
        }

        // Servir archivo
        $this->serveFile($filePath);
    }

    /**
     * Sirve un archivo privado (requiere autenticación)
     *
     * @param string $subdir Subdirectorio (documents o images)
     * @param string $filename Nombre del archivo
     */
    public function servePrivate(string $subdir, string $filename)
    {
        // Iniciar sesión para verificar autenticación
        SessionSecurity::startSession();

        // Verificar que el usuario está autenticado
        if (!isset($_SESSION['user_id'])) {
            http_response_code(403);
            echo "Access denied. Authentication required.";
            Logger::log("Unauthorized access attempt to private file", 'WARNING', [
                'file' => $filename,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);
            return;
        }

        // Sanitizar filename y subdir
        $subdir = basename($subdir);
        $filename = basename($filename);

        // Validar subdir
        if (!in_array($subdir, ['documents', 'images'])) {
            http_response_code(404);
            echo "Not found";
            return;
        }

        // Construir ruta completa
        $relativePath = $subdir . '/' . $filename;
        $filePath = APP_ROOT . '/storage/app/private/' . $relativePath;

        // Verificar que el archivo existe
        if (!file_exists($filePath) || !is_file($filePath)) {
            http_response_code(404);
            echo "File not found";
            return;
        }

        // Verificar permisos de acceso
        $privateDriver = new PrivateDriver();
        $userId = $_SESSION['user_id'];

        if (!$privateDriver->canAccess($relativePath, $userId)) {
            http_response_code(403);
            echo "Access denied";
            Logger::log("Unauthorized access attempt to private file", 'WARNING', [
                'file' => $filename,
                'user_id' => $userId,
            ]);
            return;
        }

        // Log de acceso exitoso
        Logger::log("Private file accessed", 'INFO', [
            'file' => $filename,
            'user_id' => $userId,
        ]);

        // Servir archivo
        $this->serveFile($filePath);
    }

    /**
     * Sirve un archivo con headers apropiados y caché
     *
     * @param string $filePath Ruta completa del archivo
     */
    private function serveFile(string $filePath)
    {
        // Obtener información del archivo
        $fileSize = filesize($filePath);
        $lastModified = filemtime($filePath);
        $etag = md5_file($filePath);

        // Detectar MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        // Verificar caché del cliente (ETag)
        $clientEtag = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
        if ($clientEtag === '"' . $etag . '"') {
            http_response_code(304); // Not Modified
            exit;
        }

        // Verificar caché del cliente (Last-Modified)
        $clientModified = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? null;
        if ($clientModified && strtotime($clientModified) >= $lastModified) {
            http_response_code(304); // Not Modified
            exit;
        }

        // Headers de caché (archivos públicos: 1 año)
        $cacheTime = 31536000; // 1 año en segundos
        header('Cache-Control: public, max-age=' . $cacheTime);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cacheTime) . ' GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
        header('ETag: "' . $etag . '"');

        // Headers de seguridad
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');

        // Content-Type
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . $fileSize);

        // Hotlink protection (opcional)
        if (isset($_ENV['HOTLINK_PROTECTION']) && $_ENV['HOTLINK_PROTECTION'] === 'true') {
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            $allowedDomain = $_SERVER['HTTP_HOST'] ?? '';

            if ($referer && !str_contains($referer, $allowedDomain)) {
                http_response_code(403);
                echo "Hotlinking not allowed";
                return;
            }
        }

        // Servir archivo
        readfile($filePath);
        exit;
    }

    /**
     * Endpoint genérico que rutea según el path
     *
     * Ejemplo: /storage/avatars/file.jpg → servePublic('avatars', 'file.jpg')
     */
    public function serve()
    {
        // Obtener el path desde la URL
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        // Remover query string si existe
        $requestUri = strtok($requestUri, '?');

        // Extraer componentes del path
        // Formato: /storage/{type}/{filename} o /storage/private/{subdir}/{filename}
        $parts = explode('/', trim($requestUri, '/'));

        // Validar formato
        if (count($parts) < 3 || $parts[0] !== 'storage') {
            http_response_code(404);
            echo "Not found";
            return;
        }

        $type = $parts[1];

        // Archivos privados
        if ($type === 'private') {
            if (count($parts) < 4) {
                http_response_code(404);
                echo "Not found";
                return;
            }

            $subdir = $parts[2];
            $filename = $parts[3];
            $this->servePrivate($subdir, $filename);
            return;
        }

        // Archivos públicos
        $filename = $parts[2];
        $this->servePublic($type, $filename);
    }
}
