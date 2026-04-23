<?php

namespace FilmLibrary\Controllers\Frontend;

use FilmLibrary\Models\Film;
use FilmLibrary\Models\FilmGenre;
use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Services\TenantManager;

class FilmCatalogController
{
    private function getTenantId(): ?int
    {
        return TenantManager::currentTenantId();
    }

    private static function countryCodeToNames(string $code): array
    {
        $map = [
            'ES'=>['Spain','España'],'MX'=>['Mexico','México'],'AR'=>['Argentina'],
            'CO'=>['Colombia'],'CL'=>['Chile'],'PE'=>['Peru','Perú'],
            'UY'=>['Uruguay'],'PY'=>['Paraguay'],'BO'=>['Bolivia'],
            'EC'=>['Ecuador'],'VE'=>['Venezuela'],'CU'=>['Cuba'],
            'DO'=>['Dominican Republic','República Dominicana'],'PR'=>['Puerto Rico'],
            'GT'=>['Guatemala'],'HN'=>['Honduras'],'SV'=>['El Salvador'],
            'NI'=>['Nicaragua'],'CR'=>['Costa Rica'],'PA'=>['Panama','Panamá'],
            'BR'=>['Brazil','Brasil'],'PT'=>['Portugal'],
        ];
        return $map[strtoupper($code)] ?? [$code];
    }

    /**
     * Film catalog — /films
     */
    public function index()
    {
        $tenantId = $this->getTenantId();
        $perPage = (int)(film_setting('films_per_page', 12));
        $currentPage = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($currentPage - 1) * $perPage;

        $query = Film::query()
            ->where('status', 'published');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        // Filters
        $yearFilter = $_GET['year'] ?? '';
        $genreFilter = $_GET['genre'] ?? '';
        $countryFilter = $_GET['country'] ?? [];
        if (is_string($countryFilter)) $countryFilter = $countryFilter ? [$countryFilter] : [];
        $search = trim($_GET['q'] ?? '');

        if (!empty($yearFilter)) {
            $query->where('year', (int)$yearFilter);
        }
        if (!empty($search)) {
            $s = "%{$search}%";
            $query->whereRaw("(title ILIKE ? OR director ILIKE ? OR original_title ILIKE ?)", [$s, $s, $s]);
        }
        if (!empty($countryFilter)) {
            $countryConds = [];
            $countryParams = [];
            foreach ($countryFilter as $cc) {
                $names = self::countryCodeToNames(trim($cc));
                foreach ($names as $name) {
                    $countryConds[] = "production_countries ILIKE ?";
                    $countryParams[] = "%" . $name . "%";
                }
            }
            $query->whereRaw("(" . implode(" OR ", $countryConds) . ")", $countryParams);
        }

        // Genre filter via subquery
        if (!empty($genreFilter)) {
            $genre = FilmGenre::query()->where('slug', $genreFilter);
            if ($tenantId !== null) {
                $genre->where('tenant_id', $tenantId);
            }
            $genre = $genre->first();
            if ($genre) {
                $query->whereRaw("id IN (SELECT film_id FROM film_genre_pivot WHERE genre_id = ?)", [$genre->id]);
            }
        }

        $query->orderBy('featured', 'DESC')->orderBy('year', 'DESC')->orderBy('title', 'ASC');

        $totalCount = $query->count();
        $totalPages = $perPage > 0 ? (int)ceil($totalCount / $perPage) : 1;
        $films = $query->limit($perPage)->offset($offset)->get();

        // Batch load genres
        $filmGenres = $this->batchLoadGenres($films);

        // Load filter data
        $pdo = Database::connect();
        $tenantWhere = $tenantId !== null ? "AND tenant_id = ?" : "";
        $params = $tenantId !== null ? [$tenantId] : [];

        $yearsStmt = $pdo->prepare("SELECT DISTINCT year FROM films WHERE status = 'published' AND year IS NOT NULL {$tenantWhere} ORDER BY year DESC");
        $yearsStmt->execute($params);
        $years = array_column($yearsStmt->fetchAll(\PDO::FETCH_ASSOC), 'year');

        $genres = FilmGenre::query();
        if ($tenantId !== null) {
            $genres->where('tenant_id', $tenantId);
        }
        $genres = $genres->orderBy('name', 'ASC')->get();

        $siteName = function_exists('site_setting') ? site_setting('site_name', 'IberoFilms') : 'IberoFilms';

        View::addGlobalData([
            '__page_title'       => 'Catálogo de Películas | ' . $siteName,
            '__page_description' => 'Descubre películas del cine iberoamericano. Fichas editoriales con contexto, director, reparto y dónde verlas.',
        ]);

        echo View::renderTheme('films/index', [
            'title'       => 'Catálogo de Películas',
            'films'       => $films,
            'filmGenres'  => $filmGenres,
            'pagination'  => [
                'current_page' => $currentPage,
                'total_pages'  => $totalPages,
                'total_posts'  => $totalCount,
                'per_page'     => $perPage,
            ],
            'years'       => $years,
            'genres'      => $genres,
            'yearFilter'     => $yearFilter,
            'genreFilter'    => $genreFilter,
            'countryFilter'  => $countryFilter,
            'search'         => $search,
            'apiEnabled'     => (bool)film_setting('api_enabled', 1),
        ]);
    }

