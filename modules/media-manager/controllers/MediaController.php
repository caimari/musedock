<?php

namespace MediaManager\Controllers;

use Screenart\Musedock\View;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Logger;
use MediaManager\Models\Media;
use Screenart\Musedock\Database;

if (!function_exists('slugify')) {
    function slugify($text)
    {
        // Reemplaza caracteres no alfanuméricos por guiones
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        // Transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        // Quita caracteres no deseados
        $text = preg_replace('~[^-\w]+~', '', $text);
        // Quita guiones al inicio y final
        $text = trim($text, '-');
        // Evita múltiples guiones
        $text = preg_replace('~-+~', '-', $text);
        // Minúsculas
        return strtolower($text);
    }
}

class MediaController
{
    public function index()
    {
        SessionSecurity::startSession();

        // Obtener discos disponibles para el selector
        $availableDisks = $this->getAvailableDisks();

        // Usar el sistema de vistas estándar con namespace de módulo
        // media-manager.admin.index se resuelve automáticamente a MediaManager::superadmin/media-manager/admin/index
        return View::renderSuperadmin('media-manager.admin.index', [
            'title' => 'Biblioteca de Medios',
            'availableDisks' => $availableDisks,
            'defaultDisk' => 'media'
        ]);
    }

    /**
     * Obtiene los discos disponibles para el Media Manager
     * Solo muestra discos que están configurados y tienen credenciales válidas
     */
    private function getAvailableDisks(): array
    {
        $disks = [];
        $filesystemsConfig = config('filesystems.disks', []);

        // Disco 'media' (local seguro) - siempre disponible
        if (isset($filesystemsConfig['media'])) {
            $disks['media'] = [
                'name' => 'Local (Seguro)',
                'icon' => 'bi-hdd',
                'description' => 'Almacenamiento local seguro'
            ];
        }

        // Disco 'local' (legacy) - siempre disponible para ver archivos antiguos
        if (isset($filesystemsConfig['local'])) {
            $disks['local'] = [
                'name' => 'Local (Legacy)',
                'icon' => 'bi-folder',
                'description' => 'Archivos públicos antiguos'
            ];
        }

        // Disco R2 (Cloudflare) - solo si está configurado
        if (isset($filesystemsConfig['r2'])) {
            $r2Config = $filesystemsConfig['r2'];
            if (!empty($r2Config['key']) && !empty($r2Config['secret']) && !empty($r2Config['bucket'])) {
                $disks['r2'] = [
                    'name' => 'Cloudflare R2 (CDN)',
                    'icon' => 'bi-cloud',
                    'description' => 'CDN global con Cloudflare'
                ];
            }
        }

        // Disco S3 (Amazon) - solo si está configurado
        if (isset($filesystemsConfig['s3'])) {
            $s3Config = $filesystemsConfig['s3'];
            if (!empty($s3Config['key']) && !empty($s3Config['secret']) && !empty($s3Config['bucket'])) {
                $disks['s3'] = [
                    'name' => 'Amazon S3',
                    'icon' => 'bi-cloud-arrow-up',
                    'description' => 'Almacenamiento en Amazon S3'
                ];
            }
        }

        return $disks;
    }

