<?php
namespace Blog\Models;

use Screenart\Musedock\Database\Model;
use Blog\Models\BlogPostTranslation;
use Blog\Models\BlogCategory;
use Blog\Models\BlogTag;
use Screenart\Musedock\Models\SuperAdmin;
use Screenart\Musedock\Models\Admin;
use Screenart\Musedock\Models\User;
use Screenart\Musedock\Models\Tenant;
use Screenart\Musedock\Database;
use Carbon\Carbon;

class BlogPost extends Model
{
    protected static string $table = 'blog_posts';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    /**
     * Atributos que se pueden asignar masivamente.
     */
    protected array $fillable = [
        'tenant_id',
        'user_id',
        'user_type',
        'title',
        'slug',
        'excerpt',
        'content',
        'featured_image',
        'hide_featured_image',
        'hide_title',
        'status',
        'visibility',
        'password',
        'published_at',
        'base_locale',
        'allow_comments',
        'comment_count',
        'view_count',
        'featured',
        'template',
        // --- Campos SEO ---
        'seo_title',
        'seo_description',
        'seo_keywords',
        'seo_image',
        'canonical_url',
        'robots_directive',
        'twitter_title',
        'twitter_description',
        'twitter_image',
        // --- Campos de hero ---
        'show_hero',
        'hero_image',
        'hero_title',
        'hero_content',
    ];

    /**
     * Conversión de tipos para los atributos.
     */
    protected array $casts = [
        'id'                  => 'int',
        'user_id'             => 'int',
        'tenant_id'           => 'nullable',
        'status'              => 'string',
        'visibility'          => 'string',
        'published_at'        => 'datetime',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
        'allow_comments'      => 'boolean',
        'comment_count'       => 'int',
        'view_count'          => 'int',
        'featured'            => 'boolean',
        'hide_featured_image' => 'boolean',
        'hide_title'          => 'boolean',
        'show_hero'           => 'boolean',
    ];

    /**
     * Relación con tenant (opcional).
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Devuelve el autor en función del tipo de usuario.
     */
    public function getAuthor()
    {
        return match ($this->user_type) {
            'superadmin' => SuperAdmin::find($this->user_id),
            'admin'      => Admin::find($this->user_id),
            'user'       => User::find($this->user_id),
            default      => null,
        };
    }

    /**
     * Obtiene todas las traducciones disponibles del post.
     */
    public function translations()
    {
        return BlogPostTranslation::where('post_id', $this->id)->get();
    }

    /**
     * Devuelve la traducción para un idioma específico.
     */
    public function translation(string $locale): ?BlogPostTranslation
    {
        return BlogPostTranslation::where('post_id', $this->id)
            ->where('locale', $locale)
            ->first();
    }

    /**
     * Verifica si existe una traducción para un idioma dado.
     */
    public function hasTranslation(string $locale): bool
    {
        return BlogPostTranslation::where('post_id', $this->id)
            ->where('locale', $locale)
            ->exists();
    }

    /**
     * Devuelve el contenido traducido si existe, o el original.
     */
    public function translatedOrDefault(string $locale): array
    {
        $t = $this->translation($locale);
        return [
            'title'   => $t?->title ?? $this->title,
            'excerpt' => $t?->excerpt ?? $this->excerpt,
            'content' => $t?->content ?? $this->content,
        ];
    }

