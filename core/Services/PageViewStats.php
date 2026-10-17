<?php

namespace Screenart\Musedock\Services;

use Screenart\Musedock\Database;

/**
 * Visitas de páginas a partir de la analítica propia (web_analytics).
 *
 * Cuenta solo personas (is_bot = false) y funciona con la caché HTML, porque la
 * analítica se registra desde el navegador: las visitas servidas desde caché
 * también cuentan (un contador en PHP no las vería).
 */
class PageViewStats
{
    /**
     * @param array<int, array{tenant_id: ?int, path: string}> $items
     * @return array<string, int> clave "tenant|ruta" (tenant vacío = sitio principal) => visitas
     */
    public static function counts(array $items): array
    {
        $byTenant = [];
        foreach ($items as $item) {
            $path = (string) ($item['path'] ?? '');
            if ($path === '') {
                continue;
            }
            $byTenant[$item['tenant_id'] === null ? '' : (string) (int) $item['tenant_id']][$path] = true;
        }

        $out = [];
        try {
            $pdo = Database::connect();
            foreach ($byTenant as $tenant => $paths) {
                $paths = array_keys($paths);
                $placeholders = implode(',', array_fill(0, count($paths), '?'));
                $tenantSql = $tenant === '' ? 'tenant_id IS NULL' : 'tenant_id = ?';
                $params = $tenant === '' ? $paths : array_merge([(int) $tenant], $paths);
                $stmt = $pdo->prepare("SELECT page_url, COUNT(*) FROM web_analytics WHERE {$tenantSql} AND is_bot = false AND page_url IN ({$placeholders}) GROUP BY page_url");
                $stmt->execute($params);
                foreach ($stmt->fetchAll(\PDO::FETCH_KEY_PAIR) as $url => $count) {
                    $out[$tenant . '|' . $url] = (int) $count;
                }
            }
        } catch (\Throwable $e) {
            // Sin analítica disponible: la columna muestra 0
        }

        return $out;
    }
}