    public function getMediaData()
    {
        SessionSecurity::startSession();

        // Obtener parámetros con seguridad
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 30;
        $search = isset($_GET['search']) ? $_GET['search'] : null;
        $typeFilter = isset($_GET['type']) ? $_GET['type'] : null;
        $tenantFilter = isset($_GET['tenant_id']) ? $_GET['tenant_id'] : 'all';
        $folderId = isset($_GET['folder_id']) && $_GET['folder_id'] !== '' ? (int)$_GET['folder_id'] : null;
        $diskFilter = isset($_GET['disk']) ? $_GET['disk'] : null; // Nuevo: filtro por disco

        try {
            // Construir consulta
            $query = Media::query()->orderBy('created_at', 'DESC');

            // Filtrar por disco (si se especifica)
            if ($diskFilter && in_array($diskFilter, ['local', 'media', 'r2', 's3'])) {
                $query->where('disk', $diskFilter);
            }

            // Filtrar por carpeta
            // folder_id = 1 es la carpeta Root, los archivos sin carpeta (NULL) también son de Root
            if ($folderId === null || $folderId === 1) {
                // Raíz: mostrar archivos con folder_id = NULL o folder_id = 1
                $query->whereRaw("(folder_id IS NULL OR folder_id = 1)");
            } else {
                // Carpeta específica
                $query->where('folder_id', $folderId);
            }

            if ($search) {
                // QueryBuilder no soporta closures - usar whereRaw con bindings seguros
                $searchTerm = "%{$search}%";
                $query->whereRaw("(filename LIKE ? OR alt_text LIKE ? OR caption LIKE ?)",
                    [$searchTerm, $searchTerm, $searchTerm]);
            }

            if ($typeFilter === 'image') {
                $query->where('mime_type', 'LIKE', 'image/%');
            } elseif ($typeFilter === 'document') {
                $query->whereIn('mime_type', ['application/pdf', 'application/msword']);
            }

            if ($tenantFilter === 'global') {
                $query->whereNull('tenant_id');
            } elseif (is_numeric($tenantFilter)) {
                $query->where('tenant_id', (int)$tenantFilter);
            }

            $pagination = $query->paginate($perPage, $page);

            $mediaItems = [];

            $items = isset($pagination['items']) ? $pagination['items'] : [];

            foreach ($items as $media) {
                // Convertir a modelo si no es instancia (precaución por resultados raw)
                if (!$media instanceof Media) {
                    $media = new Media((array)$media);
                }

                // Construir ruta pública
                $url = $media->getPublicUrl();
                $thumbnailUrl = $url; // Puedes personalizar si quieres miniaturas

                // Obtener dimensiones de imagen si es posible
                $dimensions = '';
                if (strpos($media->mime_type, 'image/') === 0) {
                    $filePath = $media->getFullPath();
                    if ($filePath && file_exists($filePath)) {
                        $imageInfo = @getimagesize($filePath);
                        if ($imageInfo) {
                            $dimensions = $imageInfo[0] . ' por ' . $imageInfo[1] . ' píxeles';
                        }
                    }
                }

                // Obtener usuario que subió el archivo
                $uploader = 'Usuario';
                if ($media->user_id) {
                    // Intenta obtener el nombre del usuario si tienes una tabla de usuarios
                    // Esto depende de tu estructura de base de datos
                    try {
                        $userModel = '\\App\\Models\\User'; // Ajusta según tu estructura
                        if (class_exists($userModel)) {
                            $user = $userModel::find($media->user_id);
                            if ($user) {
                                $uploader = $user->first_name . ' ' . $user->last_name;
                            }
                        }
                    } catch (\Exception $e) {
                        // Ignorar errores al buscar el usuario
                    }
                }

                // Determinar la ruta de subida
                $uploadPath = $media->tenant_id ? 'Tenant ' . $media->tenant_id : 'Global';

                $mediaItems[] = [
                    'id' => $media->id,
                    'filename' => $media->filename,
                    'url' => $url,
                    'thumbnail_url' => $thumbnailUrl,
                    'mime_type' => $media->mime_type,
                    'size' => $media->size,
                    'alt_text' => $media->alt_text,
                    'caption' => $media->caption,
                    'created_at' => $media->created_at ? $media->created_at->format('Y-m-d H:i:s') : null,
                    'upload_date' => $media->created_at ? $media->created_at->format('d \d\e F \d\e Y') : date('d \d\e F \d\e Y'),
                    'tenant_id' => $media->tenant_id,
                    'user_id' => $media->user_id,
                    'uploader' => $uploader,
                    'upload_path' => $uploadPath,
                    'dimensions' => $dimensions
                ];
            }

            // Obtener información de la carpeta actual
            $currentFolder = null;
            $folderPath = '/';
            if ($folderId) {
                $folder = \MediaManager\Models\Folder::find($folderId);
                if ($folder) {
                    $currentFolder = [
                        'id' => $folder->id,
                        'name' => $folder->name,
                        'path' => $folder->path
                    ];
                    $folderPath = $folder->path ?: '/';
                }
            }

            return $this->jsonResponse([
                'success' => true,
                'media' => $mediaItems,
                'pagination' => $pagination,
                'current_folder' => $currentFolder,
                'folder_path' => $folderPath
            ]);

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'getMediaData']);
            return $this->jsonResponse(['success' => false, 'message' => 'Error al cargar los medios.'], 500);
        }
    }

/**
 * Método para obtener los detalles completos de un archivo de medios
 * con solución específica para la fecha inválida
 */
