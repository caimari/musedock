<?php
namespace Blog\Models;

use Screenart\Musedock\Database\Model;

class BlogPostMeta extends Model
{
    protected static string $table = 'blog_post_meta';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = false;

    protected array $fillable = [
        'post_id',
        'meta_key',
        'meta_value',
    ];

    protected array $casts = [
        'id'      => 'int',
        'post_id' => 'int',
    ];

    /**
     * Relación con el post.
     */
    public function post(): ?BlogPost
    {
        return BlogPost::find($this->post_id);
    }

    /**
     * Obtener un meta valor por post y clave.
     */
    public static function getMeta(int $postId, string $key, $default = null)
    {
        $meta = self::where('post_id', $postId)
            ->where('meta_key', $key)
            ->first();

        return $meta ? $meta->meta_value : $default;
    }

    /**
     * Actualizar o insertar un meta valor.
     */
    public static function updateOrInsertMeta(int $postId, string $key, $value): bool
    {
        try {
            $meta = self::where('post_id', $postId)
                ->where('meta_key', $key)
                ->first();

            if ($meta) {
                $meta->meta_value = $value;
                return $meta->save();
            } else {
                $newMeta = new self();
                $newMeta->post_id = $postId;
                $newMeta->meta_key = $key;
                $newMeta->meta_value = $value;
                return $newMeta->save();
            }
        } catch (\Exception $e) {
            error_log("Error al actualizar/insertar meta para post ID {$postId}, key {$key}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar un meta valor.
     */
    public static function deleteMeta(int $postId, string $key): bool
    {
        try {
            $meta = self::where('post_id', $postId)
                ->where('meta_key', $key)
                ->first();

            if ($meta) {
                return $meta->delete();
            }

            return true;
        } catch (\Exception $e) {
            error_log("Error al eliminar meta para post ID {$postId}, key {$key}: " . $e->getMessage());
            return false;
        }
    }
}
