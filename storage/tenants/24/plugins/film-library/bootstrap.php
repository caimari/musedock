<?php

/**
 * Film Library — Bootstrap
 * Plugin exclusivo para tenant iberofilms.com (tenant_id: 24)
 *
 * Note: Admin sidebar menus are registered in tenant_menus (DB)
 * automatically when the plugin is activated via /admin/plugins.
 * See plugin.json > admin_menu for the menu definition.
 */

if (defined('BOOTSTRAP_FILM_LIBRARY')) return;
define('BOOTSTRAP_FILM_LIBRARY', true);

/**
 * Register sitemap URLs for films, genres, directors, actors.
 * This hook is called by SitemapController when generating sitemap.xml.
 */
if (function_exists('add_filter')) {
    add_filter('musedock_sitemap_urls', function (array $urls, ?int $tenantId, string $siteUrl, \PDO $pdo) {

        // Only add film URLs if the plugin is active for this tenant
        if (!function_exists('film_is_active') || !film_is_active()) {
            return $urls;
        }

        $tenantWhere = $tenantId !== null ? "AND tenant_id = ?" : "";
        $params = $tenantId !== null ? [$tenantId] : [];

        // ── Films catalog index ──
        $urls[] = [
            'loc'        => $siteUrl . '/films',
            'lastmod'    => date('Y-m-d'),
            'changefreq' => 'daily',
            'priority'   => '0.9',
        ];

        // ── Published films ──
        try {
            $stmt = $pdo->prepare("
                SELECT slug, title, poster_path, updated_at, created_at
                FROM films
                WHERE status = 'published' {$tenantWhere}
                ORDER BY updated_at DESC
            ");
            $stmt->execute($params);

            while ($film = $stmt->fetch(\PDO::FETCH_OBJ)) {
                $entry = [
                    'loc'        => $siteUrl . '/films/' . $film->slug,
                    'lastmod'    => date('Y-m-d', strtotime($film->updated_at ?? $film->created_at ?? 'now')),
                    'changefreq' => 'weekly',
                    'priority'   => '0.8',
                ];
                if (!empty($film->poster_path)) {
                    $imgUrl = str_starts_with($film->poster_path, 'http')
                        ? $film->poster_path
                        : 'https://image.tmdb.org/t/p/w500' . $film->poster_path;
                    $entry['images'] = [['loc' => $imgUrl, 'title' => $film->title ?? '']];
                }
                $urls[] = $entry;
            }
        } catch (\Exception $e) {
            error_log("Film Library sitemap: films error: " . $e->getMessage());
        }

        // ── Film genres ──
        try {
            $stmt = $pdo->prepare("
                SELECT g.slug, g.name, g.updated_at
                FROM film_genres g
                WHERE g.film_count > 0 {$tenantWhere}
                ORDER BY g.name ASC
            ");
            $stmt->execute($params);

            while ($genre = $stmt->fetch(\PDO::FETCH_OBJ)) {
                $urls[] = [
                    'loc'        => $siteUrl . '/films/genero/' . $genre->slug,
                    'lastmod'    => date('Y-m-d', strtotime($genre->updated_at ?? 'now')),
                    'changefreq' => 'weekly',
                    'priority'   => '0.6',
                ];
            }
        } catch (\Exception $e) {
            error_log("Film Library sitemap: genres error: " . $e->getMessage());
        }

        // ── Directors (unique) ──
        try {
            $stmt = $pdo->prepare("
                SELECT DISTINCT director_slug, director, MAX(updated_at) as last_update
                FROM films
                WHERE status = 'published' AND director_slug IS NOT NULL AND director_slug != '' {$tenantWhere}
                GROUP BY director_slug, director
                ORDER BY director ASC
            ");
            $stmt->execute($params);

            while ($dir = $stmt->fetch(\PDO::FETCH_OBJ)) {
                $urls[] = [
                    'loc'        => $siteUrl . '/films/director/' . $dir->director_slug,
                    'lastmod'    => date('Y-m-d', strtotime($dir->last_update ?? 'now')),
                    'changefreq' => 'monthly',
                    'priority'   => '0.5',
                ];
            }
        } catch (\Exception $e) {
            error_log("Film Library sitemap: directors error: " . $e->getMessage());
        }

        // ── Years ──
        try {
            $stmt = $pdo->prepare("
                SELECT DISTINCT year, MAX(updated_at) as last_update
                FROM films
                WHERE status = 'published' AND year IS NOT NULL {$tenantWhere}
                GROUP BY year
                ORDER BY year DESC
            ");
            $stmt->execute($params);

            while ($yr = $stmt->fetch(\PDO::FETCH_OBJ)) {
                $urls[] = [
                    'loc'        => $siteUrl . '/films/year/' . $yr->year,
                    'lastmod'    => date('Y-m-d', strtotime($yr->last_update ?? 'now')),
                    'changefreq' => 'monthly',
                    'priority'   => '0.4',
                ];
            }
        } catch (\Exception $e) {
            error_log("Film Library sitemap: years error: " . $e->getMessage());
        }

        // ── Actors/People (cached in film_people) ──
        try {
            $stmt = $pdo->prepare("
                SELECT tmdb_id, slug, name, profile_path, updated_at
                FROM film_people
                WHERE tenant_id = ? AND slug != ''
                ORDER BY name ASC
            ");
            $stmt->execute([$tenantId ?? 0]);

            while ($person = $stmt->fetch(\PDO::FETCH_OBJ)) {
                $actorSlug = $person->tmdb_id . '-' . $person->slug;
                $entry = [
                    'loc'        => $siteUrl . '/films/actor/' . $actorSlug,
                    'lastmod'    => date('Y-m-d', strtotime($person->updated_at ?? 'now')),
                    'changefreq' => 'monthly',
                    'priority'   => '0.5',
                ];
                if (!empty($person->profile_path)) {
                    $entry['images'] = [[
                        'loc'   => 'https://image.tmdb.org/t/p/w500' . $person->profile_path,
                        'title' => $person->name,
                    ]];
                }
                $urls[] = $entry;
            }
        } catch (\Exception $e) {
            error_log("Film Library sitemap: people error: " . $e->getMessage());
        }

        return $urls;
    }, 10);
}

/**
 * Home page carousel: show latest films after blog posts.
 * Only renders if show_home_carousel is enabled in plugin settings.
 */
if (function_exists('add_action')) {
    add_action('home_after_posts', function () {
        if (!function_exists('film_setting') || !function_exists('film_is_active')) return;
        if (!film_is_active()) return;
        if (!film_setting('show_home_carousel', 0)) return;

        $tenantId = function_exists('tenant_id') ? tenant_id() : null;
        $count = (int)film_setting('home_carousel_count', 12);
        $title = film_setting('home_carousel_title', 'Cartelera');

        try {
            $pdo = \Screenart\Musedock\Database::connect();
            $tenantWhere = $tenantId !== null ? "AND tenant_id = ?" : "";
            $params = $tenantId !== null ? [$tenantId] : [];

            $stmt = $pdo->prepare("
                SELECT * FROM films
                WHERE status = 'published' {$tenantWhere}
                ORDER BY created_at DESC
                LIMIT {$count}
            ");
            $stmt->execute($params);
            $films = array_map(fn($r) => new \FilmLibrary\Models\Film($r), $stmt->fetchAll(\PDO::FETCH_ASSOC));

            if (empty($films)) return;

            // Render inline — avoid View::renderTheme which interferes with Blade
            $homeFilms = $films;
            $homeCarouselTitle = $title;
            $viewPath = dirname(__DIR__, 5) . '/themes/default/views/films/partials/_home_carousel.php';
            if (file_exists($viewPath)) {
                include $viewPath;
            }
        } catch (\Exception $e) {
            error_log("Film Library home carousel error: " . $e->getMessage());
        }
    }, 10);
}

/**
 * Invalidate sitemap cache when a film is imported/updated/deleted.
 * Called from goToFilm() auto-import and admin CRUD.
 */
if (!function_exists('film_invalidate_sitemap')) {
    function film_invalidate_sitemap(): void
    {
        if (class_exists('Blog\Controllers\Frontend\SitemapController')) {
            $tenantId = function_exists('tenant_id') ? tenant_id() : null;
            \Blog\Controllers\Frontend\SitemapController::invalidateCache($tenantId);
        }
    }
}
