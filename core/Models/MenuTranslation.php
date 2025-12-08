<?php

namespace Screenart\Musedock\Models;

use Screenart\Musedock\Database\Model;

class MenuTranslation extends Model
{
    protected static string $table = 'site_menu_translations';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'menu_id',
        'locale',
        'title'
    ];

    protected array $casts = [
        'id' => 'int',
        'menu_id' => 'int',
        'locale' => 'string',
        'title' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function menu()
    {
        return Menu::find($this->menu_id);
    }
}
