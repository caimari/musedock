<?php

namespace FilmLibrary\Controllers\Tenant;

use FilmLibrary\Models\Film;
use FilmLibrary\Models\FilmGenre;
use FilmLibrary\Services\TmdbService;
use Screenart\Musedock\Database;
use Screenart\Musedock\Services\TenantManager;

class FilmTmdbController
{
    private function checkPermission(string $permission): void
    {
        if (function_exists('userCan') && !userCan($permission)) {
            flash('error', 'No tienes permisos para esta acción.');
            header('Location: ' . admin_url('dashboard'));
            exit;
        }
    }

    private function getTenantId(): int
    {
        $tenantId = TenantManager::currentTenantId();
        if ($tenantId === null) {
            flash('error', 'Tenant no identificado.');
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }
        return $tenantId;
    }

    private function getTmdbService(): ?TmdbService
    {
        $apiKey = film_setting('tmdb_api_key', '');
        if (empty($apiKey)) {
            return null;
        }
        return new TmdbService($apiKey, film_setting('tmdb_language', 'es-ES'));
    }

    private function jsonResponse(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * TMDb import page.
     */
    public function index()
    {
        $this->checkPermission('films.import');

        $apiKey = film_setting('tmdb_api_key', '');
        $hasApiKey = !empty($apiKey);

        echo film_render_admin('tenant.tmdb.index', [
            'title'     => 'Importar de TMDb',
            'hasApiKey' => $hasApiKey,
        ]);
    }

    /**
     * Single AJAX endpoint for all TMDb operations.
     * Uses ?action=search|preview|import to determine what to do.
     * This avoids routing issues with multiple endpoints.
     */
    public function search()
    {
        $this->checkPermission('films.import');
        $tenantId = $this->getTenantId();

        $action = $_GET['action'] ?? 'search';

        if (!film_setting('api_enabled', 1)) {
            $this->jsonResponse(['success' => false, 'error' => 'La API de TMDb está desactivada. Actívala en Configuración.']);
            return;
        }

        $tmdb = $this->getTmdbService();
        if (!$tmdb) {
            $this->jsonResponse(['success' => false, 'error' => 'API key de TMDb no configurada. Ve a Configuración.']);
            return;
        }

        switch ($action) {
            case 'preview':
                $this->handlePreview($tmdb);
                return;

            case 'import':
                $this->handleImport($tmdb, $tenantId);
                return;

            case 'genres':
                $this->handleGenres($tmdb);
                return;

            case 'person':
                $this->handlePerson($tmdb, $tenantId);
                return;

            default:
                $this->handleSearch($tmdb, $tenantId);
                return;
        }
    }

    /**
     * Return TMDb genre list.
     */
    private function handleGenres(TmdbService $tmdb): void
    {
        $genres = $tmdb->getGenreList();
        $this->jsonResponse(['success' => true, 'genres' => $genres]);
    }

    /**
     * Fetch and cache person data from TMDb.
     */
    private function handlePerson(TmdbService $tmdb, int $tenantId): void
    {
        $personId = (int)($_GET['id'] ?? 0);
        if ($personId <= 0) {
            $this->jsonResponse(['success' => false, 'error' => 'ID de persona inválido.']);
            return;
        }

        $personData = $tmdb->getPerson($personId);
        if (isset($personData['success']) && $personData['success'] === false) {
            $this->jsonResponse(['success' => false, 'error' => 'Error al obtener datos de TMDb.']);
            return;
        }

        // Cache in film_people
        $pdo = Database::connect();
        $slug = function_exists('film_slugify') ? film_slugify($personData['name'] ?? '') : '';
        $movieCredits = json_encode($personData['movie_credits'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $stmt = $pdo->prepare("
            INSERT INTO film_people (tenant_id, tmdb_id, name, slug, biography, birthday, deathday, birthplace, gender, profile_path, imdb_id, known_for, movie_credits_json, fetched_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON CONFLICT (tenant_id, tmdb_id) DO UPDATE SET
                name = EXCLUDED.name, slug = EXCLUDED.slug, biography = EXCLUDED.biography,
                birthday = EXCLUDED.birthday, deathday = EXCLUDED.deathday, birthplace = EXCLUDED.birthplace,
                gender = EXCLUDED.gender, profile_path = EXCLUDED.profile_path, imdb_id = EXCLUDED.imdb_id,
                known_for = EXCLUDED.known_for, movie_credits_json = EXCLUDED.movie_credits_json,
                fetched_at = NOW(), updated_at = NOW()
        ");
        $stmt->execute([
            $tenantId,
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

        $this->jsonResponse(['success' => true, 'data' => $personData]);
    }

    /**
     * Search or discover TMDb movies.
     */
    private function handleSearch(TmdbService $tmdb, int $tenantId): void
    {
        $query = trim($_GET['q'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $genre = $_GET['genre'] ?? '';
        $year = $_GET['year'] ?? '';
        $country = $_GET['country'] ?? '';
        $sort = $_GET['sort'] ?? '';

        // If filters are set (no text query), use Discover endpoint
        $useDiscover = empty($query) && (!empty($genre) || !empty($year) || !empty($country) || !empty($sort));

        if (empty($query) && !$useDiscover) {
            $this->jsonResponse(['success' => false, 'error' => 'Introduce un término de búsqueda o selecciona filtros.']);
            return;
        }

        if ($useDiscover) {
            $params = [];
            if (!empty($genre))   $params['with_genres'] = $genre;
            if (!empty($year))    $params['primary_release_year'] = (int)$year;
            if (!empty($country)) $params['with_origin_country'] = strtoupper($country);
            if (!empty($sort))    $params['sort_by'] = $sort;
            else                  $params['sort_by'] = 'popularity.desc';
            $results = $tmdb->discoverMovies($params, $page);
        } else {
            $results = $tmdb->searchMovies($query, $page);
        }

        // Mark already imported films
        if (!empty($results['results'])) {
            $tmdbIds = array_column($results['results'], 'id');
            $pdo = Database::connect();
            $placeholders = implode(',', array_fill(0, count($tmdbIds), '?'));
            $stmt = $pdo->prepare("SELECT tmdb_id FROM films WHERE tenant_id = ? AND tmdb_id IN ({$placeholders})");
            $stmt->execute(array_merge([$tenantId], $tmdbIds));
            $imported = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'tmdb_id');

            foreach ($results['results'] as &$movie) {
                $movie['already_imported'] = in_array($movie['id'], $imported);
            }
        }

        $this->jsonResponse(['success' => true, 'data' => $results]);
    }

    /**
     * Preview movie details from TMDb.
     */
    private function handlePreview(TmdbService $tmdb): void
    {
        $tmdbId = (int)($_GET['id'] ?? 0);
        if ($tmdbId <= 0) {
            $this->jsonResponse(['success' => false, 'error' => 'ID de TMDb inválido.']);
            return;
        }

        $movieData = $tmdb->getMovie($tmdbId);
        if (isset($movieData['success']) && $movieData['success'] === false) {
            $this->jsonResponse(['success' => false, 'error' => $movieData['error'] ?? 'Error desconocido.']);
            return;
        }

        $movieData['_director'] = $tmdb->extractDirector($movieData);
        $movieData['_cast'] = $tmdb->extractCast($movieData, 10);
        $movieData['_trailer'] = $tmdb->extractTrailer($movieData);
        $movieData['_watch_providers'] = $tmdb->extractWatchProviders($movieData);

        $this->jsonResponse(['success' => true, 'data' => $movieData]);
    }

    /**
     * Import movie from TMDb.
     */
    private function handleImport(TmdbService $tmdb, int $tenantId): void
    {
        $tmdbId = (int)($_GET['tmdb_id'] ?? 0);
        if ($tmdbId <= 0) {
            $this->jsonResponse(['success' => false, 'error' => 'ID de TMDb inválido.']);
            return;
        }

        // Check if already imported
        $existing = Film::query()->where('tenant_id', $tenantId)->where('tmdb_id', $tmdbId)->first();
        if ($existing) {
            $this->jsonResponse([
                'success' => false,
                'error'   => 'Esta película ya está importada.',
                'film_id' => $existing->id,
            ]);
            return;
        }

        // Get full movie data
        $movieData = $tmdb->getMovie($tmdbId);
        if (isset($movieData['success']) && $movieData['success'] === false) {
            $this->jsonResponse(['success' => false, 'error' => 'Error al obtener datos de TMDb.']);
            return;
        }

        // Build film data
        $filmData = $tmdb->buildFilmData($movieData, $tenantId);

        // Ensure unique slug
        $existingSlug = Film::query()->where('tenant_id', $tenantId)->where('slug', $filmData['slug'])->first();
        if ($existingSlug) {
            $filmData['slug'] .= '-' . $tmdbId;
        }

        // Download images as local backup
        $downloader = new \FilmLibrary\Services\ImageDownloader($tenantId);
        if (!empty($filmData['poster_path'])) {
            $downloader->downloadPoster($filmData['poster_path']);
        }
        if (!empty($filmData['backdrop_path'])) {
            $downloader->downloadBackdrop($filmData['backdrop_path']);
        }

        // Store local poster path reference
        if (!empty($filmData['poster_path'])) {
            $filmData['poster_local'] = $downloader->getLocalUrl($filmData['poster_path'], 'w500', 'posters');
        }

        // Create film
        $film = Film::create($filmData);

        // Sync genres — create missing genres first
        $localGenreIds = [];

        foreach ($movieData['genres'] ?? [] as $tmdbGenre) {
            $localGenre = FilmGenre::query()
                ->where('tenant_id', $tenantId)
                ->where('tmdb_id', $tmdbGenre['id'])
                ->first();

            if (!$localGenre) {
                $localGenre = FilmGenre::create([
                    'tenant_id' => $tenantId,
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

        // Invalidate sitemap
        if (function_exists('film_invalidate_sitemap')) {
            film_invalidate_sitemap();
        }

        $this->jsonResponse([
            'success' => true,
            'film_id' => $film->id,
            'message' => "'{$film->title}' importada correctamente. Ahora puedes añadir contenido editorial.",
            'edit_url' => film_admin_url($film->id . '/edit'),
        ]);
    }
}
