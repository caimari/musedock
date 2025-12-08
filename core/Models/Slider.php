<?php

namespace Screenart\Musedock\Models;

use Screenart\Musedock\Database\Model;

class Slider extends Model
{
    protected static string $table = 'sliders';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    // Campos que se pueden rellenar en create/update
    protected array $fillable = [
        'name',
        'settings',
        'engine', // Añadido para que engine también se pueda actualizar fácilmente
    ];

    // Cast para que settings siempre sea un array
    protected array $casts = [
        'settings' => 'array',
    ];

    /**
     * Relación: Un slider tiene muchas diapositivas (ordenadas por sort_order)
     */
    public function slides()
    {
        return Slide::query()
            ->where('slider_id', $this->id)
            ->orderBy('sort_order', 'ASC')
            ->get();
    }

    /**
     * Devuelve los ajustes combinados: settings + engine
     */
    public function getFullSettings(): array
    {
        $settings = $this->settings ?? [];

        // Incluir siempre el engine de la tabla como referencia
        $settings['engine'] = $this->engine ?? 'swiper';

        return $settings;
    }
}
