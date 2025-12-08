<?php

namespace ReactSliders\Models;

use Screenart\Musedock\Database\Model;

class ReactSlider extends Model
{
    protected static string $table = 'react_sliders';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'name',
        'identifier',
        'engine',
        'settings',
        'tenant_id',
        'is_active'
    ];

    protected array $casts = [
        'settings' => 'array',
        'is_active' => 'bool'
    ];

    /**
     * Relación: Un slider tiene muchas diapositivas
     */
    public function slides()
    {
        return ReactSlide::query()
            ->where('slider_id', $this->id)
            ->orderBy('sort_order', 'ASC')
            ->get();
    }

    /**
     * Obtener configuración completa del slider
     */
    public function getFullSettings(): array
    {
        $defaultSettings = [
            'engine' => 'swiper',
            'animation' => 'slide',
            'autoplay' => true,
            'autoplay_delay' => 5000,
            'loop' => true,
            'navigation' => true,
            'pagination' => true,
            'slides_per_view' => 1,
            'space_between' => 0,
            'speed' => 500
        ];

        $settings = array_merge($defaultSettings, $this->settings ?? []);
        $settings['engine'] = $this->engine ?? 'swiper';

        return $settings;
    }

    /**
     * Buscar slider por identificador
     */
    public static function findByIdentifier(string $identifier, ?int $tenantId = null): ?self
    {
        $query = self::query()->where('identifier', $identifier);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->first();
    }

    /**
     * Obtener sliders de un tenant
     */
    public static function getByTenant(?int $tenantId = null): array
    {
        $query = self::query();

        if ($tenantId === null) {
            $query->whereNull('tenant_id'); // Sliders globales
        } else {
            $query->where(function($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                  ->orWhereNull('tenant_id'); // Incluir globales
            });
        }

        return $query->orderBy('name')->get();
    }
}
