<?php
namespace Blog\Models;

use Screenart\Musedock\Database\Model;
use Screenart\Musedock\Database;

class BlogTag extends Model
{
    protected static string $table = 'blog_tags';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'color',
        'post_count',
    ];

    protected array $casts = [
        'id'         => 'int',
        'tenant_id'  => 'nullable',
        'post_count' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Obtiene los posts de esta etiqueta.
     */
    public function posts()
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
                SELECT bp.*
                FROM blog_posts bp
                INNER JOIN blog_post_tags pt ON bp.id = pt.post_id
                WHERE pt.tag_id = ?
                ORDER BY bp.published_at DESC
            ");
            $stmt->execute([$this->id]);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return array_map(function($row) {
                $post = new BlogPost();
                $post->fill($row);
                return $post;
            }, $results);
        } catch (\Exception $e) {
            error_log("Error al obtener posts para etiqueta ID {$this->id}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene la URL pública de la etiqueta.
     */
    public function getPublicUrl(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? env('APP_URL', 'localhost');
        return "https://{$host}/blog/tag/{$this->slug}";
    }

    /**
     * Buscar etiqueta por slug.
     */
    public static function findBySlug(string $slug, ?int $tenantId = null): ?BlogTag
    {
        $query = self::where('slug', $slug);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }

        return $query->first();
    }

    /**
     * Crea o encuentra una etiqueta por nombre (útil para auto-crear etiquetas).
     */
    public static function findOrCreate(string $name, ?int $tenantId = null): BlogTag
    {
        $slug = self::generateSlug($name);
        $tag = self::findBySlug($slug, $tenantId);

        if (!$tag) {
            $tag = new self();
            $tag->tenant_id = $tenantId;
            $tag->name = $name;
            $tag->slug = $slug;
            $tag->save();
        }

        return $tag;
    }

    /**
     * Genera un slug a partir de un nombre.
     */
    private static function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }
}
