<?php

namespace WpImporter\Services;

use Screenart\Musedock\Logger;

/**
 * Importa media de WordPress al Media Manager de MuseDock
 * Soporta local y Cloudflare R2 según configuración del tenant
 */
class WpMediaImporter
{
    private WpApiClient $client;
    private ?int $tenantId;
    private ?int $importFolderId = null;
    private array $urlMap = []; // url_wp => url_musedock
    private array $idMap = [];  // wp_media_id => musedock_media_id
    private array $errors = [];
    private int $imported = 0;
    private int $skipped = 0;

    public function __construct(WpApiClient $client, ?int $tenantId = null)
    {
        $this->client = $client;
        $this->tenantId = $tenantId;
    }

    /**
     * Importar todos los media items de WordPress
     * Retorna el mapa de URLs para reemplazar en el contenido
     */
    public function importAll(array $mediaItems, ?callable $onProgress = null): array
    {
        // Solo crear carpeta si hay items para importar
        if (!empty($mediaItems)) {
            $this->createImportFolder();
        }

        $total = count($mediaItems);
        $processed = 0;

        foreach ($mediaItems as $item) {
            $processed++;
            $sourceUrl = $item['source_url'] ?? null;

            if (!$sourceUrl) {
                $this->skipped++;
                continue;
            }

            // Verificar si ya fue importado (evitar duplicados)
            if (isset($this->urlMap[$sourceUrl])) {
                $this->skipped++;
                continue;
            }

            $result = $this->importSingleMedia($item);

            if ($result) {
                $this->imported++;
            } else {
                $this->skipped++;
            }

            if ($onProgress) {
                $onProgress($processed, $total, $item['title']['rendered'] ?? basename($sourceUrl));
            }
        }

        return [
            'url_map' => $this->urlMap,
            'id_map' => $this->idMap,
            'imported' => $this->imported,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
        ];
    }

