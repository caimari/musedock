<?php

namespace ReactSliders\Models;

use Screenart\Musedock\Database\Model;

class ReactSlide extends Model
{
    protected static string $table = 'react_slides';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'slider_id',
        'title',
        'subtitle',
        'description',
        'image_url',
        'button_text',
        'button_link',
        'button_target',
        'background_color',
        'text_color',
        'overlay_opacity',
        'sort_order',
        'is_active',
        'custom_css',
        'custom_data'
    ];

    protected array $casts = [
        'is_active' => 'bool',
        'sort_order' => 'int',
        'overlay_opacity' => 'float',
        'custom_data' => 'array'
    ];

    /**
     * Relación: Una diapositiva pertenece a un slider
     */
    public function slider(): ?ReactSlider
    {
        return ReactSlider::find($this->slider_id);
    }

    /**
     * Obtener el siguiente orden disponible para un slider
     */
    public static function getNextOrder(int $sliderId): int
    {
        $maxOrder = self::query()
            ->where('slider_id', $sliderId)
            ->max('sort_order');

        return ($maxOrder ?? 0) + 1;
    }

    /**
     * Reordenar diapositivas
     */
    public static function reorder(array $slideIds): bool
    {
        try {
            foreach ($slideIds as $index => $slideId) {
                self::query()
                    ->where('id', $slideId)
                    ->update(['sort_order' => $index + 1]);
            }
            return true;
        } catch (\Exception $e) {
            error_log("Error reordenando slides: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener datos para el frontend
     */
    public function toFrontendArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'description' => $this->description,
            'image' => $this->image_url,
            'button' => [
                'text' => $this->button_text,
                'link' => $this->button_link,
                'target' => $this->button_target ?? '_self'
            ],
            'styles' => [
                'backgroundColor' => $this->background_color,
                'color' => $this->text_color,
                'overlayOpacity' => $this->overlay_opacity ?? 0.3
            ],
            'customCss' => $this->custom_css,
            'customData' => $this->custom_data ?? []
        ];
    }
}
