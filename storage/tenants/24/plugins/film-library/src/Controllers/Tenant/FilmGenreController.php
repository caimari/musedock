<?php

namespace FilmLibrary\Controllers\Tenant;

use FilmLibrary\Models\FilmGenre;
use Screenart\Musedock\Database;
use Screenart\Musedock\Services\TenantManager;

class FilmGenreController
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

    public function index()
    {
        $this->checkPermission('films.view');
        $tenantId = $this->getTenantId();

        $genres = FilmGenre::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->get();

        echo film_render_admin('tenant.genres.index', [
            'title'  => 'Géneros',
            'genres' => $genres,
        ]);
    }

    public function create()
    {
        $this->checkPermission('films.create');
        echo film_render_admin('tenant.genres.form', [
            'title'  => 'Crear Género',
            'genre'  => null,
            'isEdit' => false,
        ]);
    }

    public function store()
    {
        $this->checkPermission('films.create');
        $tenantId = $this->getTenantId();

        $data = $_POST;
        $data['tenant_id'] = $tenantId;
        $data['slug'] = film_slugify($data['name'] ?? '');

        FilmGenre::create($data);

        flash('success', 'Género creado correctamente.');
        header('Location: ' . film_admin_url('genres'));
        exit;
    }

    public function edit($id)
    {
        $this->checkPermission('films.edit');
        $tenantId = $this->getTenantId();

        $genre = FilmGenre::query()->where('id', (int)$id)->where('tenant_id', $tenantId)->first();
        if (!$genre) {
            flash('error', 'Género no encontrado.');
            header('Location: ' . film_admin_url('genres'));
            exit;
        }

        echo film_render_admin('tenant.genres.form', [
            'title'  => 'Editar: ' . $genre->name,
            'genre'  => $genre,
            'isEdit' => true,
        ]);
    }

    public function update($id)
    {
        $this->checkPermission('films.edit');
        $tenantId = $this->getTenantId();

        $genre = FilmGenre::query()->where('id', (int)$id)->where('tenant_id', $tenantId)->first();
        if (!$genre) {
            flash('error', 'Género no encontrado.');
            header('Location: ' . film_admin_url('genres'));
            exit;
        }

        $data = $_POST;
        unset($data['_token'], $data['_method']);

        if (!empty($data['name']) && $data['name'] !== $genre->name) {
            $data['slug'] = film_slugify($data['name']);
        }

        $genre->fill($data);
        $genre->save();

        flash('success', 'Género actualizado correctamente.');
        header('Location: ' . film_admin_url('genres'));
        exit;
    }

    public function destroy($id)
    {
        $this->checkPermission('films.delete');
        $tenantId = $this->getTenantId();

        $genre = FilmGenre::query()->where('id', (int)$id)->where('tenant_id', $tenantId)->first();
        if ($genre) {
            $pdo = Database::connect();
            $pdo->prepare("DELETE FROM film_genre_pivot WHERE genre_id = ?")->execute([$genre->id]);
            $pdo->prepare("DELETE FROM film_genres WHERE id = ?")->execute([$genre->id]);
            flash('success', 'Género eliminado.');
        }

        header('Location: ' . film_admin_url('genres'));
        exit;
    }
}
