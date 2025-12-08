<?php

namespace Screenart\Musedock\Services\Tasks;

use Screenart\Musedock\Database;

/**
 * Tarea: Limpieza automática de revisiones
 *
 * Implementa una política mixta de retención para evitar
 * que la tabla de revisiones crezca indefinidamente.
 *
 * POLÍTICA MIXTA (configurable en .env):
 * 1. Últimas N revisiones: SIEMPRE se guardan (ej: 5)
 * 2. Revisiones antiguas: 1 por mes (ej: últimos 12 meses)
 * 3. Revisiones muy antiguas: 1 por año (ej: últimos 3 años)
 * 4. Resto: Se eliminan
 *
 * Ejemplo con valores por defecto:
 * - Últimas 5 revisiones: se guardan TODAS
 * - Del último año: 1 revisión por mes (12 revisiones)
 * - De hace 2-3 años: 1 revisión por año (2 revisiones)
 * - De hace más de 3 años: se eliminan TODAS
 *
 * Afecta a:
 * - page_revisions
 * - blog_post_revisions
 *
 * @package Screenart\Musedock\Services\Tasks
 */
class CleanupRevisionsTask
{
    /**
     * Ejecutar limpieza de revisiones
     *
     * @return array Resumen de lo eliminado
     */
    public static function run(): array
    {
        // Verificar si está habilitada la limpieza
        $enabled = filter_var(getenv('REVISION_CLEANUP_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN);

        if (!$enabled) {
            return [
                'enabled' => false,
                'message' => 'Limpieza de revisiones desactivada en .env'
            ];
        }

        // Obtener configuración de retención
        $keepRecent = (int)(getenv('REVISION_KEEP_RECENT') ?: 5);
        $keepMonthly = (int)(getenv('REVISION_KEEP_MONTHLY') ?: 12);
        $keepYearly = (int)(getenv('REVISION_KEEP_YEARLY') ?: 3);

        try {
            $pdo = Database::connect();

            $results = [
                'enabled' => true,
                'policy' => [
                    'keep_recent' => $keepRecent,
                    'keep_monthly' => $keepMonthly,
                    'keep_yearly' => $keepYearly
                ],
                'pages' => self::cleanupPageRevisions($pdo, $keepRecent, $keepMonthly, $keepYearly),
                'blog_posts' => self::cleanupBlogPostRevisions($pdo, $keepRecent, $keepMonthly, $keepYearly),
            ];

            $totalDeleted = $results['pages']['deleted'] + $results['blog_posts']['deleted'];

            error_log("CleanupRevisionsTask: Eliminadas {$totalDeleted} revisiones (Pages: {$results['pages']['deleted']}, BlogPosts: {$results['blog_posts']['deleted']})");

            return $results;

        } catch (\Exception $e) {
            error_log("CleanupRevisionsTask error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Limpiar revisiones de páginas
     *
     * @param \PDO $pdo
     * @param int $keepRecent
     * @param int $keepMonthly
     * @param int $keepYearly
     * @return array
     */
    private static function cleanupPageRevisions(\PDO $pdo, int $keepRecent, int $keepMonthly, int $keepYearly): array
    {
        // Obtener todas las páginas que tienen revisiones
        $stmt = $pdo->query("
            SELECT DISTINCT page_id
            FROM page_revisions
        ");
        $pageIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $totalDeleted = 0;
        $totalKept = 0;

        foreach ($pageIds as $pageId) {
            $result = self::cleanupRevisionsForItem($pdo, 'page_revisions', 'page_id', $pageId, $keepRecent, $keepMonthly, $keepYearly);
            $totalDeleted += $result['deleted'];
            $totalKept += $result['kept'];
        }

        return [
            'pages_processed' => count($pageIds),
            'deleted' => $totalDeleted,
            'kept' => $totalKept
        ];
    }

    /**
     * Limpiar revisiones de blog posts
     *
     * @param \PDO $pdo
     * @param int $keepRecent
     * @param int $keepMonthly
     * @param int $keepYearly
     * @return array
     */
    private static function cleanupBlogPostRevisions(\PDO $pdo, int $keepRecent, int $keepMonthly, int $keepYearly): array
    {
        // Obtener todos los posts que tienen revisiones
        $stmt = $pdo->query("
            SELECT DISTINCT post_id
            FROM blog_post_revisions
        ");
        $postIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $totalDeleted = 0;
        $totalKept = 0;

        foreach ($postIds as $postId) {
            $result = self::cleanupRevisionsForItem($pdo, 'blog_post_revisions', 'post_id', $postId, $keepRecent, $keepMonthly, $keepYearly);
            $totalDeleted += $result['deleted'];
            $totalKept += $result['kept'];
        }

        return [
            'posts_processed' => count($postIds),
            'deleted' => $totalDeleted,
            'kept' => $totalKept
        ];
    }

    /**
     * Limpiar revisiones para un item específico (página o post)
     *
     * Implementa la política mixta de retención
     *
     * @param \PDO $pdo
     * @param string $table Nombre de la tabla
     * @param string $column Nombre de la columna ID
     * @param int $itemId ID del item
     * @param int $keepRecent
     * @param int $keepMonthly
     * @param int $keepYearly
     * @return array
     */
    private static function cleanupRevisionsForItem(\PDO $pdo, string $table, string $column, int $itemId, int $keepRecent, int $keepMonthly, int $keepYearly): array
    {
        // Obtener todas las revisiones ordenadas por fecha descendente
        $stmt = $pdo->prepare("
            SELECT id, created_at,
                   DATE_FORMAT(created_at, '%Y-%m') as `year_month`,
                   DATE_FORMAT(created_at, '%Y') as `rev_year`
            FROM `{$table}`
            WHERE `{$column}` = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$itemId]);
        $revisions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $totalRevisions = count($revisions);

        if ($totalRevisions <= $keepRecent) {
            // Si hay menos revisiones que el mínimo a mantener, no eliminar nada
            return ['deleted' => 0, 'kept' => $totalRevisions];
        }

        $revisionsToKeep = [];

        // 1. SIEMPRE guardar las últimas N
        for ($i = 0; $i < $keepRecent && $i < $totalRevisions; $i++) {
            $revisionsToKeep[$revisions[$i]['id']] = [
                'reason' => 'recent',
                'created_at' => $revisions[$i]['created_at']
            ];
        }

        // 2. Guardar 1 por mes (últimos X meses)
        $monthlyRevisions = [];
        foreach ($revisions as $rev) {
            // Saltar si ya está en las recientes
            if (isset($revisionsToKeep[$rev['id']])) {
                continue;
            }

            // Verificar si está dentro del periodo mensual
            $createdAt = strtotime($rev['created_at']);
            $monthsAgo = (strtotime('now') - $createdAt) / (30 * 24 * 60 * 60);

            if ($monthsAgo <= $keepMonthly) {
                $yearMonth = $rev['year_month'];

                // Guardar solo la primera (más reciente) de cada mes
                if (!isset($monthlyRevisions[$yearMonth])) {
                    $monthlyRevisions[$yearMonth] = $rev['id'];
                    $revisionsToKeep[$rev['id']] = [
                        'reason' => 'monthly',
                        'month' => $yearMonth,
                        'created_at' => $rev['created_at']
                    ];
                }
            }
        }

        // 3. Guardar 1 por año (últimos X años)
        $yearlyRevisions = [];
        foreach ($revisions as $rev) {
            // Saltar si ya está guardada
            if (isset($revisionsToKeep[$rev['id']])) {
                continue;
            }

            // Verificar si está dentro del periodo anual
            $createdAt = strtotime($rev['created_at']);
            $yearsAgo = (strtotime('now') - $createdAt) / (365 * 24 * 60 * 60);

            if ($yearsAgo <= $keepYearly) {
                $revYear = $rev['rev_year'];

                // Guardar solo la primera (más reciente) de cada año
                if (!isset($yearlyRevisions[$revYear])) {
                    $yearlyRevisions[$revYear] = $rev['id'];
                    $revisionsToKeep[$rev['id']] = [
                        'reason' => 'yearly',
                        'year' => $revYear,
                        'created_at' => $rev['created_at']
                    ];
                }
            }
        }

        // 4. Eliminar las que no están en la lista de mantener
        $revisionsToDelete = [];
        foreach ($revisions as $rev) {
            if (!isset($revisionsToKeep[$rev['id']])) {
                $revisionsToDelete[] = $rev['id'];
            }
        }

        // Ejecutar eliminación
        $deleted = 0;
        if (!empty($revisionsToDelete)) {
            $placeholders = implode(',', array_fill(0, count($revisionsToDelete), '?'));
            $deleteStmt = $pdo->prepare("DELETE FROM {$table} WHERE id IN ({$placeholders})");
            $deleteStmt->execute($revisionsToDelete);
            $deleted = $deleteStmt->rowCount();
        }

        return [
            'deleted' => $deleted,
            'kept' => count($revisionsToKeep)
        ];
    }
}