    /**
     * API: paginated catalog — /films/api/catalog?page=2&limit=12
     * Returns JSON with published films for infinite scroll / carousel.
     */
    public function apiCatalog()
    {
        $tenantId = $this->getTenantId();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 12)));
        $offset = ($page - 1) * $limit;

        $pdo = Database::connect();
        $tenantWhere = $tenantId !== null ? "AND tenant_id = ?" : "";
        $params = $tenantId !== null ? [$tenantId] : [];

        $countStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM films WHERE status = 'published' {$tenantWhere}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetch(\PDO::FETCH_ASSOC)['cnt'];

        $stmt = $pdo->prepare("SELECT id, title, slug, year, director, poster_path, tmdb_rating FROM films WHERE status = 'published' {$tenantWhere} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute($params);
        $films = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Add poster URL
        foreach ($films as &$f) {
            $f['poster_url'] = function_exists('film_poster_url') ? film_poster_url($f['poster_path'], 'w342') : '';
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'films'   => $films,
            'page'    => $page,
            'total'   => $total,
            'totalPages' => $limit > 0 ? (int)ceil($total / $limit) : 1,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Public TMDb search — /films/tmdb-search?q=...&country[]=MX&year=2024
     * Supports text search and/or country/year filter via Discover.
     * Returns JSON with TMDb results, marking already-imported films.
     */
    public function tmdbSearch()
    {
        header('Cache-Control: no-cache, no-store, must-revalidate');

        // Check if API is enabled
        if (!film_setting('api_enabled', 1)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'API desactivada']);
            return;
        }

        $tenantId = $this->getTenantId();
        $query = trim($_GET['q'] ?? '');
        $countriesRaw = $_GET['country'] ?? [];
        if (is_string($countriesRaw)) {
            $countries = $countriesRaw ? array_filter(explode(',', $countriesRaw)) : [];
        } else {
            $countries = (array)$countriesRaw;
        }
        $year = (int)($_GET['year'] ?? 0);

        $hasQuery = strlen($query) >= 2;
        $hasFilters = !empty($countries) || $year > 0;

        if (!$hasQuery && !$hasFilters) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Introduce un título o selecciona filtros']);
            return;
        }

        $apiKey = film_setting('tmdb_api_key', '');
        if (empty($apiKey)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'No API key']);
            return;
        }

        $tmdb = new \FilmLibrary\Services\TmdbService($apiKey, film_setting('tmdb_language', 'es-ES'));

        $iberoCountries = ['ES','MX','AR','CO','CL','PE','UY','PY','BO','EC','VE','CU','DO','PR','GT','HN','SV','NI','CR','PA','BR','PT'];
        $iberoLangs = ['es','pt'];

        $page = max(1, (int)($_GET['page'] ?? 1));

        if ($hasQuery && !$hasFilters) {
            // Text search + filter iberoamerican
            $results = $tmdb->searchMovies($query, $page);
            if (!empty($results['results'])) {
                $results['results'] = array_values(array_filter($results['results'], function($m) use ($iberoCountries, $iberoLangs) {
                    if (in_array($m['original_language'] ?? '', $iberoLangs)) return true;
                    if (!empty($m['origin_country'])) {
                        foreach ($m['origin_country'] as $cc) {
                            if (in_array($cc, $iberoCountries)) return true;
                        }
                    }
                    return false;
                }));
            }
        } else {
            // Discover mode: use filters
            $discoverParams = ['sort_by' => 'popularity.desc'];

            if (!empty($countries)) {
                // TMDb discover: with_origin_country accepts pipe-separated
                $discoverParams['with_origin_country'] = implode('|', $countries);
            } else {
                // Default: all iberoamerican countries
                $discoverParams['with_origin_country'] = implode('|', $iberoCountries);
            }

            if ($year > 0) {
                $discoverParams['primary_release_year'] = $year;
            }

            if ($hasQuery) {
                $results = $tmdb->searchMovies($query, $page);
                $filterCountries = !empty($countries) ? $countries : $iberoCountries;
                if (!empty($results['results'])) {
                    $results['results'] = array_values(array_filter($results['results'], function($m) use ($filterCountries, $iberoLangs, $year) {
                        $langMatch = in_array($m['original_language'] ?? '', $iberoLangs);
                        $countryMatch = false;
                        if (!empty($m['origin_country'])) {
                            foreach ($m['origin_country'] as $cc) {
                                if (in_array($cc, $filterCountries)) { $countryMatch = true; break; }
                            }
                        }
                        $yearMatch = $year <= 0 || (substr($m['release_date'] ?? '', 0, 4) == (string)$year);
                        return ($langMatch || $countryMatch) && $yearMatch;
                    }));
                }
            } else {
                $results = $tmdb->discoverMovies($discoverParams, $page);
            }
        }

        // Mark already imported
        if (!empty($results['results'])) {
            $tmdbIds = array_column($results['results'], 'id');
            $pdo = Database::connect();
            $placeholders = implode(',', array_fill(0, count($tmdbIds), '?'));
            $params = $tenantId !== null ? array_merge([$tenantId], $tmdbIds) : $tmdbIds;
            $tenantWhere = $tenantId !== null ? "tenant_id = ? AND" : "";
            $stmt = $pdo->prepare("SELECT tmdb_id FROM films WHERE {$tenantWhere} tmdb_id IN ({$placeholders})");
            $stmt->execute($params);
            $imported = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'tmdb_id');

            foreach ($results['results'] as &$movie) {
                $movie['already_imported'] = in_array($movie['id'], $imported);
            }
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $results]);
    }

    /**
     * Single film — /films/{slug}
     */
    public function show($slug)
    {
        $tenantId = $this->getTenantId();

        $query = Film::query()
            ->where('slug', $slug)
            ->where('status', 'published');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $film = $query->first();

        if (!$film) {
            http_response_code(404);
            echo View::renderTheme('errors/404', ['message' => 'Película no encontrada']);
            return;
        }

        if (is_array($film) || $film instanceof \stdClass) {
            $film = new Film($film);
        }

        $film->incrementViewCount();

        // Load genres
        $genres = $this->loadFilmGenres($film->id);

        // Related films (same director or genre)
        $relatedFilms = $this->loadRelatedFilms($film, $tenantId);

        // SEO
        $siteName = function_exists('site_setting') ? site_setting('site_name', 'IberoFilms') : 'IberoFilms';
        $seoTitle = $film->seo_title ?: $film->title . ($film->year ? ' (' . $film->year . ')' : '');
        $seoDesc = $film->seo_description ?: ($film->synopsis_editorial ?: $film->synopsis_tmdb);
        $seoDesc = mb_substr(strip_tags($seoDesc ?? ''), 0, 160);
        $seoImage = $film->seo_image ?: film_poster_url($film->poster_path, 'w780');

        // JSON-LD Movie schema
        $movieLd = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Movie',
            'name'        => $film->title,
            'url'         => 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . '/films/' . $film->slug,
            'description' => $seoDesc,
        ];

        if ($film->poster_path) {
            $movieLd['image'] = film_poster_url($film->poster_path, 'w780');
        }
        if ($film->release_date) {
            $movieLd['datePublished'] = $film->release_date;
        }
        if ($film->director) {
            $movieLd['director'] = [
                '@type' => 'Person',
                'name'  => $film->director,
            ];
        }
        if ($film->runtime) {
            $movieLd['duration'] = 'PT' . $film->runtime . 'M';
        }
        if ($film->original_language) {
            $movieLd['inLanguage'] = $film->original_language;
        }
        if ($film->tmdb_rating) {
            $movieLd['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => $film->tmdb_rating,
                'bestRating'  => 10,
                'ratingCount' => $film->tmdb_vote_count ?: 1,
            ];
        }

        // Cast actors
        $cast = $film->getCast(5);
        if (!empty($cast)) {
            $movieLd['actor'] = array_map(fn($a) => ['@type' => 'Person', 'name' => $a['name']], $cast);
        }

        // Genres
        if (!empty($genres)) {
            $movieLd['genre'] = array_map(fn($g) => $g->name, $genres);
        }

        // Countries
        $countries = $film->getCountries();
        if (!empty($countries)) {
            $movieLd['countryOfOrigin'] = array_map(fn($c) => ['@type' => 'Country', 'name' => $c], $countries);
        }

        if ($film->trailer_url) {
            $movieLd['trailer'] = [
                '@type'    => 'VideoObject',
                'embedUrl' => $film->trailer_url,
                'name'     => 'Tráiler de ' . $film->title,
            ];
        }

        // Breadcrumbs
        $breadcrumbLd = [
            '@context' => 'https://schema.org',
            '@type'    => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => 'https://' . ($_SERVER['HTTP_HOST'] ?? '')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Películas', 'item' => 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . '/films'],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $film->title],
            ],
        ];

        View::addGlobalData([
            '__page_title'        => $seoTitle . ' | ' . $siteName,
            '__page_description'  => $seoDesc,
            '__og_title'          => $seoTitle,
            '__og_description'    => $seoDesc,
            '__og_image'          => $seoImage,
            '__og_type'           => 'video.movie',
            '__jsonld_movie'      => json_encode($movieLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            '__jsonld_breadcrumb' => json_encode($breadcrumbLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        echo View::renderTheme('films/single', [
            'film'         => $film,
            'genres'       => $genres,
            'relatedFilms' => $relatedFilms,
            'seoTitle'     => $seoTitle,
            'seoDesc'      => $seoDesc,
            'seoImage'     => $seoImage,
        ]);
    }

    /**
     * Films by genre — /films/genre/{slug}
     */
    public function genre($slug)
    {
        $tenantId = $this->getTenantId();

        $genreQuery = FilmGenre::where('slug', $slug);
        if ($tenantId !== null) {
            $genreQuery->where('tenant_id', $tenantId);
        }
        $genre = $genreQuery->first();

        if (!$genre) {
            http_response_code(404);
            echo View::renderTheme('errors/404', ['message' => 'Género no encontrado']);
            return;
        }

        $perPage = (int)(film_setting('films_per_page', 12));
        $currentPage = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($currentPage - 1) * $perPage;

        $pdo = Database::connect();
        $tenantWhere = $tenantId !== null ? "AND f.tenant_id = ?" : "";
        $params = $tenantId !== null ? [$genre->id, $tenantId] : [$genre->id];

        $countStmt = $pdo->prepare("
            SELECT COUNT(DISTINCT f.id) as cnt
            FROM films f
            INNER JOIN film_genre_pivot fgp ON fgp.film_id = f.id
            WHERE fgp.genre_id = ? AND f.status = 'published' {$tenantWhere}
        ");
        $countStmt->execute($params);
        $totalCount = (int)$countStmt->fetch(\PDO::FETCH_ASSOC)['cnt'];

        $stmt = $pdo->prepare("
            SELECT f.*
            FROM films f
            INNER JOIN film_genre_pivot fgp ON fgp.film_id = f.id
            WHERE fgp.genre_id = ? AND f.status = 'published' {$tenantWhere}
            ORDER BY f.featured DESC, f.year DESC, f.title ASC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $films = array_map(fn($r) => new Film($r), $stmt->fetchAll(\PDO::FETCH_ASSOC));

        $filmGenres = $this->batchLoadGenres($films);

        $siteName = function_exists('site_setting') ? site_setting('site_name', 'IberoFilms') : 'IberoFilms';

        View::addGlobalData([
            '__page_title'       => ($genre->seo_title ?: 'Películas de ' . $genre->name) . ' | ' . $siteName,
            '__page_description' => $genre->seo_description ?: 'Películas de ' . $genre->name . ' en el cine iberoamericano.',
        ]);

        echo View::renderTheme('films/genre', [
            'title'      => 'Películas de ' . $genre->name,
            'genre'      => $genre,
            'films'      => $films,
            'filmGenres' => $filmGenres,
            'pagination' => [
                'current_page' => $currentPage,
                'total_pages'  => $perPage > 0 ? (int)ceil($totalCount / $perPage) : 1,
                'total_posts'  => $totalCount,
                'per_page'     => $perPage,
            ],
        ]);
    }

    /**
     * Films by director — /films/director/{slug}
     */
    public function director($slug)
    {
        $tenantId = $this->getTenantId();

        $perPage = (int)(film_setting('films_per_page', 12));
        $currentPage = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($currentPage - 1) * $perPage;

        $query = Film::query()
            ->where('status', 'published')
            ->where('director_slug', $slug);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $totalCount = $query->count();
        $films = $query->orderBy('year', 'DESC')->orderBy('title', 'ASC')
            ->limit($perPage)->offset($offset)->get();

        $directorName = !empty($films) ? $films[0]->director : ucfirst(str_replace('-', ' ', $slug));

        $filmGenres = $this->batchLoadGenres($films);

        $siteName = function_exists('site_setting') ? site_setting('site_name', 'IberoFilms') : 'IberoFilms';

        View::addGlobalData([
            '__page_title'       => 'Películas de ' . $directorName . ' | ' . $siteName,
            '__page_description' => 'Filmografía de ' . $directorName . ' en IberoFilms.',
        ]);

        echo View::renderTheme('films/director', [
            'title'        => 'Filmografía de ' . $directorName,
            'directorName' => $directorName,
            'directorSlug' => $slug,
            'films'        => $films,
            'filmGenres'   => $filmGenres,
            'pagination'   => [
                'current_page' => $currentPage,
                'total_pages'  => $perPage > 0 ? (int)ceil($totalCount / $perPage) : 1,
                'total_posts'  => $totalCount,
                'per_page'     => $perPage,
            ],
        ]);
    }

    /**
     * Films by year — /films/year/{year}
     */
    public function year($year)
    {
        $tenantId = $this->getTenantId();
        $year = (int)$year;

        $perPage = (int)(film_setting('films_per_page', 12));
        $currentPage = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($currentPage - 1) * $perPage;

        $query = Film::query()
            ->where('status', 'published')
            ->where('year', $year);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $totalCount = $query->count();
        $films = $query->orderBy('featured', 'DESC')->orderBy('title', 'ASC')
            ->limit($perPage)->offset($offset)->get();

        $filmGenres = $this->batchLoadGenres($films);

        $siteName = function_exists('site_setting') ? site_setting('site_name', 'IberoFilms') : 'IberoFilms';

        View::addGlobalData([
            '__page_title'       => 'Películas de ' . $year . ' | ' . $siteName,
            '__page_description' => 'Películas del cine iberoamericano del año ' . $year . '.',
        ]);

        echo View::renderTheme('films/year', [
            'title'      => 'Películas de ' . $year,
            'year'       => $year,
            'films'      => $films,
            'filmGenres' => $filmGenres,
            'pagination' => [
                'current_page' => $currentPage,
                'total_pages'  => $perPage > 0 ? (int)ceil($totalCount / $perPage) : 1,
                'total_posts'  => $totalCount,
                'per_page'     => $perPage,
            ],
        ]);
    }

    /**
     * Actor profile — /films/actor/{tmdb_id}-{slug}
     */
    public function actor($idSlug)
    {
        $tenantId = $this->getTenantId();

        // Parse "16867-luis-tosar" → tmdb_id=16867
        $parts = explode('-', $idSlug, 2);
        $actorTmdbId = (int)($parts[0] ?? 0);

        if ($actorTmdbId <= 0) {
            http_response_code(404);
            echo View::renderTheme('errors/404', ['message' => 'Actor no encontrado']);
            return;
        }

        $pdo = Database::connect();

        // ── Try to load cached person data ──
        $person = null;
        $stmt = $pdo->prepare("SELECT * FROM film_people WHERE tmdb_id = ? AND tenant_id = ? LIMIT 1");
        $stmt->execute([$actorTmdbId, $tenantId ?? 0]);
        $personRow = $stmt->fetch(\PDO::FETCH_ASSOC);

        // If not cached or stale (>7 days), fetch from TMDb
        $apiOn = (bool)film_setting('api_enabled', 1);
        $needsFetch = $apiOn && (!$personRow || (strtotime($personRow['fetched_at'] ?? '2000-01-01') < strtotime('-7 days')));

        if ($needsFetch) {
            $apiKey = film_setting('tmdb_api_key', '');
            if (!empty($apiKey)) {
                $tmdb = new \FilmLibrary\Services\TmdbService($apiKey, film_setting('tmdb_language', 'es-ES'));
                $personData = $tmdb->getPerson($actorTmdbId);

                if (!isset($personData['success']) || $personData['success'] !== false) {
                    $slug = function_exists('film_slugify') ? film_slugify($personData['name'] ?? '') : '';
                    $movieCredits = json_encode($personData['movie_credits'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                    $upsert = $pdo->prepare("
                        INSERT INTO film_people (tenant_id, tmdb_id, name, slug, biography, birthday, deathday, birthplace, gender, profile_path, imdb_id, known_for, movie_credits_json, fetched_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ON CONFLICT (tenant_id, tmdb_id) DO UPDATE SET
                            name = EXCLUDED.name, slug = EXCLUDED.slug, biography = EXCLUDED.biography,
                            birthday = EXCLUDED.birthday, deathday = EXCLUDED.deathday, birthplace = EXCLUDED.birthplace,
                            gender = EXCLUDED.gender, profile_path = EXCLUDED.profile_path, imdb_id = EXCLUDED.imdb_id,
                            known_for = EXCLUDED.known_for, movie_credits_json = EXCLUDED.movie_credits_json,
                            fetched_at = NOW(), updated_at = NOW()
                    ");
                    $upsert->execute([
                        $tenantId ?? 0,
                        $personData['id'],
                        $personData['name'] ?? '',
                        $slug,
                        $personData['biography'] ?? null,
                        $personData['birthday'] ?? null,
                        $personData['deathday'] ?? null,
                        $personData['place_of_birth'] ?? null,
                        $personData['gender'] ?? 0,
                        $personData['profile_path'] ?? null,
                        $personData['external_ids']['imdb_id'] ?? null,
                        $personData['known_for_department'] ?? null,
                        $movieCredits,
                    ]);

                    // Re-read
                    $stmt = $pdo->prepare("SELECT * FROM film_people WHERE tmdb_id = ? AND tenant_id = ? LIMIT 1");
                    $stmt->execute([$actorTmdbId, $tenantId ?? 0]);
                    $personRow = $stmt->fetch(\PDO::FETCH_ASSOC);
                }
            }
        }

        $actorName = $personRow['name'] ?? 'Actor desconocido';
        $actorPhoto = $personRow['profile_path'] ?? null;
        $biography = $personRow['biography'] ?? '';
        $birthday = $personRow['birthday'] ?? null;
        $deathday = $personRow['deathday'] ?? null;
        $birthplace = $personRow['birthplace'] ?? '';
        $gender = (int)($personRow['gender'] ?? 0);
        $imdbId = $personRow['imdb_id'] ?? null;
        $knownFor = $personRow['known_for'] ?? '';

        // Parse full filmography from TMDb credits
        $allCredits = json_decode($personRow['movie_credits_json'] ?? '{}', true);
        $tmdbFilmography = [];
        foreach ($allCredits['cast'] ?? [] as $credit) {
            $tmdbFilmography[] = [
                'tmdb_id'      => $credit['id'] ?? 0,
                'title'        => $credit['title'] ?? '',
                'character'    => $credit['character'] ?? '',
                'release_date' => $credit['release_date'] ?? '',
                'year'         => !empty($credit['release_date']) ? substr($credit['release_date'], 0, 4) : '',
                'poster_path'  => $credit['poster_path'] ?? null,
                'vote_average' => $credit['vote_average'] ?? 0,
            ];
        }
        // Sort by year descending
        usort($tmdbFilmography, fn($a, $b) => ($b['year'] ?? '') <=> ($a['year'] ?? ''));

        // ── Films in our catalog ──
        $tenantWhere = $tenantId !== null ? "AND tenant_id = ?" : "";
        $likePattern = '%"tmdb_id":' . $actorTmdbId . '%';
        $countParams = [$likePattern];
        if ($tenantId !== null) $countParams[] = $tenantId;

        $countStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM films WHERE status = 'published' AND cast_json LIKE ? {$tenantWhere}");
        $countStmt->execute($countParams);
        $localCount = (int)$countStmt->fetch(\PDO::FETCH_ASSOC)['cnt'];

        $localStmt = $pdo->prepare("SELECT * FROM films WHERE status = 'published' AND cast_json LIKE ? {$tenantWhere} ORDER BY year DESC, title ASC LIMIT 50");
        $localStmt->execute($countParams);
        $localFilms = array_map(fn($r) => new Film($r), $localStmt->fetchAll(\PDO::FETCH_ASSOC));

        // Mark which TMDb credits we have locally
        $localTmdbIds = array_map(fn($f) => $f->tmdb_id, $localFilms);
        foreach ($tmdbFilmography as &$credit) {
            $credit['in_catalog'] = in_array($credit['tmdb_id'], $localTmdbIds);
        }

        $filmGenres = $this->batchLoadGenres($localFilms);

        // Age calculation
        $age = null;
        if ($birthday) {
            $birth = new \DateTime($birthday);
            $endDate = $deathday ? new \DateTime($deathday) : new \DateTime();
            $age = $endDate->diff($birth)->y;
        }

        $siteName = function_exists('site_setting') ? site_setting('site_name', 'IberoFilms') : 'IberoFilms';

        // Schema.org Person JSON-LD
        $personLd = [
            '@context' => 'https://schema.org',
            '@type'    => 'Person',
            'name'     => $actorName,
            'url'      => 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . '/films/actor/' . $idSlug,
        ];
        if ($actorPhoto) $personLd['image'] = 'https://image.tmdb.org/t/p/w500' . $actorPhoto;
        if ($birthday) $personLd['birthDate'] = $birthday;
        if ($birthplace) $personLd['birthPlace'] = $birthplace;
        if ($biography) $personLd['description'] = mb_substr($biography, 0, 300);

        $seoDesc = $biography ? mb_substr(strip_tags($biography), 0, 160) : 'Filmografía de ' . $actorName;

        View::addGlobalData([
            '__page_title'       => $actorName . ' — Filmografía | ' . $siteName,
            '__page_description' => $seoDesc,
            '__og_title'         => $actorName,
            '__og_description'   => $seoDesc,
            '__og_image'         => $actorPhoto ? 'https://image.tmdb.org/t/p/w500' . $actorPhoto : '',
            '__jsonld_person'    => json_encode($personLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        echo View::renderTheme('films/actor', [
            'title'           => $actorName,
            'actorName'       => $actorName,
            'actorPhoto'      => $actorPhoto,
            'actorTmdbId'     => $actorTmdbId,
            'actorSlug'       => $idSlug,
            'biography'       => $biography,
            'birthday'        => $birthday,
            'deathday'        => $deathday,
            'age'             => $age,
            'birthplace'      => $birthplace,
            'gender'          => $gender,
            'imdbId'          => $imdbId,
            'knownFor'        => $knownFor,
            'localFilms'      => $localFilms,
            'localCount'      => $localCount,
            'filmGenres'      => $filmGenres,
            'tmdbFilmography' => $tmdbFilmography,
            'apiEnabled'      => (bool)film_setting('api_enabled', 1),
        ]);
    }

    /**
     * Auto-import and redirect — /films/go/{tmdbId}
     * If the film exists in catalog, redirect to it.
     * If not, import from TMDb, publish, and redirect.
     */
    public function goToFilm($tmdbId)
    {
        $tenantId = $this->getTenantId();
        $tmdbId = (int)$tmdbId;

        if ($tmdbId <= 0) {
            http_response_code(404);
            echo View::renderTheme('errors/404', ['message' => 'Película no encontrada']);
            return;
        }

        // Check if already in catalog
        $existing = Film::query()->where('status', 'published');
        if ($tenantId !== null) {
            $existing->where('tenant_id', $tenantId);
        }
        $existing = $existing->where('tmdb_id', $tmdbId)->first();

        if ($existing) {
            header('Location: /films/' . $existing->slug);
            exit;
        }

        // Also check drafts
        $draft = Film::query();
        if ($tenantId !== null) {
            $draft->where('tenant_id', $tenantId);
        }
        $draft = $draft->where('tmdb_id', $tmdbId)->first();

        if ($draft) {
            if (!empty($draft->poster_path)) {
                $pdo = Database::connect();
                $pdo->prepare("UPDATE films SET status = 'published' WHERE id = ?")->execute([$draft->id]);
                header('Location: /films/' . $draft->slug);
                exit;
            }
            header('Location: /films');
            exit;
        }

        // Not in catalog — import from TMDb (only if API is enabled)
        if (!film_setting('api_enabled', 1)) {
            header('Location: /films');
            exit;
        }

        $apiKey = film_setting('tmdb_api_key', '');
        if (empty($apiKey)) {
            http_response_code(404);
            echo View::renderTheme('errors/404', ['message' => 'Película no disponible en el catálogo']);
            return;
        }

        $tmdb = new \FilmLibrary\Services\TmdbService($apiKey, film_setting('tmdb_language', 'es-ES'));
        $movieData = $tmdb->getMovie($tmdbId);

        if (isset($movieData['success']) && $movieData['success'] === false) {
            http_response_code(404);
            echo View::renderTheme('errors/404', ['message' => 'Película no encontrada en TMDb']);
            return;
        }

        $effectiveTenantId = $tenantId ?? 0;

        // Build film data
        $filmData = $tmdb->buildFilmData($movieData, $effectiveTenantId);
        $filmData['status'] = !empty($filmData['poster_path']) ? 'published' : 'draft';

        // Ensure unique slug
        $existingSlug = Film::query()->where('tenant_id', $effectiveTenantId)->where('slug', $filmData['slug'])->first();
        if ($existingSlug) {
            $filmData['slug'] .= '-' . $tmdbId;
        }

        // Download images as backup
        $downloader = new \FilmLibrary\Services\ImageDownloader($effectiveTenantId);
        if (!empty($filmData['poster_path'])) {
            $downloader->downloadPoster($filmData['poster_path']);
            $filmData['poster_local'] = $downloader->getLocalUrl($filmData['poster_path'], 'w500', 'posters');
        }
        if (!empty($filmData['backdrop_path'])) {
            $downloader->downloadBackdrop($filmData['backdrop_path']);
        }

        // Create film
        $film = Film::create($filmData);

        // Sync genres
        $localGenreIds = [];
        foreach ($movieData['genres'] ?? [] as $tmdbGenre) {
            $localGenre = FilmGenre::query()
                ->where('tenant_id', $effectiveTenantId)
                ->where('tmdb_id', $tmdbGenre['id'])
                ->first();

            if (!$localGenre) {
                $localGenre = FilmGenre::create([
                    'tenant_id' => $effectiveTenantId,
                    'tmdb_id'   => $tmdbGenre['id'],
                    'name'      => $tmdbGenre['name'],
                    'slug'      => film_slugify($tmdbGenre['name']),
                ]);
            }
            $localGenreIds[] = $localGenre->id;
        }

        if (!empty($localGenreIds)) {
            $film->syncGenres($localGenreIds);
        }

        // Invalidate sitemap so new film appears
        if (function_exists('film_invalidate_sitemap')) {
            film_invalidate_sitemap();
        }

        header('Location: /films/' . $film->slug);
        exit;
    }

    // ─── Private helpers ───────────────────────────

    private function batchLoadGenres(array $films): array
    {
        if (empty($films)) return [];

        $ids = array_map(fn($f) => is_object($f) ? $f->id : $f['id'], $films);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT fgp.film_id, g.id, g.name, g.slug, g.color
            FROM film_genre_pivot fgp
            INNER JOIN film_genres g ON g.id = fgp.genre_id
            WHERE fgp.film_id IN ({$placeholders})
        ");
        $stmt->execute($ids);

        $result = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $result[$row['film_id']][] = $row;
        }
        return $result;
    }

    private function loadFilmGenres(int $filmId): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT g.* FROM film_genres g
            INNER JOIN film_genre_pivot fgp ON fgp.genre_id = g.id
            WHERE fgp.film_id = ?
            ORDER BY g.name
        ");
        $stmt->execute([$filmId]);
        return array_map(fn($r) => new FilmGenre($r), $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function loadRelatedFilms(Film $film, ?int $tenantId): array
    {
        $pdo = Database::connect();
        $tenantWhere = $tenantId !== null ? "AND f.tenant_id = ?" : "";

        // Same director first, then same genres
        $params = [$film->id];
        if ($film->director_slug) {
            $params[] = $film->director_slug;
        }
        if ($tenantId !== null) {
            $params[] = $tenantId;
        }

        $directorCondition = $film->director_slug ? "OR f.director_slug = ?" : "";

        $stmt = $pdo->prepare("
            SELECT DISTINCT f.*
            FROM films f
            LEFT JOIN film_genre_pivot fgp ON fgp.film_id = f.id
            LEFT JOIN film_genre_pivot fgp2 ON fgp2.genre_id = fgp.genre_id AND fgp2.film_id = ?
            WHERE f.status = 'published'
              AND f.id != ?
              AND (fgp2.film_id IS NOT NULL {$directorCondition})
              {$tenantWhere}
            ORDER BY f.featured DESC, f.year DESC
            LIMIT 6
        ");

        $execParams = [$film->id, $film->id];
        if ($film->director_slug) {
            $execParams[] = $film->director_slug;
        }
        if ($tenantId !== null) {
            $execParams[] = $tenantId;
        }

        $stmt->execute($execParams);
        return array_map(fn($r) => new Film($r), $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }
}
