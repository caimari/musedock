<?php

namespace ImageGallery\Models;

use Screenart\Musedock\Database\Model;

/**
 * Gallery Model
 *
 * Representa una galería de imágenes con configuración personalizable
 */
class Gallery extends Model
{
    protected static string $table = 'image_galleries';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'thumbnail_url',
        'layout_type',
        'columns',
        'gap',
        'settings',
        'is_active',
        'featured',
        'sort_order'
    ];

    protected array $casts = [
        'settings' => 'array',
        'is_active' => 'bool',
        'featured' => 'bool',
        'columns' => 'int',
        'gap' => 'int',
        'sort_order' => 'int'
        // NOTA: No castear tenant_id a int para preservar NULL
    ];

    /**
     * Obtiene todas las imágenes de la galería
     */
    public function images(): array
    {
        return GalleryImage::getByGallery($this->id, false);
    }

    /**
     * Obtiene solo las imágenes activas de la galería
     */
    public function activeImages(): array
    {
        return GalleryImage::getByGallery($this->id, true);
    }

    /**
     * Cuenta las imágenes de la galería
     */
    public function imageCount(): int
    {
        $result = GalleryImage::query()
            ->where('gallery_id', $this->id)
            ->count();
        return $result;
    }

    /**
     * Busca una galería por slug
     * Para galerías globales, busca tanto tenant_id = NULL como 0 por compatibilidad
     */
    public static function findBySlug(string $slug, ?int $tenantId = null): ?self
    {
        $query = self::query()->where('slug', $slug);

        if ($tenantId !== null) {
            // Buscar galerías del tenant O globales (NULL/0)
            $query->whereRaw('(tenant_id = ? OR tenant_id IS NULL OR tenant_id = 0)', [$tenantId]);
        } else {
            // Si no se especifica tenant, buscar solo galerías globales
            $query->whereRaw('(tenant_id IS NULL OR tenant_id = 0)');
        }

        $result = $query->first();
        return $result ? self::newFromBuilder($result) : null;
    }

    /**
     * Obtiene galerías por tenant
     * Para galerías globales, incluye tanto tenant_id = NULL como 0 por compatibilidad
     */
    public static function getByTenant(?int $tenantId = null, bool $includeGlobal = true): array
    {
        $query = self::query();

        if ($tenantId !== null) {
            if ($includeGlobal) {
                // Galerías del tenant O globales (NULL/0)
                $query->whereRaw('(tenant_id = ? OR tenant_id IS NULL OR tenant_id = 0)', [$tenantId]);
            } else {
                $query->where('tenant_id', $tenantId);
            }
        } else {
            // Para galerías globales, incluir tanto NULL como 0
            $query->whereRaw('(tenant_id IS NULL OR tenant_id = 0)');
        }

        $rows = $query->orderBy('sort_order', 'ASC')
            ->orderBy('created_at', 'DESC')
            ->get();

        return self::hydrateCollection($rows);
    }

    /**
     * Obtiene galerías activas
     * Para galerías globales, incluye tanto tenant_id = NULL como 0 por compatibilidad
     */
    public static function getActive(?int $tenantId = null, bool $includeGlobal = true): array
    {
        $query = self::query()->where('is_active', 1);

        if ($tenantId !== null) {
            if ($includeGlobal) {
                // Galerías del tenant O globales (NULL/0)
                $query->whereRaw('(tenant_id = ? OR tenant_id IS NULL OR tenant_id = 0)', [$tenantId]);
            } else {
                $query->where('tenant_id', $tenantId);
            }
        }

        $rows = $query->orderBy('sort_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->get();

        return self::hydrateCollection($rows);
    }

    /**
     * Obtiene galerías destacadas
     * Para galerías globales, incluye tanto tenant_id = NULL como 0 por compatibilidad
     */
    public static function getFeatured(?int $tenantId = null, int $limit = 5): array
    {
        $query = self::query()
            ->where('is_active', 1)
            ->where('featured', 1);

        if ($tenantId !== null) {
            // Galerías del tenant O globales (NULL/0)
            $query->whereRaw('(tenant_id = ? OR tenant_id IS NULL OR tenant_id = 0)', [$tenantId]);
        }

        $rows = $query->orderBy('sort_order', 'ASC')
            ->limit($limit)
            ->get();

        return self::hydrateCollection($rows);
    }

    /**
     * Genera un slug único
     */
    public static function generateUniqueSlug(string $name, ?int $tenantId = null, ?int $excludeId = null): string
    {
        $baseSlug = self::createSlug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (self::slugExists($slug, $tenantId, $excludeId)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Verifica si un slug ya existe
     * Para galerías globales (tenantId = null), verifica tanto NULL como 0 por compatibilidad
     */
    public static function slugExists(string $slug, ?int $tenantId = null, ?int $excludeId = null): bool
    {
        $query = self::query()->where('slug', $slug);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            // Para galerías globales, verificar tanto NULL como 0 (por compatibilidad con registros antiguos)
            $query->whereRaw('(tenant_id IS NULL OR tenant_id = 0)');
        }

        if ($excludeId !== null) {
            $query->whereRaw('id != ?', [$excludeId]);
        }

        return $query->first() !== null;
    }

    /**
     * Crea un slug a partir de un texto
     */
    private static function createSlug(string $text): string
    {
        // Convertir a minúsculas y transliterar
        $slug = mb_strtolower($text, 'UTF-8');

        // Reemplazar caracteres especiales
        $replacements = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ñ' => 'n', 'ü' => 'u', 'ç' => 'c',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o',
        ];
        $slug = strtr($slug, $replacements);

        // Eliminar caracteres no alfanuméricos
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);

        // Reemplazar espacios y guiones múltiples
        $slug = preg_replace('/[\s-]+/', '-', $slug);

        // Eliminar guiones al inicio y final
        $slug = trim($slug, '-');

        return $slug ?: 'gallery';
    }

    /**
     * Obtiene la configuración con valores por defecto
     */
    public function getSettings(): array
    {
        $defaults = [
            'show_title' => true,
            'show_caption' => true,
            'enable_lightbox' => true,
            'enable_lazy_loading' => true,
            'thumbnail_size' => 'medium',
            'hover_effect' => 'zoom',
            'border_radius' => 8,
            'image_fit' => 'cover',
            'aspect_ratio' => '1:1',
            'animation_duration' => 300,
        ];

        $settings = $this->settings ?? [];

        return array_merge($defaults, $settings);
    }

    /**
     * Establece la imagen de portada desde la primera imagen
     */
    public function setThumbnailFromFirstImage(): bool
    {
        $firstImage = GalleryImage::query()
            ->where('gallery_id', $this->id)
            ->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')
            ->first();

        if ($firstImage) {
            $this->thumbnail_url = $firstImage->thumbnail_url ?: $firstImage->image_url;
            return $this->save();
        }

        return false;
    }

    /**
     * Obtiene los layouts disponibles
     */
    public static function getAvailableLayouts(): array
    {
        return [
            'grid' => [
                'name' => 'Cuadrícula',
                'description' => 'Disposición uniforme en filas y columnas',
                'icon' => 'bi-grid-3x3'
            ],
            'masonry' => [
                'name' => 'Masonry',
                'description' => 'Disposición tipo Pinterest',
                'icon' => 'bi-columns-gap'
            ],
            'carousel' => [
                'name' => 'Carrusel',
                'description' => 'Slider horizontal con navegación',
                'icon' => 'bi-collection-play'
            ],
            'lightbox' => [
                'name' => 'Lightbox',
                'description' => 'Miniaturas con vista ampliada',
                'icon' => 'bi-fullscreen'
            ],
            'justified' => [
                'name' => 'Justificado',
                'description' => 'Filas con altura uniforme',
                'icon' => 'bi-justify'
            ],
        ];
    }

    /**
     * Serializa la galería para uso en frontend
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'thumbnail_url' => $this->thumbnail_url,
            'layout_type' => $this->layout_type,
            'columns' => $this->columns,
            'gap' => $this->gap,
            'settings' => $this->getSettings(),
            'is_active' => $this->is_active,
            'featured' => $this->featured,
            'image_count' => $this->imageCount(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
