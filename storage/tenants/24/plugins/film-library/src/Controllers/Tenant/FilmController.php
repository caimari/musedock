<?php

namespace FilmLibrary\Controllers\Tenant;

use FilmLibrary\Models\Film;
use FilmLibrary\Models\FilmGenre;
use Screenart\Musedock\Database;
use Screenart\Musedock\Services\TenantManager;

class FilmController
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

    /**
     * List films with search, sort, pagination.
     */
    public function index()
    {
        $this->checkPermission('films.view');
        $tenantId = $this->getTenantId();

        $search      = isset($_GET['search']) ? trim(substr($_GET['search'], 0, 255)) : '';
        $perPage     = isset($_GET['perPage']) ? (int)$_GET['perPage'] : 10;
        $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $orderBy     = $_GET['orderby'] ?? 'created_at';
        $order       = (isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC') ? 'ASC' : 'DESC';
        $statusFilter = $_GET['status'] ?? '';
        $genreFilter  = $_GET['genre'] ?? '';

        $allowedColumns = ['title', 'year', 'director', 'status', 'created_at', 'view_count', 'featured', 'tmdb_rating'];
        if (!in_array($orderBy, $allowedColumns)) {
            $orderBy = 'created_at';
        }

        $query = Film::query()
            ->where('tenant_id', $tenantId)
            ->orderBy($orderBy, $order);

        if (!empty($search)) {
            $s = "%{$search}%";
            $query->whereRaw("(title ILIKE ? OR director ILIKE ? OR original_title ILIKE ?)", [$s, $s, $s]);
        }

        if (!empty($statusFilter)) {
            $query->where('status', $statusFilter);
        }

        $totalCount = $query->count();

        if ($perPage == -1) {
            $perPage = min($totalCount, 500);
        }

        $films = $query->limit($perPage)->offset(($currentPage - 1) * $perPage)->get();

        // Batch load genres
        $filmGenres = [];
        if (!empty($films)) {
            $ids = array_map(fn($f) => $f->id, $films);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
                SELECT fp.film_id, g.id, g.name, g.slug, g.color
                FROM film_genre_pivot fp
                INNER JOIN film_genres g ON g.id = fp.genre_id
                WHERE fp.film_id IN ({$placeholders})
            ");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $filmGenres[$row['film_id']][] = $row;
            }
        }

        $genres = FilmGenre::query()->where('tenant_id', $tenantId)->orderBy('name', 'ASC')->get();

        $pagination = [
            'total'        => $totalCount,
            'per_page'     => $perPage,
            'current_page' => $currentPage,
            'last_page'    => $perPage > 0 ? (int)ceil($totalCount / $perPage) : 1,
            'from'         => ($currentPage - 1) * $perPage + 1,
            'to'           => min($currentPage * $perPage, $totalCount),
        ];

        echo film_render_admin('tenant.films.index', [
            'title'       => 'Películas',
            'films'       => $films,
            'filmGenres'  => $filmGenres,
            'genres'      => $genres,
            'pagination'  => $pagination,
            'search'      => $search,
            'statusFilter'=> $statusFilter,
            'genreFilter' => $genreFilter,
            'statuses'    => Film::getStatuses(),
            'orderBy'     => $orderBy,
            'order'       => $order,
        ]);
    }

    /**
     * Show create form.
     */
    public function create()
    {
        $this->checkPermission('films.create');
        $tenantId = $this->getTenantId();

        $genres = FilmGenre::query()->where('tenant_id', $tenantId)->orderBy('name', 'ASC')->get();

        echo film_render_admin('tenant.films.form', [
            'title'    => 'Crear Película',
            'film'     => null,
            'genres'   => $genres,
            'selectedGenres' => [],
            'statuses' => Film::getStatuses(),
            'isEdit'   => false,
        ]);
    }

    /**
     * Store new film.
     */
    public function store()
    {
        $this->checkPermission('films.create');
        $tenantId = $this->getTenantId();

        $data = $_POST;
        $data['tenant_id'] = $tenantId;
        $data['slug'] = film_slugify($data['title'] . (!empty($data['year']) ? '-' . $data['year'] : ''));

        // Ensure unique slug
        $existing = Film::query()->where('tenant_id', $tenantId)->where('slug', $data['slug'])->first();
        if ($existing) {
            $data['slug'] .= '-' . time();
        }

        $film = Film::create($data);

        // Sync genres
        $genreIds = array_map('intval', $data['genres'] ?? []);
        if (!empty($genreIds)) {
            $film->syncGenres($genreIds);
        }

        flash('success', 'Película creada correctamente.');
        header('Location: ' . film_admin_url($film->id . '/edit'));
        exit;
    }

    /**
     * Show edit form.
     */
    public function edit($id)
    {
        $this->checkPermission('films.edit');
        $tenantId = $this->getTenantId();

        $film = Film::query()->where('id', (int)$id)->where('tenant_id', $tenantId)->first();
        if (!$film) {
            flash('error', 'Película no encontrada.');
            header('Location: ' . film_admin_url());
            exit;
        }

        $genres = FilmGenre::query()->where('tenant_id', $tenantId)->orderBy('name', 'ASC')->get();
        $selectedGenres = $film->getGenreIds();

        echo film_render_admin('tenant.films.form', [
            'title'          => 'Editar: ' . $film->title,
            'film'           => $film,
            'genres'         => $genres,
            'selectedGenres' => $selectedGenres,
            'statuses'       => Film::getStatuses(),
            'isEdit'         => true,
        ]);
    }

    /**
     * Update film.
     */
    public function update($id)
    {
        $this->checkPermission('films.edit');
        $tenantId = $this->getTenantId();

        $film = Film::query()->where('id', (int)$id)->where('tenant_id', $tenantId)->first();
        if (!$film) {
            flash('error', 'Película no encontrada.');
            header('Location: ' . film_admin_url());
            exit;
        }

        $data = $_POST;
        unset($data['_token'], $data['_method']);

        // Update slug if title changed
        if (!empty($data['title']) && $data['title'] !== $film->title) {
            $data['slug'] = film_slugify($data['title'] . (!empty($data['year']) ? '-' . $data['year'] : ''));
            $existing = Film::query()->where('tenant_id', $tenantId)->where('slug', $data['slug'])
                ->whereRaw("id != ?", [$film->id])->first();
            if ($existing) {
                $data['slug'] .= '-' . time();
            }
        }

        $film->fill($data);
        $film->save();

        // Sync genres
        $genreIds = array_map('intval', $data['genres'] ?? []);
        $film->syncGenres($genreIds);

        flash('success', 'Película actualizada correctamente.');
        header('Location: ' . film_admin_url($film->id . '/edit'));
        exit;
    }

    /**
     * Delete film.
     */
    public function destroy($id)
    {
        $this->checkPermission('films.delete');
        $tenantId = $this->getTenantId();

        $film = Film::query()->where('id', (int)$id)->where('tenant_id', $tenantId)->first();
        if ($film) {
            $pdo = Database::connect();
            $pdo->prepare("DELETE FROM film_genre_pivot WHERE film_id = ?")->execute([$film->id]);
            $pdo->prepare("DELETE FROM films WHERE id = ?")->execute([$film->id]);
            flash('success', 'Película eliminada.');
        }

        header('Location: ' . film_admin_url());
        exit;
    }

    /**
     * Bulk actions (delete, publish, draft).
     */
    public function bulk()
    {
        $this->checkPermission('films.delete');
        $tenantId = $this->getTenantId();

        $ids = array_map('intval', $_POST['ids'] ?? []);
        $action = $_POST['action'] ?? '';

        if (empty($ids)) {
            flash('error', 'No se seleccionaron películas.');
            header('Location: ' . film_admin_url());
            exit;
        }

        $pdo = Database::connect();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        switch ($action) {
            case 'delete':
                $pdo->prepare("DELETE FROM film_genre_pivot WHERE film_id IN ({$placeholders})")->execute($ids);
                $params = array_merge($ids, [$tenantId]);
                $pdo->prepare("DELETE FROM films WHERE id IN ({$placeholders}) AND tenant_id = ?")->execute($params);
                flash('success', count($ids) . ' película(s) eliminada(s).');
                break;

            case 'publish':
                $params = array_merge($ids, [$tenantId]);
                $pdo->prepare("UPDATE films SET status = 'published' WHERE id IN ({$placeholders}) AND tenant_id = ?")->execute($params);
                flash('success', count($ids) . ' película(s) publicada(s).');
                break;

            case 'draft':
                $params = array_merge($ids, [$tenantId]);
                $pdo->prepare("UPDATE films SET status = 'draft' WHERE id IN ({$placeholders}) AND tenant_id = ?")->execute($params);
                flash('success', count($ids) . ' película(s) pasada(s) a borrador.');
                break;
        }

        header('Location: ' . film_admin_url());
        exit;
    }
}
