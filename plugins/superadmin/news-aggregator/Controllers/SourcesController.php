<?php

namespace NewsAggregatorAdmin\Controllers;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use NewsAggregator\Models\Source;
use NewsAggregator\Services\FetcherFactory;

class SourcesController
{
    private function requireTenantId(): int
    {
        $tenantId = (int) ($_GET['tenant'] ?? $_POST['tenant_id'] ?? 0);
        if (!$tenantId) {
            flash('error', 'Selecciona un tenant primero.');
            header('Location: /musedock/news-aggregator');
            exit;
        }
        return $tenantId;
    }

    private function getTenantsWithPlugin(): array
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query("
                SELECT t.id, t.domain, t.name
                FROM tenants t
                INNER JOIN tenant_plugins tp ON t.id = tp.tenant_id
                WHERE tp.slug = 'news-aggregator' AND tp.active = 1
                ORDER BY t.domain
            ");
            return $stmt->fetchAll(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function index()
    {
        $tenantId = $this->requireTenantId();
        $sources = Source::all($tenantId);

        return View::renderSuperadmin('plugins.news-aggregator.sources.index', [
            'sources' => $sources,
            'tenantId' => $tenantId,
            'tenants' => $this->getTenantsWithPlugin(),
        ]);
    }

    public function create()
    {
        $tenantId = $this->requireTenantId();

        return View::renderSuperadmin('plugins.news-aggregator.sources.form', [
            'source' => null,
            'feeds' => [],
            'action' => 'create',
            'tenantId' => $tenantId,
            'tenants' => $this->getTenantsWithPlugin(),
        ]);
    }

    public function store()
    {
        $tenantId = $this->requireTenantId();
        $lang = $this->loadLang();

        $data = [
            'tenant_id' => $tenantId,
            'name' => $_POST['name'] ?? '',
            'source_type' => $_POST['source_type'] ?? 'rss',
            'url' => $_POST['url'] ?? null,
            'api_key' => $_POST['api_key'] ?? null,
            'keywords' => $_POST['keywords'] ?? null,
            'media_keywords_filter' => null,
            'categories' => $_POST['categories'] ?? null,
            'language' => $_POST['language'] ?? null,
            'fetch_interval' => (int) ($_POST['fetch_interval'] ?? 3600),
            'max_articles' => (int) ($_POST['max_articles'] ?? 10),
            'enabled' => isset($_POST['enabled']),
            'attribution_mode' => $_POST['attribution_mode'] ?? 'rewrite',
            'exclude_rewrite' => isset($_POST['exclude_rewrite']),
            'processing_type' => $_POST['processing_type'] ?? 'direct',
            'min_sources_for_publish' => (int) ($_POST['min_sources_for_publish'] ?? 2),
            'excluded_tags' => !empty($_POST['excluded_tags']) ? trim($_POST['excluded_tags']) : null,
            'required_tags' => !empty($_POST['required_tags']) ? trim($_POST['required_tags']) : null,
            'show_attribution' => isset($_POST['show_attribution']),
        ];

        try {
            $id = Source::create($data);

            if (($data['source_type'] === 'rss') && !empty($_POST['feeds'])) {
                Source::saveFeeds($id, $_POST['feeds']);
            } elseif ($data['source_type'] === 'rss' && !empty($data['url'])) {
                Source::saveFeeds($id, [['name' => $data['name'], 'url' => $data['url']]]);
            }

            flash('success', $lang['sources_created'] ?? 'Fuente creada correctamente.');
            header("Location: /musedock/news-aggregator/sources?tenant={$tenantId}");
            exit;
        } catch (\Exception $e) {
            flash('error', $e->getMessage());
            header("Location: /musedock/news-aggregator/sources/create?tenant={$tenantId}");
            exit;
        }
    }

    public function edit(int $id)
    {
        $tenantId = $this->requireTenantId();
        $source = Source::find($id);

        if (!$source) {
            header("Location: /musedock/news-aggregator/sources?tenant={$tenantId}");
            exit;
        }

        $feeds = Source::getFeeds($id);

        return View::renderSuperadmin('plugins.news-aggregator.sources.form', [
            'source' => $source,
            'feeds' => $feeds,
            'action' => 'edit',
            'tenantId' => $tenantId,
            'tenants' => $this->getTenantsWithPlugin(),
        ]);
    }

    public function update(int $id)
    {
        $tenantId = $this->requireTenantId();
        $lang = $this->loadLang();

        $data = [
            'name' => $_POST['name'] ?? '',
            'source_type' => $_POST['source_type'] ?? 'rss',
            'url' => $_POST['url'] ?? null,
            'api_key' => $_POST['api_key'] ?? null,
            'keywords' => $_POST['keywords'] ?? null,
            'media_keywords_filter' => null,
            'categories' => $_POST['categories'] ?? null,
            'language' => $_POST['language'] ?? null,
            'fetch_interval' => (int) ($_POST['fetch_interval'] ?? 3600),
            'max_articles' => (int) ($_POST['max_articles'] ?? 10),
            'enabled' => isset($_POST['enabled']),
            'attribution_mode' => $_POST['attribution_mode'] ?? 'rewrite',
            'exclude_rewrite' => isset($_POST['exclude_rewrite']),
            'processing_type' => $_POST['processing_type'] ?? 'direct',
            'min_sources_for_publish' => (int) ($_POST['min_sources_for_publish'] ?? 2),
            'excluded_tags' => !empty($_POST['excluded_tags']) ? trim($_POST['excluded_tags']) : null,
            'required_tags' => !empty($_POST['required_tags']) ? trim($_POST['required_tags']) : null,
            'show_attribution' => isset($_POST['show_attribution']),
        ];

        try {
            Source::update($id, $data);

            if (($data['source_type'] === 'rss') && !empty($_POST['feeds'])) {
                Source::saveFeeds($id, $_POST['feeds']);
            } elseif ($data['source_type'] === 'rss' && !empty($data['url'])) {
                Source::saveFeeds($id, [['name' => $data['name'], 'url' => $data['url']]]);
            }

            flash('success', $lang['sources_updated'] ?? 'Fuente actualizada correctamente.');
        } catch (\Exception $e) {
            flash('error', $e->getMessage());
        }

        header("Location: /musedock/news-aggregator/sources?tenant={$tenantId}");
        exit;
    }

    public function destroy(int $id)
    {
        $tenantId = $this->requireTenantId();
        $lang = $this->loadLang();

        try {
            Source::delete($id);
            flash('success', $lang['sources_deleted'] ?? 'Fuente eliminada correctamente.');
        } catch (\Exception $e) {
            flash('error', $e->getMessage());
        }

        header("Location: /musedock/news-aggregator/sources?tenant={$tenantId}");
        exit;
    }

    public function fetch(int $id)
    {
        $tenantId = $this->requireTenantId();
        $source = Source::find($id);
        $lang = $this->loadLang();

        if (!$source || $source->tenant_id != $tenantId) {
            flash('error', $lang['error_source_not_found'] ?? 'Fuente no encontrada.');
            header("Location: /musedock/news-aggregator/sources?tenant={$tenantId}");
            exit;
        }

        $result = FetcherFactory::fetch($tenantId, $source);

        if ($result['success']) {
            flash('success', str_replace(':count', $result['count'], $lang['sources_fetch_success'] ?? ':count noticias capturadas.'));
        } else {
            flash('error', str_replace(':error', $result['error'], $lang['sources_fetch_error'] ?? 'Error: :error'));
        }

        header("Location: /musedock/news-aggregator/sources?tenant={$tenantId}");
        exit;
    }

    private function loadLang(): array
    {
        $locale = function_exists('detectLanguage') ? detectLanguage() : 'es';
        $langFile = __DIR__ . '/../lang/' . $locale . '.php';

        if (!file_exists($langFile)) {
            $langFile = __DIR__ . '/../lang/es.php';
        }

        return require $langFile;
    }
}
