<?php

namespace Modules\SearchConsole\Services;

use PDO;

/**
 * Relaciona las URL que ve Google con el contenido del CMS (artículos, páginas, documentación) de ese tenant,
 * para mostrar su título y un enlace a «Editar» en el informe. Solo lee; nunca mezcla tenants.
 */
class ContentMapService
{
    private const TYPES = ['post' => 'Artículo', 'docs' => 'Documentación', 'brief' => 'Nota'];

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param string[] $urls URL completas de la propiedad
     * @return array<string,array{type:string,title:string,edit:string,status:string,seo_title:string,seo_description:string}> url => contenido
     */
    public function resolve(?int $tenantId, string $host, array $urls, string $adminPath = 'admin', bool $relative = false): array
    {
        $parsed = [];
        foreach (array_unique($urls) as $url) {
            $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
            $segs = $path === '' ? [] : array_map('rawurldecode', explode('/', $path));
            if ($segs && preg_match('/^[a-z]{2}$/', $segs[0]) && count($segs) > 1) {
                array_shift($segs); // /en/docs/x → docs/x
            }
            $parsed[$url] = $segs;
        }
        $slugs = array_values(array_unique(array_filter(array_map(fn ($s) => $s ? end($s) : null, $parsed))));
        $bySlug = [];
        if ($slugs) {
            $in = implode(',', array_fill(0, count($slugs), '?'));
            $stmt = $this->pdo->prepare("SELECT module, reference_id, slug, COALESCE(prefix, '') AS prefix FROM slugs WHERE slug IN ({$in}) AND " . ($tenantId === null ? 'tenant_id IS NULL' : 'tenant_id = ?'));
            $stmt->execute($tenantId === null ? $slugs : array_merge($slugs, [$tenantId]));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $bySlug[$r['slug']][] = $r;
            }
        }
        $postIds = $pageIds = [];
        $hit = [];
        foreach ($parsed as $url => $segs) {
            if (!$segs) {
                continue;
            }
            $slug = end($segs);
            $prefix = implode('/', array_slice($segs, 0, -1));
            foreach ($bySlug[$slug] ?? [] as $r) {
                if ($r['prefix'] === $prefix && in_array($r['module'], ['blog', 'pages'], true)) {
                    $hit[$url] = $r;
                    $r['module'] === 'blog' ? $postIds[] = (int) $r['reference_id'] : $pageIds[] = (int) $r['reference_id'];
                    break;
                }
            }
        }
        // Páginas que no tienen fila en slugs: se busca por su slug (con prefijo vacío o «p»)
        $loose = [];
        foreach ($parsed as $url => $segs) {
            if ($segs && !isset($hit[$url]) && in_array(implode('/', array_slice($segs, 0, -1)), ['', 'p'], true)) {
                $loose[$url] = end($segs);
            }
        }
        if ($loose) {
            $in = implode(',', array_fill(0, count($loose), '?'));
            $stmt = $this->pdo->prepare("SELECT id, slug FROM pages WHERE slug IN ({$in}) AND " . ($tenantId === null ? 'tenant_id IS NULL' : 'tenant_id = ?'));
            $stmt->execute($tenantId === null ? array_values($loose) : array_merge(array_values($loose), [$tenantId]));
            $pg = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $pg[$r['slug']] = (int) $r['id'];
            }
            foreach ($loose as $url => $slug) {
                if (isset($pg[$slug])) {
                    $hit[$url] = ['module' => 'pages', 'reference_id' => $pg[$slug]];
                    $pageIds[] = $pg[$slug];
                }
            }
        }
        $posts = $this->fetch('blog_posts', 'id, title, post_type, status, seo_title, seo_description', $postIds, $tenantId);
        $pages = $this->fetch('pages', 'id, title, status, seo_title, seo_description', $pageIds, $tenantId);
        $home = null;
        if (array_filter($parsed, fn ($s) => !$s)) {
            $stmt = $this->pdo->prepare('SELECT id, title, status, seo_title, seo_description FROM pages WHERE is_homepage = 1 AND ' . ($tenantId === null ? 'tenant_id IS NULL' : 'tenant_id = ?') . ' LIMIT 1');
            $stmt->execute($tenantId === null ? [] : [$tenantId]);
            $home = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $base = ($tenantId === null || $relative) ? '' : 'https://' . $host;
        $admin = '/' . trim($adminPath, '/');
        $out = [];
        foreach ($parsed as $url => $segs) {
            if (!$segs && $home) {
                $out[$url] = ['type' => 'Página de inicio', 'title' => (string) $home['title'], 'status' => (string) $home['status'], 'seo_title' => (string) ($home['seo_title'] ?? ''), 'seo_description' => (string) ($home['seo_description'] ?? ''),
                    'edit' => $base . ($tenantId === null ? '/musedock/pages/' : $admin . '/pages/') . (int) $home['id'] . '/edit'];
            } elseif (isset($hit[$url])) {
                $r = $hit[$url];
                if ($r['module'] === 'blog' && isset($posts[(int) $r['reference_id']])) {
                    $p = $posts[(int) $r['reference_id']];
                    $out[$url] = ['type' => self::TYPES[$p['post_type']] ?? 'Artículo', 'title' => (string) $p['title'], 'status' => (string) $p['status'], 'seo_title' => (string) ($p['seo_title'] ?? ''), 'seo_description' => (string) ($p['seo_description'] ?? ''),
                        'edit' => $base . ($tenantId === null ? '/musedock/blog/posts/' : $admin . '/blog/posts/') . (int) $p['id'] . '/edit'];
                } elseif ($r['module'] === 'pages' && isset($pages[(int) $r['reference_id']])) {
                    $p = $pages[(int) $r['reference_id']];
                    $out[$url] = ['type' => 'Página', 'title' => (string) $p['title'], 'status' => (string) $p['status'], 'seo_title' => (string) ($p['seo_title'] ?? ''), 'seo_description' => (string) ($p['seo_description'] ?? ''),
                        'edit' => $base . ($tenantId === null ? '/musedock/pages/' : $admin . '/pages/') . (int) $p['id'] . '/edit'];
                }
            }
        }

        return $out;
    }

    /** @return array<int,array> id => fila, solo del tenant pedido */
    private function fetch(string $table, string $cols, array $ids, ?int $tenantId): array
    {
        $ids = array_values(array_unique($ids));
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT {$cols} FROM {$table} WHERE id IN ({$in}) AND " . ($tenantId === null ? 'tenant_id IS NULL' : 'tenant_id = ?'));
        $stmt->execute($tenantId === null ? $ids : array_merge($ids, [$tenantId]));
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id']] = $r;
        }

        return $out;
    }
}