public function getMediaDetails($id)
{
    SessionSecurity::startSession();

    try {
        $media = Media::find($id);
        if (!$media) {
            return $this->jsonResponse(['success' => false, 'message' => 'Media no encontrado.'], 404);
        }

        // Obtener ruta completa al archivo físico usando el disco correcto
        $url = $media->getPublicUrl();
        $filePath = $media->getFullPath();

        // Verificar si el archivo existe físicamente
        $fileExists = $filePath && file_exists($filePath);
        
        // Obtener dimensiones si es imagen
        $dimensions = '';
        if ($fileExists && strpos($media->mime_type, 'image/') === 0) {
            $imageInfo = @getimagesize($filePath);
            if ($imageInfo) {
                $dimensions = $imageInfo[0] . ' por ' . $imageInfo[1] . ' píxeles';
            }
        }
        
        // Verificar tamaño real del archivo
        $size = $media->size;
        if ($fileExists && ($size === 0 || $size === null)) {
            $size = filesize($filePath);
            
            // Si encontramos un tamaño real, actualizamos la base de datos
            if ($size > 0) {
                $media->size = $size;
                $media->save();
            }
        }

        // Solución para el nombre de usuario
        $uploader = 'Super Admin';
        if ($media->user_id == 1) {
            $uploader = 'Super Admin';
        }
        else if ($media->user_id) {
            $uploader = 'Usuario ' . $media->user_id;
        }

        // Determinar la ruta de subida
        $uploadPath = '';
        if (strpos($media->path, 'global/') === 0) {
            $uploadPath = 'Biblioteca Global';
        } elseif (preg_match('/tenant_(\d+)\//', $media->path, $matches)) {
            $tenantId = $matches[1];
            $uploadPath = "Tenant {$tenantId}";
        } else {
            $uploadPath = dirname($media->path);
        }

        // SOLUCIÓN ESPECÍFICA PARA LA FECHA
        // Usar una fecha en español directamente con los nombres de los meses correctos
        $meses = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
        ];
        
        $dia = date('d');
        $mes = $meses[(int)date('m')];
        $ano = date('Y');
        
        // Formatear la fecha manualmente para evitar problemas de localización
        $uploadDate = "{$dia} de {$mes} de {$ano}";
        
        // También usar una fecha estándar para created_at
        $createdAt = date('Y-m-d H:i:s');
        
        // Obtener tipo MIME más descriptivo
        $mimeType = $media->mime_type;
        if (!$mimeType || $mimeType === 'application/octet-stream') {
            // Intentar detectar el tipo por la extensión
            $extension = pathinfo($media->filename, PATHINFO_EXTENSION);
            $mimeMap = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ];
            
            $mimeType = $mimeMap[strtolower($extension)] ?? 'application/octet-stream';
        }
        
        // Descripción más amigable del tipo de archivo
        $typeDescription = $this->getFileTypeDescription($mimeType);

        return $this->jsonResponse([
            'success' => true,
            'media' => [
                'id' => $media->id,
                'filename' => $media->filename,
                'url' => $url,
                'thumbnail_url' => $url,
                'mime_type' => $mimeType,
                'type_description' => $typeDescription,
                'size' => $size,
                'alt_text' => $media->alt_text,
                'caption' => $media->caption,
                'created_at' => $createdAt,
                'upload_date' => $uploadDate,
                'tenant_id' => $media->tenant_id,
                'user_id' => $media->user_id,
                'uploader' => $uploader,
                'upload_path' => $uploadPath,
                'dimensions' => $dimensions,
                'file_exists' => $fileExists
            ]
        ]);
    } catch (\Exception $e) {
        Logger::exception($e, 'ERROR', ['source' => 'MediaDetails']);

        // 🔒 SECURITY: No exponer detalles técnicos en producción
        $message = 'Error al obtener detalles del medio.';
        if (getenv('APP_ENV') === 'development') {
            $message .= ' [DEBUG]: ' . $e->getMessage();
        }

        return $this->jsonResponse(['success' => false, 'message' => $message], 500);
    }
}
/**
 * Obtiene una descripción amigable del tipo de archivo basada en el MIME
 */
private function getFileTypeDescription($mimeType)
{
    $parts = explode('/', $mimeType);
    $type = $parts[0] ?? '';
    $subtype = $parts[1] ?? '';
    
    switch ($type) {
        case 'image':
            return 'Imagen ' . strtoupper($subtype);
        case 'video':
            return 'Vídeo ' . strtoupper($subtype);
        case 'audio':
            return 'Audio ' . strtoupper($subtype);
        case 'text':
            return 'Documento de texto';
        case 'application':
            switch ($subtype) {
                case 'pdf':
                    return 'Documento PDF';
                case 'msword':
                case 'vnd.openxmlformats-officedocument.wordprocessingml.document':
                    return 'Documento Word';
                case 'vnd.ms-excel':
                case 'vnd.openxmlformats-officedocument.spreadsheetml.sheet':
                    return 'Hoja de cálculo Excel';
                case 'vnd.ms-powerpoint':
                case 'vnd.openxmlformats-officedocument.presentationml.presentation':
                    return 'Presentación PowerPoint';
                case 'zip':
                case 'x-zip-compressed':
                    return 'Archivo ZIP';
                case 'x-rar-compressed':
                    return 'Archivo RAR';
                default:
                    return 'Archivo ' . $subtype;
            }
        default:
            return 'Archivo ' . $mimeType;
    }
}

/**
 * Método de subida mejorado con soporte para múltiples archivos
 * y verificación de tipos de archivo permitidos
 */
