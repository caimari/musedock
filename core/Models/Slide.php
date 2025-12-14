<?php
namespace Screenart\Musedock\Models;
use Screenart\Musedock\Database\Model;

class Slide extends Model {
    protected static string $table = 'slider_slides';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;
    protected array $fillable = [
        'slider_id', 'image_url', 'title', 'description',
        'link_url', 'link_target', 'link_text',
        'link2_url', 'link2_target', 'link2_text',
        'title_bold', 'title_font', 'description_font',
        'title_color', 'description_color',
        'button_custom', 'button_bg_color', 'button_text_color', 'button_border_color',
        'button2_custom', 'button2_bg_color', 'button2_text_color', 'button2_border_color',
        'button_shape',
        'sort_order', 'is_active'
    ];
    protected array $casts = [
        'slider_id' => 'int',
        'sort_order' => 'int',
        'is_active' => 'boolean',
        'title_bold' => 'boolean',
        'button_custom' => 'boolean',
        'button2_custom' => 'boolean'
    ];

    // Relación inversa: Una diapositiva pertenece a un slider
    public function slider() {
        return $this->belongsTo(Slider::class, 'slider_id'); // Asume belongsTo
    }
}
