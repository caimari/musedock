<?php

namespace FilmLibrary\Models;

use Screenart\Musedock\Database\Model;
use Screenart\Musedock\Database;

class Film extends Model
{
    protected static string $table = 'films';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'tenant_id',
        'tmdb_id',
        'imdb_id',
        'title',
        'original_title',
        'slug',
        'tagline',
        'synopsis_tmdb',
        'synopsis_editorial',
        'editorial_content',
        'editorial_context',
        'editorial_rating',
        'poster_path',
        'backdrop_path',
        'poster_local',
        'release_date',
        'year',
        'runtime',
        'original_language',
        'production_countries',
        'budget',
        'revenue',
        'tmdb_rating',
        'tmdb_vote_count',
        'director',
        'director_slug',
        'cast_json',
        'crew_json',
        'trailer_url',
        'watch_providers_json',
        'status',
        'featured',
        'sort_order',
        'view_count',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'seo_image',
        'noindex',
    ];

    protected array $casts = [
        'id'              => 'int',
        'tenant_id'       => 'int',
        'tmdb_id'         => 'int',
        'year'            => 'int',
        'runtime'         => 'int',
        'budget'          => 'int',
        'revenue'         => 'int',
        'tmdb_vote_count' => 'int',
        'editorial_rating'=> 'float',
        'tmdb_rating'     => 'float',
        'featured'        => 'boolean',
        'noindex'         => 'boolean',
        'view_count'      => 'int',
        'sort_order'      => 'int',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    /**
     * Sync genres (many-to-many via film_genre_pivot).
     */
    public function syncGenres(array $genreIds): bool
    {
        try {
            $pdo = Database::connect();
            $pdo->prepare("DELETE FROM film_genre_pivot WHERE film_id = ?")->execute([$this->id]);

            if (!empty($genreIds)) {
                $stmt = $pdo->prepare("INSERT INTO film_genre_pivot (film_id, genre_id) VALUES (?, ?)");
                foreach ($genreIds as $genreId) {
                    $stmt->execute([$this->id, (int)$genreId]);
                }
                self::updateGenreCounts($genreIds);
            }

            return true;
        } catch (\Exception $e) {
            error_log("Film syncGenres error (ID {$this->id}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get genre IDs for this film.
     */
    public function getGenreIds(): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT genre_id FROM film_genre_pivot WHERE film_id = ?");
        $stmt->execute([$this->id]);
        return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'genre_id');
    }

    /**
     * Increment view count (one per session).
     */
    public function incrementViewCount(): void
    {
        $key = 'film_viewed_' . $this->id;
        if (!empty($_SESSION[$key])) {
            return;
        }
        $_SESSION[$key] = true;

        $pdo = Database::connect();
        $pdo->prepare("UPDATE films SET view_count = view_count + 1 WHERE id = ?")->execute([$this->id]);
        $this->view_count = ($this->view_count ?? 0) + 1;
    }

    /**
     * Get decoded cast array.
     */
    public function getCast(int $limit = 10): array
    {
        $cast = json_decode($this->cast_json ?? '[]', true);
        return $limit > 0 ? array_slice($cast, 0, $limit) : $cast;
    }

    /**
     * Get decoded crew array.
     */
    public function getCrew(): array
    {
        return json_decode($this->crew_json ?? '[]', true);
    }

    /**
     * Get decoded watch providers.
     */
    public function getWatchProviders(): array
    {
        return json_decode($this->watch_providers_json ?? '[]', true);
    }

    /**
     * Get production countries as array.
     */
    public function getCountries(): array
    {
        $countries = $this->production_countries ?? '';
        return $countries ? array_map('trim', explode(',', $countries)) : [];
    }

    /**
     * Update film_count on genres.
     */
    public static function updateGenreCounts(array $genreIds): void
    {
        try {
            $pdo = Database::connect();
            foreach ($genreIds as $genreId) {
                $pdo->prepare("
                    UPDATE film_genres
                    SET film_count = (
                        SELECT COUNT(*) FROM film_genre_pivot WHERE genre_id = ?
                    )
                    WHERE id = ?
                ")->execute([(int)$genreId, (int)$genreId]);
            }
        } catch (\Exception $e) {
            error_log("updateGenreCounts error: " . $e->getMessage());
        }
    }

    /**
     * Film statuses.
     */
    public static function getStatuses(): array
    {
        return [
            'draft'     => 'Borrador',
            'published' => 'Publicado',
        ];
    }
}
