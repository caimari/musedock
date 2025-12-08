<?php
namespace Blog\Models;

use Screenart\Musedock\Database\Model;

class BlogPostTranslation extends Model
{
    protected static string $table = 'blog_post_translations';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'post_id',
        'tenant_id',
        'locale',
        'title',
        'excerpt',
        'content',
        'seo_title',
        'seo_description',
        'seo_keywords',
    ];

    protected array $casts = [
        'id'         => 'int',
        'post_id'    => 'int',
        'tenant_id'  => 'nullable',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con el post.
     */
    public function post(): ?BlogPost
    {
        return BlogPost::find($this->post_id);
    }

    /**
     * Buscar traducción por post y locale.
     */
    public static function findByPostAndLocale(int $postId, string $locale): ?BlogPostTranslation
    {
        return self::where('post_id', $postId)
            ->where('locale', $locale)
            ->first();
    }
}
