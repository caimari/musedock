<?php

namespace Screenart\Musedock\Models;

use Screenart\Musedock\Database\Model;
use Screenart\Musedock\Database;

/**
 * Modelo para Page Revisions
 * Sistema de versiones completo para páginas
 *
 * @property int $id
 * @property int $page_id
 * @property int|null $tenant_id
 * @property int|null $user_id
 * @property string|null $user_name
 * @property string $user_type
 * @property string $revision_type
 * @property string $title
 * @property string|null $slug
 * @property string $content
 * @property string|null $excerpt
 * @property string|null $slider_image
 * @property string|null $meta_data (JSON)
 * @property string|null $status
 * @property string $created_at
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $changes_summary
 */
class PageRevision extends Model
{
    protected static string $table = 'page_revisions';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = false; // Usamos created_at manual

    protected array $fillable = [
        'page_id',
        'tenant_id',
        'user_id',
        'user_name',
        'user_type',
        'revision_type',
        'title',
        'slug',
        'content',
        'excerpt',
        'slider_image',
        'meta_data',
        'status',
        'created_at',
        'ip_address',
        'user_agent',
        'changes_summary',
    ];

    /**
     * 🔒 SECURITY: Crear una revisión desde una página
     *
     * @param Page|object $page La página de la cual crear la revisión
     * @param string $type Tipo de revisión
     * @param string|null $changesSummary Resumen de cambios
     * @return PageRevision|false
     */
    public static function createFromPage($page, string $type = 'manual', ?string $changesSummary = null)
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
            'seo_title' => $page->seo_title ?? null,
            'seo_description' => $page->seo_description ?? null,
            'seo_keywords' => $page->seo_keywords ?? null,
            'visibility' => $page->visibility ?? 'public',
            'layout' => $page->layout ?? 'default',
            'is_homepage' => $page->is_homepage ?? 0,
            'parent_id' => $page->parent_id ?? null,
            'menu_order' => $page->menu_order ?? 0,
            'canonical_url' => $page->canonical_url ?? null,
            'robots_directive' => $page->robots_directive ?? null,
        ];

        try {
            $revision = self::create([
                'page_id' => (int)$page->id,
                'tenant_id' => $page->tenant_id !== null ? (int)$page->tenant_id : null,
                'user_id' => $userId,
                'user_name' => substr($userName, 0, 100), // 🔒 SECURITY: Limitar longitud
                'user_type' => $userType,
                'revision_type' => $type,
                'title' => substr($page->title, 0, 255), // 🔒 SECURITY: Limitar longitud
                'slug' => $page->slug ?? null,
                'content' => $page->content ?? '',
                'excerpt' => $page->excerpt ?? null,
                'slider_image' => $page->slider_image ?? null,
                'meta_data' => json_encode($metaData, JSON_UNESCAPED_UNICODE),
                'status' => $page->status ?? 'draft',
                'created_at' => date('Y-m-d H:i:s'),
                'ip_address' => self::getClientIP(),
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500), // 🔒 SECURITY: Limitar
                'changes_summary' => $changesSummary ? substr($changesSummary, 0, 255) : null,
            ]);

            // Incrementar contador de revisiones en la página
            if ($revision) {
                self::incrementRevisionCount($page->id);
            }

            return $revision;

        } catch (\Exception $e) {
            error_log("Error al crear revisión de página: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 🔒 SECURITY: Obtener IP del cliente de forma segura
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
    private static function incrementRevisionCount(int $pageId): void
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("UPDATE pages SET revision_count = revision_count + 1 WHERE id = ?");
            $stmt->execute([$pageId]);
        } catch (\Exception $e) {
            error_log("Error al incrementar revision_count: " . $e->getMessage());
        }
    }

    /**
     * Obtener todas las revisiones de una página
     *
     * @param int $pageId
     * @param int $limit
     * @return array
     */
    public static function getPageRevisions(int $pageId, int $limit = 50): array
    {
        // 🔒 SECURITY: Validar que limit sea razonable
        $limit = min(max(1, (int)$limit), 200); // Entre 1 y 200

        return self::where('page_id', $pageId)
                   ->orderBy('created_at', 'DESC')
                   ->limit($limit)
                   ->get();
    }

    /**
     * Obtener revisión por ID verificando permisos de tenant
     *
     * @param int $revisionId
     * @param int|null $tenantId
     * @return PageRevision|null
     */
    public static function findWithTenant(int $revisionId, ?int $tenantId): ?PageRevision
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
     * 🔒 SECURITY: Restaurar una página a esta revisión
     *
     * @return bool
     */
    public function restore(): bool
    {
        try {
            $pdo = Database::connect();

            // 1. Obtener página actual
            $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ? LIMIT 1");
            $stmt->execute([$this->page_id]);
            $currentPage = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$currentPage) {
                error_log("Página #{$this->page_id} no encontrada para restaurar");
                return false;
            }

            // 2. Crear backup de la versión actual ANTES de restaurar
            $backupPage = (object)$currentPage;
            self::createFromPage($backupPage, 'manual', 'Backup automático antes de restaurar revisión #' . $this->id);

            // 3. Decodificar metadata de forma segura
            $meta = null;
            if ($this->meta_data) {
                $meta = json_decode($this->meta_data, true);
            }

            // 4. 🔒 SECURITY: Actualizar página con prepared statement
            $stmt = $pdo->prepare("
                UPDATE pages SET
                    title = ?,
                    content = ?,
                    excerpt = ?,
                    slider_image = ?,
                    seo_title = ?,
                    seo_description = ?,
                    seo_keywords = ?,
                    visibility = ?,
                    layout = ?,
                    canonical_url = ?,
                    robots_directive = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $this->title,
                $this->content,
                $this->excerpt,
                $this->slider_image,
                $meta['seo_title'] ?? null,
                $meta['seo_description'] ?? null,
                $meta['seo_keywords'] ?? null,
                $meta['visibility'] ?? 'public',
                $meta['layout'] ?? 'default',
                $meta['canonical_url'] ?? null,
                $meta['robots_directive'] ?? null,
                $this->page_id,
            ]);

            // 5. Crear revisión de "restauración"
            $restoredPage = (object)array_merge($currentPage, [
                'title' => $this->title,
                'content' => $this->content,
                'excerpt' => $this->excerpt,
                'slider_image' => $this->slider_image,
            ]);
            self::createFromPage($restoredPage, 'restored', 'Restaurado desde revisión #' . $this->id . ' del ' . $this->created_at);

            return true;

        } catch (\Exception $e) {
            error_log("Error al restaurar revisión #{$this->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Comparar esta revisión con otra
     *
     * @param PageRevision $other
     * @return array
     */
    public function diffWith(PageRevision $other): array
    {
        return [
            'title' => $this->title !== $other->title,
            'content' => $this->content !== $other->content,
            'excerpt' => $this->excerpt !== $other->excerpt,
            'slider_image' => $this->slider_image !== $other->slider_image,
            'status' => $this->status !== $other->status,
            'content_length_diff' => strlen($this->content) - strlen($other->content),
        ];
    }

    /**
     * Limpiar revisiones antiguas (mantener solo las últimas N)
     *
     * @param int $pageId
     * @param int $keepLast Cuántas mantener (default: 50)
     * @return int Cantidad eliminada
     */
    public static function cleanOldRevisions(int $pageId, int $keepLast = 50): int
    {
        try {
            $pdo = Database::connect();

            // Solo eliminar autosaves antiguos
            $stmt = $pdo->prepare("
                DELETE FROM page_revisions
                WHERE page_id = ?
                AND revision_type = 'autosave'
                AND id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM page_revisions
                        WHERE page_id = ? AND revision_type = 'autosave'
                        ORDER BY created_at DESC
                        LIMIT ?
                    ) tmp
                )
            ");

            $stmt->execute([$pageId, $pageId, $keepLast]);
            return $stmt->rowCount();

        } catch (\Exception $e) {
            error_log("Error al limpiar revisiones antiguas: " . $e->getMessage());
            return 0;
        }
    }
}
