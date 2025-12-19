<?php

namespace Elements\Models;

use Screenart\Musedock\Database\Model;

/**
 * Element Model
 *
 * Represents a reusable content element (Hero, FAQ, CTA, etc.)
 */
class Element extends Model
{
    protected static string $table = 'elements';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'type',
        'layout_type',
        'data',
        'settings',
        'is_active',
        'featured',
        'sort_order'
    ];

    protected array $casts = [
        'data' => 'array',
        'settings' => 'array',
        'is_active' => 'bool',
        'featured' => 'bool',
        'sort_order' => 'int'
        // NOTA: No castear tenant_id a int para preservar NULL
    ];

    /**
     * Available element types
     */
    public static function getAvailableTypes(): array
    {
        return [
            'hero' => __element('element.type_hero'),
            'highlight' => __element('element.type_highlight'),
            'divider' => __element('element.type_divider'),
            'faq' => __element('element.type_faq'),
            'cta' => __element('element.type_cta'),
            'features' => __element('element.type_features'),
            'testimonials' => __element('element.type_testimonials'),
            'stats' => __element('element.type_stats'),
            'timeline' => __element('element.type_timeline')
        ];
    }

    /**
     * Available layouts for Heroes
     */
    public static function getHeroLayouts(): array
    {
        return [
            'image-right' => __element('hero.layout_image_right'),
            'image-left' => __element('hero.layout_image_left'),
            'centered' => __element('hero.layout_centered'),
            'background' => __element('hero.layout_background'),
            'video' => __element('hero.layout_video')
        ];
    }

    /**
     * Available layouts for FAQs
     */
    public static function getFaqLayouts(): array
    {
        return [
            'accordion' => __element('faq.layout_accordion'),
            'simple' => __element('faq.layout_simple'),
            'two-columns' => __element('faq.layout_two_columns')
        ];
    }

    /**
     * Available layouts for CTAs
     */
    public static function getCtaLayouts(): array
    {
        return [
            'horizontal' => __element('cta.layout_horizontal'),
            'centered' => __element('cta.layout_centered'),
            'box' => __element('cta.layout_box')
        ];
    }

    /**
     * Available layouts for Highlight Sections
     */
    public static function getHighlightLayouts(): array
    {
        return [
            'centered' => __element('highlight.layout_centered'),
            'left' => __element('highlight.layout_left'),
            'right' => __element('highlight.layout_right')
        ];
    }

    /**
     * Available layouts for Dividers
     */
    public static function getDividerLayouts(): array
    {
        return [
            'spacer' => __element('divider.layout_spacer'),
            'line' => __element('divider.layout_line'),
            'dots' => __element('divider.layout_dots'),
            'zigzag' => __element('divider.layout_zigzag'),
            'wave' => __element('divider.layout_wave'),
            'arrows' => __element('divider.layout_arrows'),
            'diamonds' => __element('divider.layout_diamonds')
        ];
    }

    /**
     * Get layouts for a specific type
     */
    public static function getLayoutsForType(string $type): array
    {
        return match ($type) {
            'hero' => self::getHeroLayouts(),
            'highlight' => self::getHighlightLayouts(),
            'divider' => self::getDividerLayouts(),
            'faq' => self::getFaqLayouts(),
            'cta' => self::getCtaLayouts(),
            default => []
        };
    }

    /**
     * Find element by slug
     * For global elements, searches both tenant_id = NULL and 0 for compatibility
     */
    public static function findBySlug(string $slug, ?int $tenantId = null): ?self
    {
        $query = self::query()->where('slug', $slug);

        if ($tenantId !== null) {
            // Search tenant elements OR global (NULL/0)
            $query->whereRaw('(tenant_id = ? OR tenant_id IS NULL OR tenant_id = 0)', [$tenantId]);
        } else {
            // If no tenant specified, search only global elements
            $query->whereRaw('(tenant_id IS NULL OR tenant_id = 0)');
        }

        $result = $query->first();
        return $result ? self::newFromBuilder($result) : null;
    }

    /**
     * Get elements by tenant
     * For global elements, includes both tenant_id = NULL and 0 for compatibility
     */
    public static function getByTenant(?int $tenantId = null, bool $includeGlobal = true): array
    {
        $query = self::query();

        if ($tenantId !== null) {
            if ($includeGlobal) {
                // Tenant elements OR global (NULL/0)
                $query->whereRaw('(tenant_id = ? OR tenant_id IS NULL OR tenant_id = 0)', [$tenantId]);
            } else {
                // Only tenant elements
                $query->where('tenant_id', $tenantId);
            }
        } else {
            // Only global elements
            $query->whereRaw('(tenant_id IS NULL OR tenant_id = 0)');
        }

        $query->orderBy('sort_order', 'ASC')
              ->orderBy('created_at', 'DESC');

        $results = $query->get();
        return array_map(fn($row) => self::newFromBuilder($row), $results);
    }

    /**
     * Get elements by type
     */
    public static function getByType(string $type, ?int $tenantId = null, bool $activeOnly = false): array
    {
        $query = self::query()->where('type', $type);

        if ($tenantId !== null) {
            $query->whereRaw('(tenant_id = ? OR tenant_id IS NULL OR tenant_id = 0)', [$tenantId]);
        } else {
            $query->whereRaw('(tenant_id IS NULL OR tenant_id = 0)');
        }

        if ($activeOnly) {
            $query->where('is_active', 1);
        }

        $query->orderBy('sort_order', 'ASC')
              ->orderBy('created_at', 'DESC');

        $results = $query->get();
        return array_map(fn($row) => self::newFromBuilder($row), $results);
    }

    /**
     * Generate unique slug
     */
    public static function generateUniqueSlug(string $name, ?int $tenantId = null, ?int $excludeId = null): string
    {
        $baseSlug = self::slugify($name);
        $slug = $baseSlug;
        $counter = 1;

        while (self::slugExists($slug, $tenantId, $excludeId)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Check if slug exists
     */
    private static function slugExists(string $slug, ?int $tenantId = null, ?int $excludeId = null): bool
    {
        $query = self::query()->where('slug', $slug);

        // Check within same tenant scope
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereRaw('(tenant_id IS NULL OR tenant_id = 0)');
        }

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Slugify a string
     */
    private static function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);

        return empty($text) ? 'element-' . uniqid() : $text;
    }

    /**
     * Check if element is global
     */
    public function isGlobal(): bool
    {
        return $this->tenant_id === null || $this->tenant_id === 0;
    }

    /**
     * Check if element belongs to a specific tenant
     */
    public function belongsToTenant(int $tenantId): bool
    {
        return $this->tenant_id === $tenantId;
    }

    /**
     * Get the element data decoded
     */
    public function getData(): array
    {
        return is_array($this->data) ? $this->data : [];
    }

    /**
     * Get the element settings decoded
     */
    public function getSettings(): array
    {
        return is_array($this->settings) ? $this->settings : [];
    }
}
