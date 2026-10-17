<?php

namespace Modules\SearchConsole\Services;

use PDO;

/**
 * De dónde llegan las visitas reales según la analítica del propio CMS (tabla web_analytics), no según Google.
 * Solo personas (los robots se excluyen) y solo sitios que viven en este CMS. Son visitas que llegaron haciendo clic
 * en un enlace: no es el listado de todos los enlaces que apuntan al sitio (eso solo lo tiene Google en su web).
 */
class VisitSourcesService
{
    /**
     * @return array{total:int,direct:int,search:int,internal:int,sources:array<int,array{domain:string,type:string,visits:int,page:?string}>}|null
     *         null si no hay analítica para ese tenant
     */
    public static function forTenant(PDO $pdo, ?int $tenantId, string $from, string $to): ?array
    {
        if (!$tenantId) {
            return null;
        }
        try {
            $args = [$tenantId, $from . ' 00:00:00', $to . ' 23:59:59'];
            $stmt = $pdo->prepare('SELECT referrer_type AS t, COUNT(*) AS n FROM web_analytics WHERE tenant_id = ? AND is_bot = FALSE AND created_at >= ? AND created_at <= ? GROUP BY referrer_type');
            $stmt->execute($args);
            $types = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $types[(string) $r['t']] = (int) $r['n'];
            }
            $total = array_sum($types);
            if ($total === 0) {
                return null;
            }
            $stmt = $pdo->prepare("SELECT referrer_domain AS d, referrer_type AS t, COUNT(*) AS n FROM web_analytics
                WHERE tenant_id = ? AND is_bot = FALSE AND created_at >= ? AND created_at <= ? AND referrer_type IN ('referral', 'social') AND referrer_domain IS NOT NULL AND referrer_domain <> ''
                GROUP BY referrer_domain, referrer_type ORDER BY n DESC LIMIT 12");
            $stmt->execute($args);
            $pageStmt = $pdo->prepare("SELECT page_url FROM web_analytics WHERE tenant_id = ? AND is_bot = FALSE AND created_at >= ? AND created_at <= ? AND referrer_domain = ? GROUP BY page_url ORDER BY COUNT(*) DESC LIMIT 1");
            $sources = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $pageStmt->execute([$tenantId, $args[1], $args[2], $r['d']]);
                $page = $pageStmt->fetchColumn();
                $sources[] = ['domain' => (string) $r['d'], 'type' => (string) $r['t'], 'visits' => (int) $r['n'], 'page' => $page === false ? null : (string) $page];
            }

            return ['total' => $total, 'direct' => $types['direct'] ?? 0, 'search' => $types['search'] ?? 0, 'internal' => $types['internal'] ?? 0, 'sources' => $sources];
        } catch (\Throwable $e) {
            return null; // sin tabla de analítica (otro CMS o instalación sin ella)
        }
    }
}
