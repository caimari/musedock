<?php

namespace FilmLibrary\Controllers\Tenant;

use Screenart\Musedock\Database;
use Screenart\Musedock\Services\TenantManager;

class FilmSettingsController
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

        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT settings FROM tenant_plugins WHERE tenant_id = ? AND slug = 'film-library' LIMIT 1");
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $settings = $row ? (json_decode($row['settings'] ?? '{}', true) ?: []) : [];

        echo film_render_admin('tenant.settings.index', [
            'title'    => 'Configuración — Film Library',
            'settings' => $settings,
        ]);
    }

    public function save()
    {
        $this->checkPermission('films.edit');
        $tenantId = $this->getTenantId();

        $settings = [
            'tmdb_api_key'       => trim($_POST['tmdb_api_key'] ?? ''),
            'tmdb_language'      => trim($_POST['tmdb_language'] ?? 'es-ES'),
            'films_per_page'     => max(1, (int)($_POST['films_per_page'] ?? 12)),
            'poster_base_url'    => trim($_POST['poster_base_url'] ?? 'https://image.tmdb.org/t/p/w500'),
            'image_source'       => in_array($_POST['image_source'] ?? '', ['tmdb', 'local', 'local_fallback']) ? $_POST['image_source'] : 'tmdb',
            'api_enabled'        => (int)($_POST['api_enabled'] ?? 1),
            'show_home_carousel' => (int)($_POST['show_home_carousel'] ?? 0),
            'home_carousel_count'=> max(1, min(50, (int)($_POST['home_carousel_count'] ?? 12))),
            'home_carousel_title'=> trim($_POST['home_carousel_title'] ?? 'Cartelera'),
        ];

        $pdo = Database::connect();
        $stmt = $pdo->prepare("UPDATE tenant_plugins SET settings = ? WHERE tenant_id = ? AND slug = 'film-library'");
        $stmt->execute([json_encode($settings), $tenantId]);

        // Invalidate HTML cache for home page (carousel change)
        if (class_exists('\Screenart\Musedock\Cache\HtmlCache')) {
            \Screenart\Musedock\Cache\HtmlCache::invalidateByTag('home', $tenantId);
        }
        // Also invalidate sitemap
        if (function_exists('film_invalidate_sitemap')) {
            film_invalidate_sitemap();
        }

        flash('success', 'Configuración guardada correctamente.');
        header('Location: ' . film_admin_url('settings'));
        exit;
    }
}
