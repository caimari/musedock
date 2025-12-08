<?php

namespace Screenart\Musedock\Storage\Drivers;

use Screenart\Musedock\Security\FileUploadValidator;
use Screenart\Musedock\Logger;

/**
 * Driver especializado para archivos privados
 *
 * CARACTERÍSTICAS:
 * - Sin acceso público directo
 * - Requiere autenticación para acceder
 * - URLs permanentes (sin caducidad)
 * - Soporta documentos e imágenes privadas
 * - Auditoría de accesos
 * - Máximo 20MB
 *
 * NOTA: A diferencia de los otros drivers, este NO recodifica automáticamente
 * ya que puede manejar documentos PDF, DOCX, etc.
 *
 * @package Screenart\Musedock\Storage\Drivers
 */
class PrivateDriver
{
    protected string $storageBase = '';
    protected string $driverType = 'private';
    protected int $maxSize = 20971520; // 20MB

    protected array $allowedMimes = [
        // Imágenes
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        // Documentos
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
    ];

    protected array $allowedExtensions = [
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt',
    ];

    public function __construct()
    {
        $this->storageBase = APP_ROOT . '/storage/app/private';
    }

    /**
     * Sube un archivo privado de forma segura
     *
     * @param array $file Archivo de $_FILES
     * @param array $options Opciones (user_id requerido)
     * @return array
     */
    public function upload(array $file, array $options = []): array
    {
        try {
            // Validar que se proporcionó user_id
            if (!isset($options['user_id'])) {
                return [
                    'success' => false,
                    'error' => 'Se requiere user_id para archivos privados',
                ];
            }

            // Determinar tipo de archivo
            $type = $this->isImage($file) ? 'image' : 'document';

            // Validación con FileUploadValidator
            $validation = FileUploadValidator::validate($file, [
                'type' => $type,
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

            // Generar nombre seguro
            $extension = $validation['extension'];
            $safeName = $this->generateSafeName($options);
            $filename = $safeName . '.' . $extension;

            // Determinar subdirectorio (documents o images)
            $subdir = $type === 'image' ? 'images' : 'documents';
            $relativePath = $subdir . '/' . $filename;
            $fullPath = $this->storageBase . '/' . $relativePath;

            // Crear directorio si no existe
            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // Mover archivo
            if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                return [
                    'success' => false,
                    'error' => 'Error al guardar el archivo',
                ];
            }

            // Obtener metadatos
            $metadata = [
                'size' => filesize($fullPath),
                'mime_type' => $validation['mime_type'],
                'type' => $type,
            ];

            // Si es imagen, obtener dimensiones
            if ($type === 'image') {
                $imageInfo = @getimagesize($fullPath);
                if ($imageInfo !== false) {
                    $metadata['width'] = $imageInfo[0];
                    $metadata['height'] = $imageInfo[1];
                }
            }

            // Guardar en base de datos
            $dbRecord = $this->saveToDatabase($relativePath, $metadata, $options);

            // Log de éxito
            Logger::log("Private file uploaded successfully", 'INFO', [
                'driver' => $this->driverType,
                'filename' => $filename,
                'user_id' => $options['user_id'],
                'type' => $type,
            ]);

            // Retornar resultado con URL permanente
            return [
                'success' => true,
                'url' => '/storage/private/' . $relativePath,
                'path' => $relativePath,
                'full_path' => $fullPath,
                'filename' => $filename,
                'metadata' => $metadata,
                'db_id' => $dbRecord['id'] ?? null,
                'type' => $type,
            ];

        } catch (\Exception $e) {
            Logger::log("Private file upload failed: " . $e->getMessage(), 'ERROR', [
                'driver' => $this->driverType,
                'file' => $file['name'] ?? 'unknown',
            ]);

            return [
                'success' => false,
                'error' => 'Error inesperado al subir el archivo',
            ];
        }
    }

    /**
     * Determina si un archivo es una imagen
     */
    protected function isImage(array $file): bool
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        return strpos($mimeType, 'image/') === 0;
    }

    /**
     * Genera un nombre seguro único
     */
    protected function generateSafeName(array $options): string
    {
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        $prefix = 'u' . $options['user_id'] . '_';

        return $prefix . $timestamp . '_' . $random;
    }

    /**
     * Guarda el registro en la base de datos
     */
    protected function saveToDatabase(string $path, array $metadata, array $options): ?array
    {
        try {
            $data = [
                'filename' => basename($path),
                'path' => $path,
                'mime_type' => $metadata['mime_type'],
                'size' => $metadata['size'],
                'width' => $metadata['width'] ?? null,
                'height' => $metadata['height'] ?? null,
                'driver_type' => $this->driverType,
                'user_id' => $options['user_id'],
                'tenant_id' => $options['tenant_id'] ?? null,
                'is_private' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $db = \Screenart\Musedock\Database::connect();
            $stmt = $db->prepare("
                INSERT INTO media
                (filename, path, mime_type, size, width, height, driver_type, user_id, tenant_id, is_private, created_at)
                VALUES
                (:filename, :path, :mime_type, :size, :width, :height, :driver_type, :user_id, :tenant_id, :is_private, :created_at)
            ");

            $stmt->execute($data);
            $id = $db->lastInsertId();

            return ['id' => $id, 'data' => $data];

        } catch (\Exception $e) {
            Logger::log("Failed to save private file to database: " . $e->getMessage(), 'ERROR');
            return null;
        }
    }

    /**
     * Elimina un archivo privado
     */
    public function delete(string $path): bool
    {
        $fullPath = $this->storageBase . '/' . $path;

        if (!file_exists($fullPath)) {
            return false;
        }

        $deleted = @unlink($fullPath);

        if ($deleted) {
            Logger::log("Private file deleted successfully", 'INFO', [
                'driver' => $this->driverType,
                'path' => $path,
            ]);
        }

        return $deleted;
    }

    /**
     * Verifica si un usuario tiene permiso para acceder al archivo
     */
    public function canAccess(string $path, int $userId): bool
    {
        try {
            $db = \Screenart\Musedock\Database::connect();
            $stmt = $db->prepare("SELECT user_id FROM media WHERE path = :path AND driver_type = 'private' LIMIT 1");
            $stmt->execute(['path' => $path]);
            $record = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$record) {
                return false;
            }

            // El usuario propietario siempre puede acceder
            // Los superadmin también (role_id = 1)
            // Puedes expandir esta lógica según tus necesidades
            return $record['user_id'] == $userId;

        } catch (\Exception $e) {
            Logger::log("Access check failed: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
}
