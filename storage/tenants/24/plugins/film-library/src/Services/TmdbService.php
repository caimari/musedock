<?php

namespace FilmLibrary\Services;

class TmdbService
{
    private string $apiKey;
    private string $language;
    private string $baseUrl = 'https://api.themoviedb.org/3';

    public function __construct(string $apiKey, string $language = 'es-ES')
    {
        $this->apiKey = $apiKey;
        $this->language = $language;
    }

    /**
     * Search movies by query.
     */
    public function searchMovies(string $query, int $page = 1): array
    {
        return $this->request('/search/movie', [
            'query' => $query,
            'page'  => $page,
        ]);
    }

    /**
     * Get movie details by TMDb ID.
     */
    public function getMovie(int $tmdbId): array
    {
        return $this->request("/movie/{$tmdbId}", [
            'append_to_response' => 'credits,videos,watch/providers',
        ]);
    }

    /**
     * Get movie credits (cast & crew).
     */
    public function getCredits(int $tmdbId): array
    {
        return $this->request("/movie/{$tmdbId}/credits");
    }

    /**
     * Get TMDb genre list.
     */
    public function getGenreList(): array
    {
        $data = $this->request('/genre/movie/list');
        return $data['genres'] ?? [];
    }

    /**
     * Discover movies by filters.
     */
    public function discoverMovies(array $params = [], int $page = 1): array
    {
        $params['page'] = $page;
        return $this->request('/discover/movie', $params);
    }

    /**
     * Get trending movies.
     */
    public function getTrending(string $timeWindow = 'week'): array
    {
        return $this->request("/trending/movie/{$timeWindow}");
    }

    /**
     * Get person details (actor/director).
     */
    public function getPerson(int $personId): array
    {
        return $this->request("/person/{$personId}", [
            'append_to_response' => 'movie_credits,external_ids',
        ]);
    }

    /**
     * Extract director from credits.
     */
    public function extractDirector(array $movieData): ?string
    {
        $crew = $movieData['credits']['crew'] ?? [];
        foreach ($crew as $member) {
            if ($member['job'] === 'Director') {
                return $member['name'];
            }
        }
        return null;
    }

    /**
     * Extract top cast from credits.
     */
    public function extractCast(array $movieData, int $limit = 15): array
    {
        $cast = $movieData['credits']['cast'] ?? [];
        $result = [];
        foreach (array_slice($cast, 0, $limit) as $actor) {
            $result[] = [
                'name'      => $actor['name'],
                'character' => $actor['character'] ?? '',
                'photo'     => $actor['profile_path'] ?? null,
                'tmdb_id'   => $actor['id'],
            ];
        }
        return $result;
    }

    /**
     * Extract key crew members.
     */
    public function extractCrew(array $movieData): array
    {
        $crew = $movieData['credits']['crew'] ?? [];
        $result = [];
        $importantJobs = ['Director', 'Producer', 'Screenplay', 'Writer', 'Director of Photography', 'Original Music Composer'];

        foreach ($crew as $member) {
            if (in_array($member['job'], $importantJobs)) {
                $result[] = [
                    'name' => $member['name'],
                    'job'  => $member['job'],
                    'tmdb_id' => $member['id'],
                ];
            }
        }
        return $result;
    }

    /**
     * Extract trailer URL from videos.
     */
    public function extractTrailer(array $movieData): ?string
    {
        $videos = $movieData['videos']['results'] ?? [];
        foreach ($videos as $video) {
            if ($video['type'] === 'Trailer' && $video['site'] === 'YouTube') {
                return 'https://www.youtube.com/watch?v=' . $video['key'];
            }
        }
        // Fallback to any YouTube video
        foreach ($videos as $video) {
            if ($video['site'] === 'YouTube') {
                return 'https://www.youtube.com/watch?v=' . $video['key'];
            }
        }
        return null;
    }

    /**
     * Extract watch providers for a region.
     */
    public function extractWatchProviders(array $movieData, string $region = 'ES'): array
    {
        $providers = $movieData['watch/providers']['results'][$region] ?? [];
        $result = [];

        foreach (['flatrate' => 'Suscripción', 'rent' => 'Alquiler', 'buy' => 'Compra'] as $type => $label) {
            if (!empty($providers[$type])) {
                foreach ($providers[$type] as $p) {
                    $result[] = [
                        'name'      => $p['provider_name'],
                        'logo_path' => $p['logo_path'] ?? null,
                        'type'      => $label,
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Build data array ready to create/update a Film model.
     */
    public function buildFilmData(array $movieData, int $tenantId): array
    {
        $title = $movieData['title'] ?? '';
        $year = !empty($movieData['release_date']) ? substr($movieData['release_date'], 0, 4) : '';
        $slug = function_exists('film_slugify')
            ? film_slugify($title . ($year ? '-' . $year : ''))
            : strtolower(preg_replace('/[^a-z0-9]+/', '-', $title . '-' . $year));

        $countries = [];
        foreach ($movieData['production_countries'] ?? [] as $c) {
            $countries[] = $c['name'] ?? $c['iso_3166_1'] ?? '';
        }

        return [
            'tenant_id'            => $tenantId,
            'tmdb_id'              => $movieData['id'] ?? null,
            'imdb_id'              => $movieData['imdb_id'] ?? null,
            'title'                => $title,
            'original_title'       => $movieData['original_title'] ?? $title,
            'slug'                 => $slug,
            'tagline'              => $movieData['tagline'] ?? null,
            'synopsis_tmdb'        => $movieData['overview'] ?? null,
            'poster_path'          => $movieData['poster_path'] ?? null,
            'backdrop_path'        => $movieData['backdrop_path'] ?? null,
            'release_date'         => $movieData['release_date'] ?? null,
            'year'                 => $year ? (int)$year : null,
            'runtime'              => $movieData['runtime'] ?? null,
            'original_language'    => $movieData['original_language'] ?? null,
            'production_countries' => implode(', ', $countries),
            'budget'               => $movieData['budget'] ?? 0,
            'revenue'              => $movieData['revenue'] ?? 0,
            'tmdb_rating'          => $movieData['vote_average'] ?? null,
            'tmdb_vote_count'      => $movieData['vote_count'] ?? 0,
            'director'             => $this->extractDirector($movieData),
            'director_slug'        => $this->extractDirector($movieData)
                ? (function_exists('film_slugify') ? film_slugify($this->extractDirector($movieData)) : '')
                : null,
            'cast_json'            => json_encode($this->extractCast($movieData), JSON_UNESCAPED_UNICODE),
            'crew_json'            => json_encode($this->extractCrew($movieData), JSON_UNESCAPED_UNICODE),
            'trailer_url'          => $this->extractTrailer($movieData),
            'watch_providers_json' => json_encode($this->extractWatchProviders($movieData), JSON_UNESCAPED_UNICODE),
            'status'               => 'draft',
        ];
    }

    /**
     * Extract TMDb genre IDs from movie data.
     */
    public function extractGenreIds(array $movieData): array
    {
        return array_column($movieData['genres'] ?? [], 'id');
    }

    /**
     * Make API request to TMDb.
     */
    private function request(string $endpoint, array $params = []): array
    {
        $params['api_key'] = $this->apiKey;
        $params['language'] = $this->language;

        $url = $this->baseUrl . $endpoint . '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("TMDb API error: {$error}");
            return ['success' => false, 'error' => $error];
        }

        if ($httpCode !== 200) {
            error_log("TMDb API HTTP {$httpCode}: {$response}");
            return ['success' => false, 'error' => "HTTP {$httpCode}", 'status_code' => $httpCode];
        }

        return json_decode($response, true) ?: [];
    }
}
