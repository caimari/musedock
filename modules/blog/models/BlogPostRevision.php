<?php

namespace Blog\Models;

use Screenart\Musedock\Database\Model;
use Screenart\Musedock\Database;
use Blog\Models\BlogPost;

/**
 * Modelo para Blog Post Revisions
 * Sistema de versiones completo para blog posts
 *
 * @property int $id
 * @property int $post_id
 * @property int|null $tenant_id
 * @property int|null $user_id
 * @property string|null $user_name
 * @property string $user_type
 * @property string $revision_type
 * @property string $title
 * @property string|null $slug
 * @property string $content
 * @property string|null $excerpt
 * @property string|null $featured_image
 * @property string|null $meta_data (JSON)
 * @property string|null $status
 * @property string $created_at
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $changes_summary
 */
class BlogPostRevision extends Model
{
    protected static string $table = 'blog_post_revisions';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = false; // Usamos created_at manual

    protected array $fillable = [
        'post_id',
        'tenant_id',
        'user_id',
        'user_name',
        'user_type',
        'revision_type',
        'title',
        'slug',
        'content',
        'excerpt',
        'featured_image',
        'meta_data',
        'status',
        'created_at',
        'ip_address',
        'user_agent',
        'changes_summary',
    ];

    /**
     * 🔒 SECURITY: Crear una revisión desde un blog post
     *
     * @param BlogPost|object $post El post del cual crear la revisión
     * @param string $type Tipo de revisión
     * @param string|null $changesSummary Resumen de cambios
     * @return BlogPostRevision|false
     */
    public static function createFromPost($post, string $type = 'manual', ?string $changesSummary = null)
    {
        // 🔒 SECURITY: Obtener usuario actual de sesión de forma segura
        $user = $_SESSION['admin'] ?? $_SESSION['user'] ?? $_SESSION['super_admin'] ?? null;
        $userId = null;
        $userName = 'Sistema';
        $userType = 'admin';

        if ($user) {
            $userId = (int)($user['id'] ?? 0);
            $userName = $user['name'] ?? $user['email'] ?? 'Usuario';

            // 🔒 SECURITY: Validar user_type contra whitelist
            $allowedUserTypes = ['user', 'admin', 'superadmin'];
            if (isset($_SESSION['super_admin'])) {
                $userType = 'superadmin';
            } elseif (isset($_SESSION['admin'])) {
                $userType = 'admin';
            } elseif (isset($_SESSION['user'])) {
                $userType = 'user';
            }
        }

        // 🔒 SECURITY: Validar revision_type contra whitelist
        $allowedTypes = ['autosave', 'manual', 'published', 'restored', 'scheduled', 'initial'];
        $type = in_array($type, $allowedTypes, true) ? $type : 'manual';

        // Construir metadata JSON de forma segura
        $metaData = [
            'seo_title' => $post->seo_title ?? null,
            'seo_description' => $post->seo_description ?? null,
            'seo_keywords' => $post->seo_keywords ?? null,
            'visibility' => $post->visibility ?? 'public',
            'featured' => $post->featured ?? 0,
            'allow_comments' => $post->allow_comments ?? 1,
            'canonical_url' => $post->canonical_url ?? null,
            'robots_directive' => $post->robots_directive ?? null,
        ];

        try {
            $revision = self::create([
                'post_id' => (int)$post->id,
                'tenant_id' => $post->tenant_id !== null ? (int)$post->tenant_id : null,
                'user_id' => $userId,
                'user_name' => substr($userName, 0, 100), // 🔒 SECURITY: Limitar longitud
                'user_type' => $userType,
                'revision_type' => $type,
                'title' => substr($post->title, 0, 255), // 🔒 SECURITY: Limitar longitud
                'slug' => $post->slug ?? null,
                'content' => $post->content ?? '',
                'excerpt' => $post->excerpt ?? null,
                'featured_image' => $post->featured_image ?? null,
                'meta_data' => json_encode($metaData, JSON_UNESCAPED_UNICODE),
                'status' => $post->status ?? 'draft',
                'created_at' => date('Y-m-d H:i:s'),
                'ip_address' => self::getClientIP(),
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500), // 🔒 SECURITY: Limitar
                'changes_summary' => $changesSummary ? substr($changesSummary, 0, 255) : null,
            ]);

            // Incrementar contador de revisiones en el post
            if ($revision) {
                self::incrementRevisionCount($post->id);
            }

            return $revision;

        } catch (\Exception $e) {
            error_log("Error al crear revisión de blog post: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 🔒 SECURITY: Obtener IP del cliente de forma segura
     * Considera proxies y load balancers
     */
    private static function getClientIP(): ?string
    {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED',
                   'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];

        foreach ($ipKeys as $key) {
            if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
                return substr($_SERVER[$key], 0, 45); // IPv6 max length
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    /**
     * Incrementar contador de revisiones
     */
    private static function incrementRevisionCount(int $postId): void
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("UPDATE blog_posts SET revision_count = revision_count + 1 WHERE id = ?");
            $stmt->execute([$postId]);
        } catch (\Exception $e) {
            error_log("Error al incrementar revision_count: " . $e->getMessage());
        }
    }

    /**
     * Obtener todas las revisiones de un post
     *
     * @param int $postId
     * @param int $limit
     * @return array
     */
    public static function getPostRevisions(int $postId, int $limit = 50): array
    {
        // 🔒 SECURITY: Validar que limit sea razonable
        $limit = min(max(1, (int)$limit), 200); // Entre 1 y 200

        return self::where('post_id', $postId)
                   ->orderBy('created_at', 'DESC')
                   ->limit($limit)
                   ->get();
    }

    /**
     * Obtener revisión por ID verificando permisos de tenant
     *
     * @param int $revisionId
     * @param int|null $tenantId
     * @return BlogPostRevision|null
     */
    public static function findWithTenant(int $revisionId, ?int $tenantId): ?BlogPostRevision
    {
        $query = self::where('id', $revisionId);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }

        return $query->first();
    }

    /**
     * 🔒 SECURITY: Restaurar un post a esta revisión
     * Crea backup automático antes de restaurar
     *
     * @return bool
     */
    public function restore(): bool
    {
        try {
            $pdo = Database::connect();

            // 1. Obtener post actual
            $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ? LIMIT 1");
            $stmt->execute([$this->post_id]);
            $currentPost = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$currentPost) {
                error_log("Post #{$this->post_id} no encontrado para restaurar");
                return false;
            }

            // 2. Crear backup de la versión actual ANTES de restaurar
            $backupPost = (object)$currentPost;
            self::createFromPost($backupPost, 'manual', 'Backup automático antes de restaurar revisión #' . $this->id);

            // 3. Decodificar metadata de forma segura
            $meta = null;
            if ($this->meta_data) {
                $meta = json_decode($this->meta_data, true);
            }

            // 4. 🔒 SECURITY: Actualizar post con prepared statement
            $stmt = $pdo->prepare("
                UPDATE blog_posts SET
                    title = ?,
                    content = ?,
                    excerpt = ?,
                    featured_image = ?,
                    seo_title = ?,
                    seo_description = ?,
                    seo_keywords = ?,
                    visibility = ?,
                    featured = ?,
                    allow_comments = ?,
                    canonical_url = ?,
                    robots_directive = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $this->title,
                $this->content,
                $this->excerpt,
                $this->featured_image,
                $meta['seo_title'] ?? null,
                $meta['seo_description'] ?? null,
                $meta['seo_keywords'] ?? null,
                $meta['visibility'] ?? 'public',
                $meta['featured'] ?? 0,
                $meta['allow_comments'] ?? 1,
                $meta['canonical_url'] ?? null,
                $meta['robots_directive'] ?? null,
                $this->post_id,
            ]);

            // 5. Crear revisión de "restauración"
            $restoredPost = (object)array_merge($currentPost, [
                'title' => $this->title,
                'content' => $this->content,
                'excerpt' => $this->excerpt,
                'featured_image' => $this->featured_image,
            ]);
            self::createFromPost($restoredPost, 'restored', 'Restaurado desde revisión #' . $this->id . ' del ' . $this->created_at);

            return true;

        } catch (\Exception $e) {
            error_log("Error al restaurar revisión #{$this->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Comparar esta revisión con otra
     *
     * @param BlogPostRevision $other
     * @return array
     */
    public function diffWith(BlogPostRevision $other): array
    {
        return [
            'title' => $this->title !== $other->title,
            'content' => $this->content !== $other->content,
            'excerpt' => $this->excerpt !== $other->excerpt,
            'featured_image' => $this->featured_image !== $other->featured_image,
            'status' => $this->status !== $other->status,
            'content_length_diff' => strlen($this->content) - strlen($other->content),
        ];
    }

    /**
     * Limpiar revisiones antiguas (mantener solo las últimas N)
     * Solo elimina autosaves, nunca revisiones manual/published/restored
     *
     * @param int $postId
     * @param int $keepLast Cuántas mantener (default: 50)
     * @return int Cantidad eliminada
     */
    public static function cleanOldRevisions(int $postId, int $keepLast = 50): int
    {
        try {
            $pdo = Database::connect();

            // Solo eliminar autosaves antiguos, nunca revisiones importantes
            $stmt = $pdo->prepare("
                DELETE FROM blog_post_revisions
                WHERE post_id = ?
                AND revision_type = 'autosave'
                AND id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM blog_post_revisions
                        WHERE post_id = ? AND revision_type = 'autosave'
                        ORDER BY created_at DESC
                        LIMIT ?
                    ) tmp
                )
            ");

            $stmt->execute([$postId, $postId, $keepLast]);
            return $stmt->rowCount();

        } catch (\Exception $e) {
            error_log("Error al limpiar revisiones antiguas: " . $e->getMessage());
            return 0;
        }
    }
}
