<?php

namespace Screenart\Musedock\Models;

use Screenart\Musedock\Database\Model;

class Menu extends Model
{
    protected static string $table = 'site_menus';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'tenant_id',
        'location'
    ];

    protected array $casts = [
        'id' => 'int',
        'tenant_id' => 'nullable',
        'location' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function translations()
    {
        return MenuTranslation::where('menu_id', $this->id)->get();
    }

    public function items()
    {
        return MenuItem::where('menu_id', $this->id)
                        ->orderBy('sort_order', 'ASC')
                        ->get();
    }
}

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

class MenuItem extends Model
{
    protected static string $table = 'site_menu_items';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'menu_id',
        'parent_id',
        'sort_order',
        'depth',
        'title',
        'slug',
        'link',
        'type',
        'target',
        'locale'
    ];

    protected array $casts = [
        'id' => 'int',
        'menu_id' => 'int',
        'parent_id' => 'nullable',
        'sort_order' => 'int',
        'depth' => 'int',
        'title' => 'string',
        'slug' => 'nullable',
        'link' => 'nullable',
        'type' => 'string',
        'target' => 'nullable',
        'locale' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function menu()
    {
        return Menu::find($this->menu_id);
    }

    public function parent()
    {
        return MenuItem::find($this->parent_id);
    }

    public function children()
    {
        return MenuItem::where('parent_id', $this->id)
                        ->orderBy('sort_order', 'ASC')
                        ->get();
    }
}