    /**
     * Importar un solo media item de WordPress
     */
    public function importSingleMedia(array $wpMedia): ?array
    {
        $sourceUrl = $wpMedia['source_url'] ?? null;
        if (!$sourceUrl) {
            return null;
        }

        $mimeType = $wpMedia['mime_type'] ?? '';
        $originalName = $wpMedia['title']['rendered'] ?? basename($sourceUrl);
        $altText = $wpMedia['alt_text'] ?? '';
        $caption = $wpMedia['caption']['rendered'] ?? '';
        // Limpiar HTML del caption
        $caption = strip_tags($caption);

        // Solo importar tipos soportados (imágenes, documentos)
        $allowedPrefixes = ['image/', 'application/pdf', 'video/', 'audio/'];
        $allowed = false;
        foreach ($allowedPrefixes as $prefix) {
            if (strpos($mimeType, $prefix) === 0) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            Logger::debug("WpMediaImporter: Tipo no soportado: {$mimeType} para {$sourceUrl}");
            $this->errors[] = "Tipo no soportado: {$mimeType} ({$originalName})";
            return null;
        }

        // Verificar si ya existe un media con el mismo filename en la BD (skip re-download)
        $existingMedia = $this->findExistingMediaByFilename($sourceUrl);
        if ($existingMedia) {
            $this->urlMap[$sourceUrl] = $existingMedia['url'];
            $wpId = $wpMedia['id'] ?? null;
            if ($wpId) {
                $this->idMap[$wpId] = $existingMedia['id'];
            }
            // Mapear variantes de tamaño también
            if (isset($wpMedia['media_details']['sizes'])) {
                foreach ($wpMedia['media_details']['sizes'] as $size => $sizeData) {
                    if (isset($sizeData['source_url'])) {
                        $this->urlMap[$sizeData['source_url']] = $existingMedia['url'];
                    }
                }
            }
            Logger::debug("WpMediaImporter: Ya existe, skip: {$originalName} => {$existingMedia['url']}");
            return ['id' => $existingMedia['id'], 'url' => $existingMedia['url']];
        }

        // Descargar a temporal
        $extension = pathinfo(parse_url($sourceUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
        $tmpFile = sys_get_temp_dir() . '/wp_import_' . uniqid() . '.' . $extension;

        if (!$this->client->downloadFile($sourceUrl, $tmpFile)) {
            $this->errors[] = "Error descargando: {$originalName} ({$sourceUrl})";
            return null;
        }

        // Subir al Media Manager usando uploadFromFile
        try {
            $mediaController = $this->getMediaController();
            $result = $mediaController->uploadFromFile(
                $tmpFile,
                $mimeType ?: mime_content_type($tmpFile),
                $this->tenantId,
                [
                    'original_name' => $this->sanitizeFilename($originalName, $extension),
                    'folder_id' => $this->importFolderId,
                    'disk' => $this->getPreferredDisk(),
                    'compress' => empty($wpMedia['no_compress']),
                    'alt_text' => $altText,
                    'caption' => $caption,
                ]
            );
        } catch (\Throwable $e) {
            Logger::error("WpMediaImporter: Error subiendo {$originalName}: " . $e->getMessage());
            $this->errors[] = "Error subiendo: {$originalName} - " . $e->getMessage();
            @unlink($tmpFile);
            return null;
        }

        // Limpiar temporal
        @unlink($tmpFile);

        if (!$result || !isset($result['url'])) {
            $this->errors[] = "Error en Media Manager al subir: {$originalName}";
            return null;
        }

        // Registrar en el mapa de URLs
        $this->urlMap[$sourceUrl] = $result['url'];
        $wpId = $wpMedia['id'] ?? null;
        if ($wpId) {
            $this->idMap[$wpId] = $result['id'];
        }

        // También mapear las variantes de tamaño de WordPress
        if (isset($wpMedia['media_details']['sizes'])) {
            foreach ($wpMedia['media_details']['sizes'] as $size => $sizeData) {
                if (isset($sizeData['source_url'])) {
                    $this->urlMap[$sizeData['source_url']] = $result['url'];
                }
            }
        }

        Logger::debug("WpMediaImporter: Importado {$originalName} => {$result['url']}");

        return $result;
    }

    /**
     * Reemplazar URLs de WordPress por URLs de MuseDock en el contenido HTML
     */
    public function replaceUrlsInContent(string $content): string
    {
        if (empty($this->urlMap)) {
            return $content;
        }

        // Ordenar por longitud descendente para evitar reemplazos parciales
        $sortedMap = $this->urlMap;
        uksort($sortedMap, function ($a, $b) {
            return strlen($b) - strlen($a);
        });

        foreach ($sortedMap as $wpUrl => $musedockUrl) {
            $content = str_replace($wpUrl, $musedockUrl, $content);
        }

        // Segundo paso: reemplazar variantes de tamaño WP no mapeadas explícitamente
        // Las URLs tipo imagen-300x200.jpg son variantes de imagen.jpg
        // Si tenemos imagen.jpg en el map, reemplazar todas sus variantes
        foreach ($sortedMap as $wpUrl => $musedockUrl) {
            // Extraer base del filename sin extensión
            $parsed = parse_url($wpUrl, PHP_URL_PATH);
            if (!$parsed) continue;
            $ext = pathinfo($parsed, PATHINFO_EXTENSION);
            $base = pathinfo($parsed, PATHINFO_FILENAME);
            if (!$ext || !$base) continue;

            // Buscar en el contenido variantes tipo base-NNNxNNN.ext del mismo directorio
            $dir = dirname($wpUrl);
            $pattern = preg_quote($dir . '/' . $base, '/') . '-\d+x\d+\.' . preg_quote($ext, '/');
            $content = preg_replace('/' . $pattern . '/', $musedockUrl, $content);
        }

        return $content;
    }

    /**
     * Obtener el mapa de URLs actual
     */
    public function getUrlMap(): array
    {
        return $this->urlMap;
    }

    /**
     * Obtener el mapa de IDs actual
     */
    public function getIdMap(): array
    {
        return $this->idMap;
    }

    /**
     * Obtener los errores acumulados
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    // ====================================================================
    // PRIVATE METHODS
    // ====================================================================

    /**
     * Crear carpeta "Import" en el Media Manager
     */
    private function createImportFolder(): void
    {
        if ($this->importFolderId !== null) {
            return;
        }

        try {
            $disk = $this->getPreferredDisk();

            // Buscar si ya existe (por nombre "Import" o legacy "WordPress Import")
            foreach (['Import', 'WordPress Import'] as $folderName) {
                $existing = \MediaManager\Models\Folder::query()
                    ->where('name', $folderName)
                    ->where('disk', $disk);

                if ($this->tenantId !== null) {
                    $existing->where('tenant_id', $this->tenantId);
                } else {
                    $existing->whereNull('tenant_id');
                }

                $folder = $existing->first();
                if ($folder) {
                    $this->importFolderId = $folder['id'];
                    return;
                }
            }

            // Obtener root folder (global, path='/')
            $rootQuery = \MediaManager\Models\Folder::query()
                ->where('disk', $disk)
                ->where('path', '/');

            // Root folders son siempre globales (tenant_id NULL)
            $rootQuery->whereNull('tenant_id');

            $root = $rootQuery->first();
            $parentId = $root ? $root['id'] : null;

            // Crear carpeta
            $folderId = \MediaManager\Models\Folder::create([
                'tenant_id' => $this->tenantId,
                'parent_id' => $parentId,
                'name' => 'Import',
                'slug' => 'import',
                'path' => '/import/',
                'disk' => $disk,
                'description' => 'Media importado',
            ]);

            $this->importFolderId = $folderId;
            Logger::info("WpMediaImporter: Carpeta 'Import' creada con ID {$folderId}");
        } catch (\Throwable $e) {
            Logger::error("WpMediaImporter: Error creando carpeta: " . $e->getMessage());
            // Continuar sin carpeta (se subirán a la raíz)
        }
    }

    /**
     * Obtener el disco preferido para el tenant
     */
    private function getPreferredDisk(): string
    {
        // Si R2 está habilitado, usar R2
        if (\Screenart\Musedock\Env::get('TENANT_DISK_R2_ENABLED', false)) {
            $r2Key = \Screenart\Musedock\Env::get('R2_ACCESS_KEY_ID', '');
            if (!empty($r2Key)) {
                return 'r2';
            }
        }

        // Si S3 está habilitado, usar S3
        if (\Screenart\Musedock\Env::get('TENANT_DISK_S3_ENABLED', false)) {
            $s3Key = \Screenart\Musedock\Env::get('AWS_ACCESS_KEY_ID', '');
            if (!empty($s3Key)) {
                return 's3';
            }
        }

        // Fallback a media (local seguro)
        return 'media';
    }

    /**
     * Buscar media existente por filename derivado de la URL de WordPress
     */
    private function findExistingMediaByFilename(string $sourceUrl): ?array
    {
        try {
            $path = parse_url($sourceUrl, PHP_URL_PATH);
            if (!$path) return null;
            $filename = basename($path);
            if (empty($filename)) return null;

            // Buscar por filename en la tabla media
            $tenantCondition = $this->tenantId !== null
                ? "tenant_id = :tenant_id"
                : "tenant_id IS NULL";
            $params = ['filename' => '%' . $filename];
            if ($this->tenantId !== null) {
                $params['tenant_id'] = $this->tenantId;
            }

            $row = \Screenart\Musedock\Database::query(
                "SELECT id, url FROM media WHERE url LIKE :filename AND {$tenantCondition} LIMIT 1",
                $params
            )->fetch();

            if ($row && !empty($row['url'])) {
                return ['id' => $row['id'], 'url' => $row['url']];
            }
        } catch (\Throwable $e) {
            Logger::debug("WpMediaImporter: Error buscando media existente: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Obtener instancia del MediaController
     */
    private function getMediaController(): \MediaManager\Controllers\MediaController
    {
        static $controller = null;
        if ($controller === null) {
            $controller = new \MediaManager\Controllers\MediaController();
        }
        return $controller;
    }

    /**
     * Sanitizar nombre de archivo
     */
    private function sanitizeFilename(string $name, string $extension): string
    {
        // Eliminar HTML
        $name = strip_tags($name);
        // Si ya tiene extensión, no añadir otra
        $existingExt = pathinfo($name, PATHINFO_EXTENSION);
        if (empty($existingExt)) {
            $name .= '.' . $extension;
        }
        return $name;
    }
}