    /**
     * Obtiene las categorías asociadas a este post.
     */
    public function categories()
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
                SELECT c.*
                FROM blog_categories c
                INNER JOIN blog_post_categories pc ON c.id = pc.category_id
                WHERE pc.post_id = ?
                ORDER BY c.name
            ");
            $stmt->execute([$this->id]);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return array_map(function($row) {
                $category = new BlogCategory();
                $category->fill($row);
                return $category;
            }, $results);
        } catch (\Exception $e) {
            error_log("Error al obtener categorías para post ID {$this->id}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene las etiquetas asociadas a este post.
     */
    public function tags()
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
                SELECT t.*
                FROM blog_tags t
                INNER JOIN blog_post_tags pt ON t.id = pt.tag_id
                WHERE pt.post_id = ?
                ORDER BY t.name
            ");
            $stmt->execute([$this->id]);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return array_map(function($row) {
                $tag = new BlogTag();
                $tag->fill($row);
                return $tag;
            }, $results);
        } catch (\Exception $e) {
            error_log("Error al obtener etiquetas para post ID {$this->id}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Asigna categorías al post (muchos a muchos).
     */
    public function syncCategories(array $categoryIds): bool
    {
        try {
            $pdo = Database::connect();

            // Eliminar categorías existentes
            $deleteStmt = $pdo->prepare("DELETE FROM blog_post_categories WHERE post_id = ?");
            $deleteStmt->execute([$this->id]);

            // Insertar nuevas categorías
            if (!empty($categoryIds)) {
                $insertStmt = $pdo->prepare("INSERT INTO blog_post_categories (post_id, category_id) VALUES (?, ?)");
                foreach ($categoryIds as $categoryId) {
                    $insertStmt->execute([$this->id, $categoryId]);

                    // Actualizar contador de posts en la categoría
                    $this->updateCategoryCount($categoryId);
                }
            }

            return true;
        } catch (\Exception $e) {
            error_log("Error al sincronizar categorías para post ID {$this->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Asigna etiquetas al post (muchos a muchos).
     */
    public function syncTags(array $tagIds): bool
    {
        try {
            $pdo = Database::connect();

            // Eliminar etiquetas existentes
            $deleteStmt = $pdo->prepare("DELETE FROM blog_post_tags WHERE post_id = ?");
            $deleteStmt->execute([$this->id]);

            // Insertar nuevas etiquetas
            if (!empty($tagIds)) {
                $insertStmt = $pdo->prepare("INSERT INTO blog_post_tags (post_id, tag_id) VALUES (?, ?)");
                foreach ($tagIds as $tagId) {
                    $insertStmt->execute([$this->id, $tagId]);

                    // Actualizar contador de posts en la etiqueta
                    $this->updateTagCount($tagId);
                }
            }

            return true;
        } catch (\Exception $e) {
            error_log("Error al sincronizar etiquetas para post ID {$this->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualiza el contador de posts de una categoría.
     */
    private function updateCategoryCount(int $categoryId): void
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
                UPDATE blog_categories
                SET post_count = (
                    SELECT COUNT(*)
                    FROM blog_post_categories
                    WHERE category_id = ?
                )
                WHERE id = ?
            ");
            $stmt->execute([$categoryId, $categoryId]);
        } catch (\Exception $e) {
            error_log("Error al actualizar contador de categoría ID {$categoryId}: " . $e->getMessage());
        }
    }

    /**
     * Actualiza el contador de posts de una etiqueta.
     */
    private function updateTagCount(int $tagId): void
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
                UPDATE blog_tags
                SET post_count = (
                    SELECT COUNT(*)
                    FROM blog_post_tags
                    WHERE tag_id = ?
                )
                WHERE id = ?
            ");
            $stmt->execute([$tagId, $tagId]);
        } catch (\Exception $e) {
            error_log("Error al actualizar contador de etiqueta ID {$tagId}: " . $e->getMessage());
        }
    }

    /**
     * Obtiene el slug asociado a este post.
     */
    public function getSlug()
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT * FROM slugs WHERE module = 'blog' AND reference_id = ?");
            $stmt->execute([$this->id]);
            return $stmt->fetch(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            error_log("Error al obtener slug para post ID {$this->id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene el prefix asociado al slug de este post.
     */
    public function getPrefix(): string
    {
        $slug = $this->getSlug();
        return $slug ? ($slug->prefix ?? 'blog') : 'blog';
    }

    /**
     * Obtiene la URL pública del post.
     */
    public function getPublicUrl(): string
    {
        $slug = $this->getSlug();
        if (!$slug) return '#';

        $host = $_SERVER['HTTP_HOST'] ?? env('APP_URL', 'localhost');
        $prefix = $slug->prefix ?? 'blog';

        return "https://{$host}/{$prefix}/{$slug->slug}";
    }

    /**
     * Actualiza el slug asociado al post.
     */
    public function updateSlug(string $slug, ?string $prefix = null): bool
    {
        try {
            $pdo = Database::connect();

            // Verificar si ya existe un slug para este post
            $checkStmt = $pdo->prepare("SELECT id FROM slugs WHERE module = 'blog' AND reference_id = ?");
            $checkStmt->execute([$this->id]);
            $existing = $checkStmt->fetch(\PDO::FETCH_ASSOC);

            if ($existing) {
                // Actualizar el slug existente
                $updateStmt = $pdo->prepare("UPDATE slugs SET slug = ?, prefix = ? WHERE module = 'blog' AND reference_id = ?");
                return $updateStmt->execute([$slug, $prefix ?? 'blog', $this->id]);
            } else {
                // Crear un nuevo registro de slug
                $insertStmt = $pdo->prepare("INSERT INTO slugs (module, reference_id, slug, tenant_id, prefix) VALUES (?, ?, ?, ?, ?)");
                return $insertStmt->execute(['blog', $this->id, $slug, $this->tenant_id, $prefix ?? 'blog']);
            }
        } catch (\Exception $e) {
            error_log("Error al actualizar slug para post ID {$this->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Incrementa el contador de vistas del post.
     */
    public function incrementViewCount(): bool
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("UPDATE blog_posts SET view_count = view_count + 1 WHERE id = ?");
            $result = $stmt->execute([$this->id]);

            if ($result) {
                $this->view_count = ($this->view_count ?? 0) + 1;
            }

            return $result;
        } catch (\Exception $e) {
            error_log("Error al incrementar contador de vistas para post ID {$this->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sobrescribe el método de guardado para manejar correctamente tenant_id y fechas.
     */
    public function save(): bool
    {
        // Si tenant_id es una cadena vacía o null, lo forzamos a NULL explícitamente
        if (isset($this->attributes['tenant_id']) && ($this->attributes['tenant_id'] === '' || $this->attributes['tenant_id'] === null)) {
            $this->attributes['tenant_id'] = null;
        }

        // Manejar fechas de creación y actualización manualmente
        if (!isset($this->attributes['id']) || empty($this->attributes['id'])) {
            if (!isset($this->attributes['created_at']) || empty($this->attributes['created_at'])) {
                $this->attributes['created_at'] = date('Y-m-d H:i:s');
            }
        }

        // Siempre actualizar la fecha de última modificación
        $this->attributes['updated_at'] = date('Y-m-d H:i:s');

        // Si el status es published pero no hay fecha de publicación, establecerla
        if (isset($this->attributes['status']) && $this->attributes['status'] === 'published') {
            if (empty($this->attributes['published_at'])) {
                $this->attributes['published_at'] = date('Y-m-d H:i:s');
            }
        }

        // Asegurar que visibility tenga un valor predeterminado si no existe
        if (!isset($this->attributes['visibility']) || !in_array($this->attributes['visibility'], ['public', 'private', 'password'])) {
            $this->attributes['visibility'] = 'public';
        }

        return parent::save();
    }

    /**
     * Método para formatear fechas para mostrar en la vista usando Carbon.
     */
    public function getFormattedDate(string $field, ?string $format = null): string
    {
        $value = $this->{$field} ?? ($this->attributes[$field] ?? null);

        if (empty($value)) {
            return 'Desconocido';
        }

        // Determinar el formato a usar
        if ($format === null) {
            try {
                $dateFormat = setting('date_format', 'd/m/Y');
                $timeFormat = setting('time_format', 'H:i');
                $format = $dateFormat . ' ' . $timeFormat;
            } catch (\Throwable $e) {
                $format = 'd/m/Y H:i';
                error_log("Error obteniendo formato de fecha desde settings: " . $e->getMessage());
            }
        }

        // Si ya es un objeto DateTime/Carbon
        if ($value instanceof \DateTimeInterface) {
            try {
                return Carbon::instance($value)->format($format);
            } catch (\Exception $e) {
                error_log("Error formateando objeto DateTime {$field} para post ID {$this->id}: " . $e->getMessage());
                return 'Error Formato Obj';
            }
        }

        // Si es un string
        if (is_string($value)) {
            try {
                return Carbon::parse($value)->format($format);
            } catch (\Exception $e) {
                error_log("Error parseando string de fecha {$field} ('{$value}') para post ID {$this->id}: " . $e->getMessage());
                return 'Desconocido (Inválido)';
            }
        }

        error_log("Valor de fecha {$field} para post ID {$this->id} no es objeto ni string parseable.");
        return 'Desconocido';
    }

    /**
     * Buscar post por slug.
     */
    public static function findBySlug(string $slug, ?int $tenantId = null): ?BlogPost
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
                SELECT bp.*
                FROM blog_posts bp
                INNER JOIN slugs s ON s.reference_id = bp.id
                WHERE s.module = 'blog'
                AND s.slug = ?
                AND (bp.tenant_id = ? OR bp.tenant_id IS NULL)
                LIMIT 1
            ");
            $stmt->execute([$slug, $tenantId]);
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($data) {
                $post = new self();
                $post->fill($data);
                return $post;
            }

            return null;
        } catch (\Exception $e) {
            error_log("Error al buscar post por slug '{$slug}': " . $e->getMessage());
            return null;
        }
    }
}
