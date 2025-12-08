<?php
namespace Screenart\Musedock\Models;
use Screenart\Musedock\Database\Model;

class Slide extends Model {
    protected static string $table = 'slider_slides';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;
    protected array $fillable = [
        'slider_id', 'image_url', 'title', 'description',
        'link_url', 'sort_order', 'is_active'
    ];
    protected array $casts = [
        'slider_id' => 'int',
        'sort_order' => 'int',
        'is_active' => 'boolean'
    ];

    // Relación inversa: Una diapositiva pertenece a un slider
    public function slider() {
        return $this->belongsTo(Slider::class, 'slider_id'); // Asume belongsTo
    }
}