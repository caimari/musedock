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

    /**
     * Visitantes únicos y cargas por grupo de rutas (una página puede tener varias
     * URL, p. ej. /precios y /p/precios: se cuentan juntas sin repetir personas).
     * Visitante único = huella anónima diaria (ip_hash), como el resto de la analítica.
     * La consulta de la URL (?utm=…, ?product=…) no cuenta: /docs/x?a=1 suma en /docs/x.
     *
     * @param array<string, array{tenant_id: ?int, paths: string[]}> $groups clave libre => rutas
     * @return array<string, array{unique: int, loads: int}>
     */
    public static function stats(array $groups): array
    {
        $byTenant = [];
        foreach ($groups as $key => $g) {
            foreach ((array) ($g['paths'] ?? []) as $path) {
                if ($path !== '') {
                    $byTenant[$g['tenant_id'] === null ? '' : (string) (int) $g['tenant_id']][] = [(string) $key, (string) $path];
                }
            }
        }

        $out = [];
        try {
            $pdo = Database::connect();
            // PostgreSQL: VALUES con tipo y split_part. MySQL/MariaDB no tiene ninguno de los dos (antes la
            // consulta fallaba en silencio y las vistas salían a 0): tabla derivada con UNION ALL y SUBSTRING_INDEX.
            $mysql = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql';
            foreach ($byTenant as $tenant => $pairs) {
                $params = array_merge(...$pairs);
                $tenantSql = $tenant === '' ? 'w.tenant_id IS NULL' : 'w.tenant_id = ?';
                if ($tenant !== '') {
                    $params[] = (int) $tenant;
                }
                if ($mysql) {
                    $values = 'SELECT ? AS k, ? AS p' . str_repeat(' UNION ALL SELECT ?, ?', count($pairs) - 1);
                    $join = "JOIN ({$values}) AS v ON SUBSTRING_INDEX(w.page_url, '?', 1) = v.p";
                } else {
                    $values = implode(',', array_fill(0, count($pairs), '(?::text, ?::text)'));
                    $join = "JOIN (VALUES {$values}) AS v(k, p) ON split_part(w.page_url, '?', 1) = v.p";
                }
                $stmt = $pdo->prepare("SELECT v.k, COUNT(DISTINCT w.ip_hash) AS u, COUNT(*) AS l
                    FROM web_analytics w {$join}
                    WHERE {$tenantSql} AND w.is_bot = false GROUP BY v.k");
                $stmt->execute($params);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                    $out[$r['k']] = ['unique' => (int) $r['u'], 'loads' => (int) $r['l']];
                }
            }
        } catch (\Throwable $e) {
            // Sin analítica disponible: se muestran ceros
        }

        return $out;
    }

    /**
     * Visitantes únicos y cargas de entradas del blog, con todas sus URL: la de su
     * prefijo actual, sin prefijo y con /blog/ (enlaces antiguos) y las traducidas (/en/…).
     *
     * @param iterable<object> $posts objetos con id y tenant_id
     * @return array<int, array{unique: int, loads: int}> id de entrada => cifras
     */
    public static function blogPosts(iterable $posts): array
    {
        $tenantOf = [];
        foreach ($posts as $post) {
            $tenantOf[(int) $post->id] = $post->tenant_id ? (int) $post->tenant_id : null;
        }
        if (!$tenantOf) {
            return [];
        }

        $groups = [];
        try {
            $ids = array_keys($tenantOf);
            $stmt = Database::connect()->prepare("SELECT reference_id, slug, prefix, locale FROM slugs WHERE module = 'blog' AND reference_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ')');
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $s) {
                $id = (int) $s['reference_id'];
                $slug = trim((string) $s['slug'], '/');
                if ($slug === '') {
                    continue;
                }
                $lang = $s['locale'] ? '/' . $s['locale'] : '';
                $prefix = trim((string) $s['prefix'], '/');
                $paths = [$lang . '/' . $slug, $lang . '/blog/' . $slug];
                if ($prefix !== '') {
                    $paths[] = $lang . '/' . $prefix . '/' . $slug;
                }
                $groups[(string) $id]['tenant_id'] = $tenantOf[$id];
                $groups[(string) $id]['paths'] = array_values(array_unique(array_merge($groups[(string) $id]['paths'] ?? [], $paths)));
            }
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach (self::stats($groups) as $id => $row) {
            $out[(int) $id] = $row;
        }

        return $out;
    }
}
