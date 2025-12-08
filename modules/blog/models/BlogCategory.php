<?php
namespace Blog\Models;

use Screenart\Musedock\Database\Model;
use Screenart\Musedock\Database;

class BlogCategory extends Model
{
    protected static string $table = 'blog_categories';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'tenant_id',
        'parent_id',
        'name',
        'slug',
        'description',
        'image',
        'color',
        'order',
        'post_count',
        'seo_title',
        'seo_description',
        'seo_keywords',
    ];

    protected array $casts = [
        'id'         => 'int',
        'tenant_id'  => 'nullable',
        'parent_id'  => 'nullable',
        'order'      => 'int',
        'post_count' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Obtiene la categoría padre.
     */
    public function parent(): ?BlogCategory
    {
        if (!$this->parent_id) {
            return null;
        }
        return self::find($this->parent_id);
    }

    /**
     * Obtiene las categorías hijas.
     */
    public function children()
    {
        return self::where('parent_id', $this->id)->orderBy('order', 'ASC')->get();
    }

    /**
     * Obtiene los posts de esta categoría.
     */
    public function posts()
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
                SELECT bp.*
                FROM blog_posts bp
                INNER JOIN blog_post_categories pc ON bp.id = pc.post_id
                WHERE pc.category_id = ?
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
            error_log("Error al obtener posts para categoría ID {$this->id}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene la URL pública de la categoría.
     */
    public function getPublicUrl(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? env('APP_URL', 'localhost');
        return "https://{$host}/blog/category/{$this->slug}";
    }

    /**
     * Buscar categoría por slug.
     */
    public static function findBySlug(string $slug, ?int $tenantId = null): ?BlogCategory
    {
        $query = self::where('slug', $slug);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }

        return $query->first();
    }
}