public function upload()
{
    SessionSecurity::startSession();

    // Verificar si se ha enviado al menos un archivo
    if (empty($_FILES['file']['name'][0])) {
        return $this->jsonResponse(['success' => false, 'message' => 'No se recibió ningún archivo.']);
    }

    // Tipos MIME permitidos (puedes personalizar esta lista según tus necesidades)
    $allowedMimeTypes = [
        // Imágenes
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        // Documentos
        'application/pdf', 'application/msword', 
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation', // .pptx
        // Texto
        'text/plain', 'text/html', 'text/css', 'text/javascript', 
        // Archivos comprimidos
        'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed',
        // Audio
        'audio/mpeg', 'audio/wav', 'audio/ogg',
        // Vídeo
        'video/mp4', 'video/webm', 'video/ogg'
    ];

    // Tamaño máximo de archivo en bytes (50MB por defecto)
    $maxFileSize = config('media.max_file_size', 50 * 1024 * 1024);

    // Variables para tracking
    $uploadedFiles = [];
    $errors = [];
    $filesCount = count($_FILES['file']['name']);

    // Procesar cada archivo
    for ($i = 0; $i < $filesCount; $i++) {
        $file = [
            'name'     => $_FILES['file']['name'][$i],
            'tmp_name' => $_FILES['file']['tmp_name'][$i],
            'error'    => $_FILES['file']['error'][$i],
            'type'     => $_FILES['file']['type'][$i],
            'size'     => $_FILES['file']['size'][$i],
        ];

        // Verificar errores de subida
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMsg = $this->getUploadErrorMessage($file['error']);
            $errors[] = "Error al subir '{$file['name']}': {$errorMsg}";
            continue;
        }

        // 🔒 SECURITY: Verificar tipo MIME REAL (no confiar en el tipo del cliente)
        // Previene: MIME spoofing, upload de archivos maliciosos
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($realMimeType, $allowedMimeTypes)) {
            $errors[] = "Tipo de archivo no permitido: {$file['name']} (detectado: {$realMimeType})";
            continue;
        }

        // Para imágenes, validación adicional con getimagesize
        if (strpos($realMimeType, 'image/') === 0) {
            $imageInfo = @getimagesize($file['tmp_name']);
            if ($imageInfo === false || $imageInfo['mime'] !== $realMimeType) {
                $errors[] = "El archivo {$file['name']} no es una imagen válida";
                continue;
            }
        }

        // Verificar tamaño
        if ($file['size'] > $maxFileSize) {
            $sizeInMB = number_format($maxFileSize / 1024 / 1024, 2);
            $errors[] = "El archivo {$file['name']} excede el tamaño máximo permitido ({$sizeInMB}MB)";
            continue;
        }

        try {
            // Determinar qué disco usar (desde POST o default 'media')
            $requestedDisk = isset($_POST['disk']) ? $_POST['disk'] : 'media';

            // Validar que el disco sea válido
            $validDisks = ['local', 'media', 'r2', 's3'];
            $diskName = in_array($requestedDisk, $validDisks) ? $requestedDisk : 'media';

            $diskConfig = config("filesystems.disks.{$diskName}");

            // Fallback a 'media' si el disco solicitado no está configurado
            if (!$diskConfig || !is_array($diskConfig)) {
                $diskName = 'media';
                $diskConfig = config('filesystems.disks.media');
            }

            // Si aún no hay configuración, usar valores por defecto para disco 'media'
            if (!$diskConfig || !is_array($diskConfig)) {
                $diskConfig = [
                    'driver' => 'local',
                    'root' => '/storage/app/media',
                    'url' => '/media/file',
                    'visibility' => 'public'
                ];
            }

            // Preparar la ruta del archivo
            $tenantId = $_SESSION['admin']['tenant_id'] ?? null;
            $subPath = $tenantId ? "tenant_{$tenantId}" : "global";
            $yearMonth = date('Y/m');
            $safeFilename = slugify(pathinfo($file['name'], PATHINFO_FILENAME)) . '_' . uniqid() . '.' . strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $relativePath = "{$subPath}/{$yearMonth}/{$safeFilename}";
            $dirPath = dirname($relativePath);

            // Crear el filesystem según el tipo de disco
            if ($diskConfig['driver'] === 's3') {
                // Para S3/R2: usar el adaptador S3
                $filesystem = $this->createS3Filesystem($diskConfig);
                if (!$filesystem) {
                    throw new \Exception("No se pudo conectar con el almacenamiento en la nube ({$diskName})");
                }
                $localRoot = null;
            } else {
                // Para discos locales
                $localRoot = APP_ROOT . $diskConfig['root'];
                $adapter = new \League\Flysystem\Local\LocalFilesystemAdapter(
                    $localRoot,
                    null, // visibility
                    LOCK_EX, // write flags
                    \League\Flysystem\Local\LocalFilesystemAdapter::DISALLOW_LINKS
                );
                $filesystem = new \League\Flysystem\Filesystem($adapter);
            }

            // Crear directorio si no existe
            if (!$filesystem->directoryExists($dirPath)) {
                $filesystem->createDirectory($dirPath);
                // Establecer permisos solo para discos locales
                if ($localRoot) {
                    @chmod($localRoot . '/' . $dirPath, 0755);
                }
            }

            // Abrir el archivo temporal
            $stream = fopen($file['tmp_name'], 'r+');
            if (!$stream) {
                $errors[] = "No se pudo abrir el archivo temporal: {$file['name']}";
                continue;
            }

            // Guardar el archivo en el sistema de archivos
            $filesystem->writeStream($relativePath, $stream);
            fclose($stream);

            // Establecer permisos solo para discos locales
            if ($localRoot) {
                @chmod($localRoot . '/' . $relativePath, 0644);
            }

            // Obtener folder_id si se especificó
            // Si es folder_id=1 (Root), guardar como NULL para mantener consistencia
            $folderId = isset($_POST['folder_id']) && $_POST['folder_id'] !== '' ? (int)$_POST['folder_id'] : null;
            if ($folderId === 1) {
                $folderId = null; // Root = NULL
            }

            // Guardar en la base de datos
            $userId = $_SESSION['super_admin']['id'] ?? ($_SESSION['admin']['id'] ?? null);
            $publicToken = Media::generatePublicToken();
            $slug = Media::generateSlug($file['name']);
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $seoFilename = $slug . '-' . $publicToken . '.' . $extension;

            $media = Media::create([
                'tenant_id'    => $tenantId,
                'user_id'      => $userId,
                'folder_id'    => $folderId,
                'disk'         => $diskName, // Usar el disco determinado (media o local)
                'path'         => $relativePath,
                'public_token' => $publicToken, // Token seguro para URL pública
                'slug'         => $slug, // Slug SEO-friendly del nombre
                'seo_filename' => $seoFilename, // Filename completo para URL SEO
                'filename'     => $file['name'],
                'mime_type'    => $file['type'],
                'size'         => $file['size'],
                'metadata'     => null,
            ]);

            // Verificar dimensiones para imágenes
            $dimensions = '';
            if (strpos($file['type'], 'image/') === 0) {
                $imageInfo = @getimagesize($file['tmp_name']);
                if ($imageInfo) {
                    $dimensions = $imageInfo[0] . 'x' . $imageInfo[1];
                }
            }

            // Añadir a archivos subidos exitosamente
            $uploadedFiles[] = [
                'id'            => $media->id,
                'filename'      => $media->filename,
                'url'           => $media->getPublicUrl(),
                'thumbnail_url' => $media->getPublicUrl(),
                'mime_type'     => $media->mime_type,
                'size'          => $media->size,
                'dimensions'    => $dimensions,
                'upload_date'   => date('d \d\e F \d\e Y')
            ];

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'MediaUpload']);

            // 🔒 SECURITY: No exponer detalles técnicos en producción
            $errorMsg = "Error al procesar '{$file['name']}'.";
            if (getenv('APP_ENV') === 'development') {
                $errorMsg .= " [DEBUG]: " . $e->getMessage();
            }
            $errors[] = $errorMsg;
        }
    }

    // Construir mensaje de respuesta
    $totalUploaded = count($uploadedFiles);
    $totalErrors = count($errors);
    $message = "";
    
    if ($totalUploaded > 0) {
        $message .= "{$totalUploaded} archivo(s) subido(s) correctamente. ";
    }
    
    if ($totalErrors > 0) {
        $message .= "{$totalErrors} archivo(s) con errores.";
    }

    // Devolver respuesta JSON
    return $this->jsonResponse([
        'success' => $totalUploaded > 0,
        'message' => $message,
        'files'   => $uploadedFiles,
        'errors'  => $errors,
        'media'   => $uploadedFiles[0] ?? null // Para compatibilidad con código existente
    ]);
}

