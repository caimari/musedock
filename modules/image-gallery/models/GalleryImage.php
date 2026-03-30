<?php

namespace ImageGallery\Models;

use Screenart\Musedock\Database\Model;
use MediaManager\Models\Media;

/**
 * GalleryImage Model
 *
 * Representa una imagen individual dentro de una galería
 */
class GalleryImage extends Model
{
    protected static string $table = 'gallery_images';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'gallery_id',
        'disk',
        'public_token',
        'file_name',
        'file_path',
        'file_hash',
        'file_size',
        'mime_type',
        'image_url',
        'thumbnail_url',
        'medium_url',
        'title',
        'alt_text',
        'caption',
        'link_url',
        'link_target',
        'width',
        'height',
        'sort_order',
        'is_active',
        'metadata'
    ];

    protected array $casts = [
        'metadata' => 'array',
        'is_active' => 'bool',
        'gallery_id' => 'int',
        'file_size' => 'int',
        'width' => 'int',
        'height' => 'int',
        'sort_order' => 'int'
    ];

    /**
     * Obtiene la galería a la que pertenece esta imagen
     */
    public function gallery(): ?Gallery
    {
        return Gallery::find($this->gallery_id);
    }

    /**
     * Obtiene imágenes por galería
     */
    public static function getByGallery(int $galleryId, bool $onlyActive = true): array
    {
        $query = self::query()->where('gallery_id', $galleryId);

        if ($onlyActive) {
            $query->where('is_active', 1);
        }

        $rows = $query->orderBy('sort_order', 'ASC')->get();

        return self::hydrateCollection($rows);
    }

    /**
     * Obtiene el siguiente orden disponible para una galería
     */
    public static function getNextSortOrder(int $galleryId): int
    {
        $maxOrder = self::query()
            ->where('gallery_id', $galleryId)
            ->max('sort_order');

        return ($maxOrder ?? 0) + 1;
    }

    /**
     * Reordena las imágenes de una galería
     */
    public static function reorder(int $galleryId, array $imageIds): bool
    {
        $pdo = \Screenart\Musedock\Database::connect();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE `gallery_images`
                SET `sort_order` = ?
                WHERE `id` = ? AND `gallery_id` = ?
            ");

            foreach ($imageIds as $order => $imageId) {
                $stmt->execute([$order, $imageId, $galleryId]);
            }

            $pdo->commit();
            return true;

        } catch (\Exception $e) {
            $pdo->rollBack();
            error_log("Error reordering gallery images: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene el tamaño formateado
     */
    public function getFormattedSize(): string
    {
        $bytes = $this->file_size ?? 0;

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' bytes';
    }

    /**
     * Obtiene las dimensiones formateadas
     */
    public function getDimensions(): string
    {
        if ($this->width && $this->height) {
            return $this->width . ' × ' . $this->height . ' px';
        }
        return 'Desconocido';
    }

    /**
     * Obtiene la relación de aspecto
     */
    public function getAspectRatio(): float
    {
        if ($this->width && $this->height && $this->height > 0) {
            return round($this->width / $this->height, 2);
        }
        return 1.0;
    }

    /**
     * Verifica si es una imagen horizontal
     */
    public function isLandscape(): bool
    {
        return $this->getAspectRatio() > 1;
    }

    /**
     * Verifica si es una imagen vertical
     */
    public function isPortrait(): bool
    {
        return $this->getAspectRatio() < 1;
    }

    /**
     * Verifica si es una imagen cuadrada
     */
    public function isSquare(): bool
    {
        return abs($this->getAspectRatio() - 1) < 0.1;
    }

    /**
     * Obtiene la URL apropiada según el tamaño solicitado
     */
    public function getUrl(string $size = 'original'): string
    {
        switch ($size) {
            case 'thumbnail':
            case 'small':
                return $this->thumbnail_url ?: $this->image_url;
            case 'medium':
                return $this->medium_url ?: $this->image_url;
            case 'large':
            case 'original':
            default:
                return $this->image_url;
        }
    }

    /**
     * Obtiene el texto alternativo o genera uno por defecto
     */
    public function getAltText(): string
    {
        if (!empty($this->alt_text)) {
            return $this->alt_text;
        }

        if (!empty($this->title)) {
            return $this->title;
        }

        // Generar desde el nombre del archivo
        $name = pathinfo($this->file_name, PATHINFO_FILENAME);
        $name = str_replace(['-', '_'], ' ', $name);
        return ucfirst($name);
    }

    /**
     * Verifica si la imagen tiene un enlace configurado
     */
    public function hasLink(): bool
    {
        return !empty($this->link_url);
    }

    /**
     * Obtiene los metadatos EXIF si existen
     */
    public function getExifData(): array
    {
        $metadata = $this->metadata ?? [];
        return $metadata['exif'] ?? [];
    }

    /**
     * Verifica si el archivo físico existe
     */
    public function fileExists(): bool
    {
        if (empty($this->file_path)) {
            return false;
        }

        // Legacy: rutas públicas bajo DOCUMENT_ROOT
        if (empty($this->disk)) {
            $fullPath = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . ltrim($this->file_path, '/');
            return file_exists($fullPath);
        }

        // Cloud: no comprobamos existencia aquí (evita latencia/credenciales)
        if (in_array($this->disk, ['r2', 's3'], true)) {
            return true;
        }

        // Discos locales configurados en filesystems.php (media/local/etc.)
        $filesystemsConfig = require APP_ROOT . '/config/filesystems.php';
        $diskConfig = $filesystemsConfig['disks'][$this->disk] ?? null;
        if (!$diskConfig || !is_array($diskConfig) || ($diskConfig['driver'] ?? 'local') !== 'local') {
            return false;
        }

        $root = APP_ROOT . ($diskConfig['root'] ?? '/storage/app/media');
        $fullPath = rtrim($root, '/') . '/' . ltrim($this->file_path, '/');
        return file_exists($fullPath);
    }

    /**
     * Elimina el archivo físico y la entrada de BD
     */
    public function deleteWithFile(): bool
    {
        // Si existe Media asociado por token, intentar borrarlo primero (y su archivo)
        if (!empty($this->public_token) && class_exists(Media::class)) {
            $media = Media::findByToken($this->public_token);
            if ($media) {
                // Intento best-effort: eliminar archivo físico según disco
                try {
                    if ($media->disk === 'media') {
                        $fullPath = APP_ROOT . '/storage/app/media/' . ltrim($media->path, '/');
                        if (file_exists($fullPath)) {
                            @unlink($fullPath);
                        }
                    } elseif (in_array($media->disk, ['r2', 's3'], true)) {
                        $filesystemsConfig = require APP_ROOT . '/config/filesystems.php';
                        $diskConfig = $filesystemsConfig['disks'][$media->disk] ?? null;
                        if ($diskConfig && ($diskConfig['driver'] ?? null) === 's3') {
                            $clientConfig = [
                                'version' => 'latest',
                                'region' => $diskConfig['region'] ?? 'auto',
                                'credentials' => [
                                    'key' => $diskConfig['key'] ?? null,
                                    'secret' => $diskConfig['secret'] ?? null,
                                ],
                            ];
                            if (!empty($diskConfig['endpoint'])) {
                                $clientConfig['endpoint'] = $diskConfig['endpoint'];
                            }
                            if (isset($diskConfig['use_path_style_endpoint'])) {
                                $clientConfig['use_path_style_endpoint'] = $diskConfig['use_path_style_endpoint'];
                            }

                            $client = new \Aws\S3\S3Client($clientConfig);
                            $adapter = new \League\Flysystem\AwsS3V3\AwsS3V3Adapter(
                                $client,
                                $diskConfig['bucket'],
                                '',
                                null,
                                null,
                                ['ACL' => 'public-read']
                            );
                            $filesystem = new \League\Flysystem\Filesystem($adapter);
                            if ($filesystem->fileExists($media->path)) {
                                $filesystem->delete($media->path);
                            }
                        }
                    } else {
                        $filesystemsConfig = require APP_ROOT . '/config/filesystems.php';
                        $diskConfig = $filesystemsConfig['disks'][$media->disk] ?? null;
                        if ($diskConfig && ($diskConfig['driver'] ?? 'local') === 'local') {
                            $root = APP_ROOT . ($diskConfig['root'] ?? '/storage/app/media');
                            $fullPath = rtrim($root, '/') . '/' . ltrim($media->path, '/');
                            if (file_exists($fullPath)) {
                                @unlink($fullPath);
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // best-effort, no bloquear el borrado de BD
                }

                // Borrar fila media (si existe)
                try {
                    $media->delete();
                } catch (\Throwable $e) {
                    // Ignorar
                }
            }
        }

        // Legacy o si no hay Media asociado: eliminar archivos físicos conocidos
        if (empty($this->disk)) {
            $basePath = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
            $filesToDelete = array_filter([
                $this->file_path,
                $this->thumbnail_url ? parse_url($this->thumbnail_url, PHP_URL_PATH) : null,
                $this->medium_url ? parse_url($this->medium_url, PHP_URL_PATH) : null,
            ]);

            foreach ($filesToDelete as $file) {
                $fullPath = $basePath . '/' . ltrim($file, '/');
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
            }
        }

        return $this->delete();
    }

    /**
     * Serializa la imagen para uso en frontend/API
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'gallery_id' => $this->gallery_id,
            'file_name' => $this->file_name,
            'image_url' => $this->image_url,
            'thumbnail_url' => $this->thumbnail_url ?: $this->image_url,
            'medium_url' => $this->medium_url ?: $this->image_url,
            'title' => $this->title,
            'alt_text' => $this->getAltText(),
            'caption' => $this->caption,
            'link_url' => $this->link_url,
            'link_target' => $this->link_target,
            'width' => $this->width,
            'height' => $this->height,
            'dimensions' => $this->getDimensions(),
            'file_size' => $this->file_size,
            'formatted_size' => $this->getFormattedSize(),
            'aspect_ratio' => $this->getAspectRatio(),
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * Genera el HTML de la imagen para usar en templates
     */
    public function toHtml(array $options = []): string
    {
        $defaults = [
            'size' => 'medium',
            'class' => '',
            'lazy' => true,
            'with_link' => true,
        ];

        $options = array_merge($defaults, $options);

        $url = $this->getUrl($options['size']);
        $alt = htmlspecialchars($this->getAltText(), ENT_QUOTES, 'UTF-8');
        $class = htmlspecialchars($options['class'], ENT_QUOTES, 'UTF-8');
        $loading = $options['lazy'] ? 'loading="lazy"' : '';

        $imgTag = sprintf(
            '<img src="%s" alt="%s" class="%s" %s>',
            $url,
            $alt,
            $class,
            $loading
        );

        if ($options['with_link'] && $this->hasLink()) {
            $linkUrl = htmlspecialchars($this->link_url, ENT_QUOTES, 'UTF-8');
            $target = $this->link_target === '_blank' ? 'target="_blank" rel="noopener"' : '';
            return sprintf('<a href="%s" %s>%s</a>', $linkUrl, $target, $imgTag);
        }

        return $imgTag;
    }
}
