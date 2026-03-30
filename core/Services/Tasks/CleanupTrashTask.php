<?php

namespace Screenart\Musedock\Services\Tasks;

use Screenart\Musedock\Database;

/**
 * Tarea: Limpieza automática de papelera
 *
 * Elimina permanentemente items que han estado en papelera
 * más tiempo del configurado (por defecto 30 días).
 *
 * Se ejecuta:
 * - Pseudo-cron: Cada X horas (configurable en .env)
 * - Real cron: Según configuración de crontab
 *
 * Afecta a:
 * - Pages (pages_trash)
 * - Blog Posts (blog_posts_trash)
 *
 * @package Screenart\Musedock\Services\Tasks
 */
class CleanupTrashTask
{
    /**
     * Ejecutar limpieza de papelera
     *
     * @return array Resumen de lo eliminado
     */
    public static function run(): array
    {
        // Verificar si está habilitada la limpieza automática
        $enabled = filter_var(getenv('TRASH_AUTO_DELETE_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN);

        if (!$enabled) {
            return [
                'enabled' => false,
                'message' => 'Limpieza de papelera desactivada en .env'
            ];
        }

        // Obtener días de retención
        $retentionDays = (int)(getenv('TRASH_RETENTION_DAYS') ?: 30);

        try {
            $pdo = Database::connect();

            $results = [
                'enabled' => true,
                'retention_days' => $retentionDays,
                'pages' => self::cleanupPages($pdo, $retentionDays),
                'blog_posts' => self::cleanupBlogPosts($pdo, $retentionDays),
            ];

            $totalDeleted = $results['pages']['deleted'] + $results['blog_posts']['deleted'];

            error_log("CleanupTrashTask: Eliminados {$totalDeleted} items (Pages: {$results['pages']['deleted']}, BlogPosts: {$results['blog_posts']['deleted']})");

            return $results;

        } catch (\Exception $e) {
            error_log("CleanupTrashTask error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Limpiar páginas de la papelera
     *
     * @param \PDO $pdo
     * @param int $retentionDays
     * @return array
     */
    private static function cleanupPages(\PDO $pdo, int $retentionDays): array
    {
        // Obtener páginas que deben eliminarse
        $stmt = $pdo->prepare("
            SELECT pt.page_id, p.title
            FROM pages_trash pt
            INNER JOIN pages p ON p.id = pt.page_id
            WHERE pt.scheduled_permanent_delete <= NOW()
            OR pt.deleted_at <= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$retentionDays]);
        $pagesToDelete = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $deleted = 0;

        foreach ($pagesToDelete as $pageData) {
            $pageId = $pageData['page_id'];

            try {
                // Eliminar revisiones
                $stmt = $pdo->prepare("DELETE FROM page_revisions WHERE page_id = ?");
                $stmt->execute([$pageId]);

                // Eliminar de papelera
                $stmt = $pdo->prepare("DELETE FROM pages_trash WHERE page_id = ?");
                $stmt->execute([$pageId]);

                // Eliminar traducciones
                $stmt = $pdo->prepare("DELETE FROM page_translations WHERE page_id = ?");
                $stmt->execute([$pageId]);

                // Eliminar metadatos
                $stmt = $pdo->prepare("DELETE FROM page_meta WHERE page_id = ?");
                $stmt->execute([$pageId]);

                // Eliminar slugs
                $stmt = $pdo->prepare("DELETE FROM slugs WHERE module = 'pages' AND reference_id = ?");
                $stmt->execute([$pageId]);

                // Eliminar página
                $stmt = $pdo->prepare("DELETE FROM pages WHERE id = ?");
                $stmt->execute([$pageId]);

                $deleted++;

                error_log("CleanupTrashTask: Página #{$pageId} ('{$pageData['title']}') eliminada permanentemente");

            } catch (\Exception $e) {
                error_log("CleanupTrashTask: Error eliminando página #{$pageId}: " . $e->getMessage());
            }
        }

        return [
            'found' => count($pagesToDelete),
            'deleted' => $deleted
        ];
    }

    /**
     * Limpiar blog posts de la papelera
     *
     * @param \PDO $pdo
     * @param int $retentionDays
     * @return array
     */
    private static function cleanupBlogPosts(\PDO $pdo, int $retentionDays): array
    {
        // Obtener posts que deben eliminarse
        $stmt = $pdo->prepare("
            SELECT pt.post_id, p.title
            FROM blog_posts_trash pt
            INNER JOIN blog_posts p ON p.id = pt.post_id
            WHERE pt.scheduled_permanent_delete <= NOW()
            OR pt.deleted_at <= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$retentionDays]);
        $postsToDelete = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $deleted = 0;

        foreach ($postsToDelete as $postData) {
            $postId = $postData['post_id'];

            try {
                // Eliminar revisiones
                $stmt = $pdo->prepare("DELETE FROM blog_post_revisions WHERE post_id = ?");
                $stmt->execute([$postId]);

                // Eliminar de papelera
                $stmt = $pdo->prepare("DELETE FROM blog_posts_trash WHERE post_id = ?");
                $stmt->execute([$postId]);

                // Eliminar relaciones con categorías
                $stmt = $pdo->prepare("DELETE FROM blog_post_categories WHERE post_id = ?");
                $stmt->execute([$postId]);

                // Eliminar relaciones con tags
                $stmt = $pdo->prepare("DELETE FROM blog_post_tags WHERE post_id = ?");
                $stmt->execute([$postId]);

                // Eliminar traducciones
                $stmt = $pdo->prepare("DELETE FROM blog_post_translations WHERE post_id = ?");
                $stmt->execute([$postId]);

                // Eliminar slugs
                $stmt = $pdo->prepare("DELETE FROM slugs WHERE module = 'blog' AND reference_id = ?");
                $stmt->execute([$postId]);

                // Eliminar post
                $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
                $stmt->execute([$postId]);

                $deleted++;

                error_log("CleanupTrashTask: Post #{$postId} ('{$postData['title']}') eliminado permanentemente");

            } catch (\Exception $e) {
                error_log("CleanupTrashTask: Error eliminando post #{$postId}: " . $e->getMessage());
            }
        }

        return [
            'found' => count($postsToDelete),
            'deleted' => $deleted
        ];
    }
}
