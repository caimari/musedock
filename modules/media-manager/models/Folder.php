<?php

namespace MediaManager\Models;

use Screenart\Musedock\Database\Model;

/**
 * Modelo para gestionar carpetas en el Media Manager
 */
class Folder extends Model
{
    protected static string $table = 'media_folders';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'tenant_id', 'parent_id', 'name', 'slug', 'path', 'disk', 'description'
    ];

    protected array $casts = [
        'tenant_id' => 'nullable|integer',
        'parent_id' => 'nullable|integer',
    ];

    /**
     * Obtiene el padre de esta carpeta
     */
    public function parent()
    {
        if (!$this->parent_id) {
            return null;
        }
        return static::find($this->parent_id);
    }

    /**
     * Obtiene todas las subcarpetas de esta carpeta
     */
    public function children()
    {
        return static::query()
            ->where('parent_id', $this->id)
            ->orderBy('name', 'ASC')
            ->get();
    }

    /**
     * Obtiene todos los archivos de esta carpeta
     */
    public function media()
    {
        return Media::query()
            ->where('folder_id', $this->id)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * Obtiene la ruta completa de breadcrumbs
     * @return array
     */
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = [];
        $current = $this;

        while ($current) {
            array_unshift($breadcrumbs, [
                'id' => $current->id,
                'name' => $current->name,
                'path' => $current->path
            ]);
            $current = $current->parent();
        }

        return $breadcrumbs;
    }

    /**
     * Verifica si esta carpeta puede ser eliminada
     * @return bool
     */
    public function canDelete(): bool
    {
        // No se puede eliminar la raíz
        if ($this->path === '/' || $this->parent_id === null) {
            return false;
        }

        return true;
    }

    /**
     * Cuenta el número total de archivos en esta carpeta (recursivamente)
     * @return int
     */
    public function countMediaRecursive(): int
    {
        $count = Media::query()->where('folder_id', $this->id)->count();

        foreach ($this->children() as $child) {
            $count += $child->countMediaRecursive();
        }

        return $count;
    }

    /**
     * Genera un slug único para el nombre de carpeta
     * @param string $name
     * @param int|null $tenantId
     * @param int|null $parentId
     * @param string $disk Disco de almacenamiento
     * @return string
     */
    public static function generateSlug(string $name, ?int $tenantId = null, ?int $parentId = null, string $disk = 'media'): string
    {
        $slug = static::slugify($name);
        $originalSlug = $slug;
        $counter = 1;

        while (static::slugExists($slug, $tenantId, $parentId, null, $disk)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Convierte un texto en slug
     * @param string $text
     * @return string
     */
    private static function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        return strtolower($text);
    }

    /**
     * Verifica si un slug ya existe
     * @param string $slug
     * @param int|null $tenantId
     * @param int|null $parentId
     * @param int|null $excludeId
     * @param string $disk Disco de almacenamiento
     * @return bool
     */
    private static function slugExists(string $slug, ?int $tenantId = null, ?int $parentId = null, ?int $excludeId = null, string $disk = 'media'): bool
    {
        $query = static::query()
            ->where('slug', $slug)
            ->where('parent_id', $parentId)
            ->where('disk', $disk);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Genera la ruta completa basada en la jerarquía
     * @return string
     */
    public function generatePath(): string
    {
        if ($this->parent_id === null) {
            return '/';
        }

        $parent = $this->parent();
        if (!$parent) {
            return '/' . $this->slug . '/';
        }

        return rtrim($parent->path, '/') . '/' . $this->slug . '/';
    }

    /**
     * Obtiene o crea la carpeta raíz para un tenant y disco específico
     * @param int|null $tenantId
     * @param string $disk Disco de almacenamiento (media, local, r2, s3)
     * @return Folder
     */
    public static function getRootFolder(?int $tenantId = null, string $disk = 'media'): Folder
    {
        $query = static::query()
            ->where('path', '/')
            ->where('disk', $disk)
            ->whereNull('parent_id');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }

        $root = $query->first();

        if (!$root) {
            // Crear carpeta raíz si no existe
            $diskNames = [
                'media' => 'Local (Seguro)',
                'local' => 'Local (Legacy)',
                'r2' => 'Cloudflare R2',
                's3' => 'Amazon S3'
            ];
            $diskName = $diskNames[$disk] ?? $disk;

            $root = static::create([
                'tenant_id' => $tenantId,
                'parent_id' => null,
                'name' => 'Root',
                'slug' => 'root',
                'path' => '/',
                'disk' => $disk,
                'description' => $tenantId
                    ? "Carpeta raíz del tenant {$tenantId} en {$diskName}"
                    : "Carpeta raíz global en {$diskName}"
            ]);
            // Asegurar que $root es una instancia de Folder
            if (!($root instanceof Folder)) {
                $root = new Folder($root);
            }
        } else {
            // Asegurar que $root es una instancia de Folder (podría ser stdClass)
            if (!($root instanceof Folder)) {
                $root = new Folder($root);
            }
        }

        return $root;
    }

    /**
     * Mueve esta carpeta a otro padre
     * @param int|null $newParentId
     * @return bool
     */
    public function moveTo(?int $newParentId): bool
    {
        // No se puede mover la raíz
        if (!$this->canDelete()) {
            return false;
        }

        // No se puede mover a sí misma
        if ($newParentId === $this->id) {
            return false;
        }

        // No se puede mover a uno de sus descendientes
        if ($newParentId && $this->isDescendantOf($newParentId)) {
            return false;
        }

        $this->parent_id = $newParentId;
        $this->path = $this->generatePath();

        return $this->save();
    }

    /**
     * Verifica si esta carpeta es descendiente de otra
     * @param int $possibleAncestorId
     * @return bool
     */
    private function isDescendantOf(int $possibleAncestorId): bool
    {
        $current = $this->parent();

        while ($current) {
            if ($current->id === $possibleAncestorId) {
                return true;
            }
            $current = $current->parent();
        }

        return false;
    }
}