/**
 * Traduce los códigos de error de PHP a mensajes legibles
 */
private function getUploadErrorMessage($errorCode)
{
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
            return 'El tamaño del archivo excede el límite permitido por PHP.';
        case UPLOAD_ERR_FORM_SIZE:
            return 'El tamaño del archivo excede el límite especificado en el formulario.';
        case UPLOAD_ERR_PARTIAL:
            return 'El archivo se subió parcialmente.';
        case UPLOAD_ERR_NO_FILE:
            return 'No se subió ningún archivo.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'No se encontró la carpeta temporal.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Error al escribir el archivo en disco.';
        case UPLOAD_ERR_EXTENSION:
            return 'Subida detenida por una extensión de PHP.';
        default:
            return 'Error desconocido.';
    }
}
    public function updateMeta($id)
    {
        SessionSecurity::startSession();

        try {
            $media = Media::find($id);
            if (!$media) {
                return $this->jsonResponse(['success' => false, 'message' => 'Media no encontrado.'], 404);
            }

            $altText = substr(strip_tags($_POST['alt_text'] ?? ''), 0, 255);
            $caption = substr(strip_tags($_POST['caption'] ?? ''), 0, 500);

            $media->alt_text = $altText;
            $media->caption = $caption;
            $media->save();

            return $this->jsonResponse(['success' => true, 'message' => 'Metadatos actualizados.']);
        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'MediaUpdateMeta']);
            return $this->jsonResponse(['success' => false, 'message' => 'Error al guardar los metadatos.'], 500);
        }
    }
    
    public function delete($id)
    {
        SessionSecurity::startSession();

        try {
            $media = Media::find($id);
            if (!$media) {
                return $this->jsonResponse(['success' => false, 'message' => 'Medio no encontrado.']);
            }

            $filesystem = new \League\Flysystem\Filesystem(
                new \League\Flysystem\Local\LocalFilesystemAdapter(APP_ROOT . config('filesystems.disks.local.root', '/public/assets/uploads'))
            );

            if ($filesystem->fileExists($media->path)) {
                $filesystem->delete($media->path);
            }

            if ($media->delete()) {
                return $this->jsonResponse(['success' => true, 'message' => 'Medio eliminado correctamente.']);
            }

            return $this->jsonResponse(['success' => false, 'message' => 'Error al eliminar el medio.']);

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'MediaDelete']);
            return $this->jsonResponse(['success' => false, 'message' => 'Error al eliminar el medio.'], 500);
        }
    }

    /**
     * Renombra un archivo de medios
     */
    public function renameMedia($id)
    {
        SessionSecurity::startSession();

        try {
            $media = Media::find($id);
            if (!$media) {
                return $this->jsonResponse(['success' => false, 'message' => 'Medio no encontrado.'], 404);
            }

            $newFilename = $_POST['filename'] ?? '';
            if (empty($newFilename)) {
                return $this->jsonResponse(['success' => false, 'message' => 'El nombre del archivo es requerido.'], 400);
            }

            // 🔒 SECURITY: Sanitizar nombre de archivo
            // Previene: Path traversal, overwrite de archivos del sistema
            $newFilename = basename($newFilename);

            // Validar caracteres permitidos (solo alfanuméricos, guiones, puntos, guiones bajos)
            if (!preg_match('/^[a-zA-Z0-9._-]+$/', $newFilename)) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'El nombre del archivo contiene caracteres no permitidos.'
                ], 400);
            }

            // Prevenir nombres maliciosos
            $dangerousNames = ['.htaccess', '.htpasswd', 'web.config', '.env', '.git', '.gitignore', 'composer.json', 'package.json'];
            if (in_array(strtolower($newFilename), $dangerousNames)) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Nombre de archivo no permitido.'
                ], 400);
            }

            // Prevenir múltiples extensiones (.php.jpg)
            $parts = explode('.', $newFilename);
            if (count($parts) > 2) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'El nombre de archivo no puede tener múltiples extensiones.'
                ], 400);
            }

            $oldPath = $media->path;
            $oldFilename = $media->filename;

            // Actualizar el nombre del archivo
            $media->filename = $newFilename;
            $media->save();

            // Renombrar el archivo físico si existe
            $filesystem = new \League\Flysystem\Filesystem(
                new \League\Flysystem\Local\LocalFilesystemAdapter(APP_ROOT . config('filesystems.disks.local.root', '/public/assets/uploads'))
            );

            $basePath = dirname($oldPath);
            $newPath = $basePath === '.' ? $newFilename : $basePath . '/' . $newFilename;

            if ($filesystem->fileExists($oldPath)) {
                try {
                    $filesystem->move($oldPath, $newPath);
                    $media->path = $newPath;
                    $media->save();
                } catch (\Exception $e) {
                    error_log("Error renaming physical file: " . $e->getMessage());
                }
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Archivo renombrado correctamente.',
                'media' => [
                    'id' => $media->id,
                    'filename' => $media->filename,
                    'path' => $media->path
                ]
            ]);

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'MediaRename']);
            return $this->jsonResponse(['success' => false, 'message' => 'Error al renombrar el archivo.'], 500);
        }
    }

    public function getPublicUrl()
    {
        // Si es disco local
        if ($this->disk === 'local') {
            return '/assets/uploads/' . ltrim($this->path, '/');
        }

        // Otros discos → por si en el futuro usas otro tipo
        return $this->path;
    }


    // ========================================================
    // FOLDER MANAGEMENT METHODS
    // ========================================================

    /**
     * Obtiene la estructura de carpetas y archivos del directorio actual
     */
    public function getFolderStructure()
    {
        SessionSecurity::startSession();

        // Establecer headers JSON PRIMERO antes de cualquier procesamiento
        header('Content-Type: application/json');

        try {
            $tenantId = $_SESSION['admin']['tenant_id'] ?? null;

            // Obtener disco desde parámetro (default: media)
            $disk = isset($_GET['disk']) ? $_GET['disk'] : 'media';
            $validDisks = ['local', 'media', 'r2', 's3'];
            if (!in_array($disk, $validDisks)) {
                $disk = 'media';
            }

            // Usar el modelo Folder en lugar de queries crudas
            $query = \MediaManager\Models\Folder::query();

            // Filtrar por disco
            $query->where('disk', $disk);

            // Filtrar por tenant si existe
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            } else {
                $query->whereNull('tenant_id');
            }

            // Ordenar por jerarquía
            $folders = $query->orderBy('parent_id', 'ASC')
                           ->orderBy('name', 'ASC')
                           ->get();

            // Convertir a array
            $foldersData = [];
            foreach ($folders as $folder) {
                $foldersData[] = [
                    'id' => (int)$folder->id,
                    'parent_id' => $folder->parent_id ? (int)$folder->parent_id : null,
                    'name' => $folder->name,
                    'slug' => $folder->slug,
                    'path' => $folder->path,
                    'disk' => $folder->disk ?? $disk,
                    'description' => $folder->description ?? '',
                    'created_at' => $folder->created_at ? (string)$folder->created_at : null
                ];
            }

            // Si no hay carpetas para este disco, crear la raíz
            if (empty($foldersData)) {
                $rootFolder = \MediaManager\Models\Folder::getRootFolder($tenantId, $disk);
                $foldersData[] = [
                    'id' => (int)$rootFolder->id,
                    'parent_id' => null,
                    'name' => $rootFolder->name,
                    'slug' => $rootFolder->slug,
                    'path' => $rootFolder->path,
                    'disk' => $rootFolder->disk ?? $disk,
                    'description' => $rootFolder->description ?? '',
                    'created_at' => $rootFolder->created_at ? (string)$rootFolder->created_at : null
                ];
            }

            echo json_encode([
                'success' => true,
                'folders' => $foldersData,
                'disk' => $disk
            ]);
            exit;

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'getFolderStructure', 'error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al cargar carpetas: ' . $e->getMessage(),
                'debug' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ]);
            exit;
        }
    }

    /**
     * Crea una nueva carpeta
     */
    public function createFolder()
    {
        SessionSecurity::startSession();

        try {
            $name = $_POST['name'] ?? '';
            $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
            $description = $_POST['description'] ?? '';
            $tenantId = $_SESSION['admin']['tenant_id'] ?? null;

            // Obtener disco desde POST (default: media)
            $disk = isset($_POST['disk']) ? $_POST['disk'] : 'media';
            $validDisks = ['local', 'media', 'r2', 's3'];
            if (!in_array($disk, $validDisks)) {
                $disk = 'media';
            }

            if (empty($name)) {
                return $this->jsonResponse(['success' => false, 'message' => 'El nombre de la carpeta es requerido.'], 400);
            }

            // Si no se especifica parent_id, obtener la raíz del disco actual
            if ($parentId === null) {
                $rootFolder = \MediaManager\Models\Folder::getRootFolder($tenantId, $disk);
                $parentId = $rootFolder->id;
            }

            // Generar slug único (incluyendo disco)
            $slug = \MediaManager\Models\Folder::generateSlug($name, $tenantId, $parentId, $disk);

            // Crear carpeta
            $folder = \MediaManager\Models\Folder::create([
                'tenant_id' => $tenantId,
                'parent_id' => $parentId,
                'name' => $name,
                'slug' => $slug,
                'path' => '', // Se generará automáticamente
                'disk' => $disk,
                'description' => $description
            ]);

            // Generar y actualizar path
            $folder->path = $folder->generatePath();
            $folder->save();

            // Crear directorio físico si no existe (solo para discos locales)
            if (in_array($disk, ['local', 'media'])) {
                $diskConfig = config("filesystems.disks.{$disk}");
                $diskRoot = $diskConfig['root'] ?? '/public/assets/uploads';
                $physicalPath = APP_ROOT . $diskRoot . $folder->path;
                if (!is_dir($physicalPath)) {
                    mkdir($physicalPath, 0755, true);
                }
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Carpeta creada correctamente.',
                'folder' => [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'path' => $folder->path,
                    'disk' => $folder->disk,
                    'created_at' => $folder->created_at ? $folder->created_at->format('Y-m-d H:i:s') : null
                ]
            ]);

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'createFolder']);
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Error al crear la carpeta: ' . $e->getMessage(),
                'debug' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }

    /**
     * Renombra una carpeta
     */
    public function renameFolder($id)
    {
        SessionSecurity::startSession();

        try {
            $folder = \MediaManager\Models\Folder::find($id);

            if (!$folder) {
                return $this->jsonResponse(['success' => false, 'message' => 'Carpeta no encontrada.'], 404);
            }

            // Verificar si es la carpeta raíz (no puede ser renombrada)
            // La raíz tiene path === '/' y es la única con parent_id === null
            if ($folder->id === 1 || $folder->path === '/') {
                return $this->jsonResponse(['success' => false, 'message' => 'No se puede renombrar la carpeta raíz.'], 403);
            }

            $newName = $_POST['name'] ?? '';

            if (empty($newName)) {
                return $this->jsonResponse(['success' => false, 'message' => 'El nombre es requerido.'], 400);
            }

            $oldPath = $folder->path;
            $folder->name = $newName;
            $folder->slug = \MediaManager\Models\Folder::generateSlug($newName, $folder->tenant_id, $folder->parent_id);
            $folder->path = $folder->generatePath();
            $folder->save();

            // Renombrar directorio físico
            $oldPhysicalPath = APP_ROOT . config('filesystems.disks.local.root', '/public/assets/uploads') . $oldPath;
            $newPhysicalPath = APP_ROOT . config('filesystems.disks.local.root', '/public/assets/uploads') . $folder->path;

            if (is_dir($oldPhysicalPath)) {
                rename($oldPhysicalPath, $newPhysicalPath);
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Carpeta renombrada correctamente.',
                'folder' => [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'path' => $folder->path
                ]
            ]);

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'renameFolder']);
            return $this->jsonResponse(['success' => false, 'message' => 'Error al renombrar la carpeta.'], 500);
        }
    }

    /**
     * Elimina una carpeta
     */
    public function deleteFolder($id)
    {
        SessionSecurity::startSession();

        try {
            $folder = \MediaManager\Models\Folder::find($id);

            if (!$folder) {
                return $this->jsonResponse(['success' => false, 'message' => 'Carpeta no encontrada.'], 404);
            }

            // Verificar si es la carpeta raíz (no puede ser eliminada)
            // La raíz tiene id === 1 y path === '/'
            if ($folder->id === 1 || $folder->path === '/') {
                return $this->jsonResponse(['success' => false, 'message' => 'No se puede eliminar la carpeta raíz.'], 403);
            }

            // Verificar si tiene contenido
            $mediaCount = $folder->countMediaRecursive();
            $childrenCount = count($folder->children());

            if ($mediaCount > 0 || $childrenCount > 0) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => "La carpeta contiene {$mediaCount} archivo(s) y {$childrenCount} subcarpeta(s). Debe estar vacía para eliminarla."
                ], 400);
            }

            $physicalPath = APP_ROOT . config('filesystems.disks.local.root', '/public/assets/uploads') . $folder->path;

            // Eliminar carpeta
            if ($folder->delete()) {
                // Eliminar directorio físico
                if (is_dir($physicalPath)) {
                    @rmdir($physicalPath);
                }

                return $this->jsonResponse(['success' => true, 'message' => 'Carpeta eliminada correctamente.']);
            }

            return $this->jsonResponse(['success' => false, 'message' => 'Error al eliminar la carpeta.'], 500);

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'deleteFolder']);
            return $this->jsonResponse(['success' => false, 'message' => 'Error al eliminar la carpeta.'], 500);
        }
    }

    /**
     * Mueve archivos o carpetas a otra ubicación
     */
    public function moveItems()
    {
        SessionSecurity::startSession();

        try {
            $itemIds = $_POST['item_ids'] ?? [];
            $itemType = $_POST['item_type'] ?? 'media'; // 'media' o 'folder'
            $targetFolderId = isset($_POST['target_folder_id']) && $_POST['target_folder_id'] !== '' ? (int)$_POST['target_folder_id'] : null;

            // Si target es Root (1), guardar como NULL para mantener consistencia
            if ($targetFolderId === 1) {
                $targetFolderId = null;
            }

            if (empty($itemIds) || !is_array($itemIds)) {
                return $this->jsonResponse(['success' => false, 'message' => 'No se especificaron elementos para mover.'], 400);
            }

            $movedCount = 0;
            $errors = [];

            if ($itemType === 'media') {
                foreach ($itemIds as $mediaId) {
                    $media = Media::find($mediaId);
                    if ($media && $media->moveToFolder($targetFolderId)) {
                        $movedCount++;
                    } else {
                        $errors[] = "Error moviendo archivo ID: {$mediaId}";
                    }
                }
            } elseif ($itemType === 'folder') {
                foreach ($itemIds as $folderId) {
                    $folder = \MediaManager\Models\Folder::find($folderId);
                    if ($folder && $folder->moveTo($targetFolderId)) {
                        $movedCount++;
                    } else {
                        $errors[] = "Error moviendo carpeta ID: {$folderId}";
                    }
                }
            }

            $message = "{$movedCount} elemento(s) movido(s) correctamente.";
            if (!empty($errors)) {
                $message .= " Errores: " . implode(', ', $errors);
            }

            return $this->jsonResponse([
                'success' => $movedCount > 0,
                'message' => $message,
                'moved_count' => $movedCount,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'moveItems']);
            return $this->jsonResponse(['success' => false, 'message' => 'Error al mover elementos.'], 500);
        }
    }

    /**
     * Copia archivos a otra ubicación
     */
    public function copyMedia()
    {
        SessionSecurity::startSession();

        try {
            $mediaIds = $_POST['media_ids'] ?? [];
            $targetFolderId = isset($_POST['target_folder_id']) && $_POST['target_folder_id'] !== '' ? (int)$_POST['target_folder_id'] : null;

            if (empty($mediaIds) || !is_array($mediaIds)) {
                return $this->jsonResponse(['success' => false, 'message' => 'No se especificaron archivos para copiar.'], 400);
            }

            $copiedCount = 0;
            $errors = [];

            foreach ($mediaIds as $mediaId) {
                $media = Media::find($mediaId);
                if ($media) {
                    $copy = $media->copyToFolder($targetFolderId);
                    if ($copy) {
                        $copiedCount++;
                    } else {
                        $errors[] = "Error copiando archivo ID: {$mediaId}";
                    }
                } else {
                    $errors[] = "Archivo ID {$mediaId} no encontrado.";
                }
            }

            $message = "{$copiedCount} archivo(s) copiado(s) correctamente.";
            if (!empty($errors)) {
                $message .= " Errores: " . implode(', ', $errors);
            }

            return $this->jsonResponse([
                'success' => $copiedCount > 0,
                'message' => $message,
                'copied_count' => $copiedCount,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'copyMedia']);
            return $this->jsonResponse(['success' => false, 'message' => 'Error al copiar archivos.'], 500);
        }
    }

    /**
     * Crea un sistema de archivos S3/R2 usando Flysystem
     *
     * @param array $config Configuración del disco
     * @return \League\Flysystem\Filesystem|null
     */
    private function createS3Filesystem(array $config): ?\League\Flysystem\Filesystem
    {
        try {
            // Verificar que tenemos las credenciales necesarias
            if (empty($config['key']) || empty($config['secret']) || empty($config['bucket'])) {
                Logger::warning('S3/R2: Credenciales incompletas', [
                    'has_key' => !empty($config['key']),
                    'has_secret' => !empty($config['secret']),
                    'has_bucket' => !empty($config['bucket'])
                ]);
                return null;
            }

            // Verificar que la clase de AWS SDK existe
            if (!class_exists('\Aws\S3\S3Client')) {
                Logger::error('S3/R2: AWS SDK no está instalado. Ejecuta: composer require aws/aws-sdk-php');
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
                $clientConfig['use_path_style_endpoint'] = $config['use_path_style_endpoint'] ?? false;
            }

            $client = new \Aws\S3\S3Client($clientConfig);

            // Crear adaptador S3
            $adapter = new \League\Flysystem\AwsS3V3\AwsS3V3Adapter(
                $client,
                $config['bucket'],
                '', // prefix
                new \League\Flysystem\AwsS3V3\PortableVisibilityConverter(
                    \League\Flysystem\Visibility::PUBLIC
                )
            );

            return new \League\Flysystem\Filesystem($adapter);

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'createS3Filesystem']);
            return null;
        }
    }

    /**
     * Obtiene los discos disponibles (endpoint API)
     */
    public function getAvailableDisksApi()
    {
        SessionSecurity::startSession();

        $disks = $this->getAvailableDisks();

        return $this->jsonResponse([
            'success' => true,
            'disks' => $disks
        ]);
    }

    private function jsonResponse(array $data, int $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}