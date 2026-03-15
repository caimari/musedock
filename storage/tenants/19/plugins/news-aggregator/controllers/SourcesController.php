<?php

namespace NewsAggregator\Controllers;

use Screenart\Musedock\Services\TenantManager;
use Screenart\Musedock\View;
use NewsAggregator\Models\Source;
use NewsAggregator\Services\FetcherFactory;

/**
 * Controlador de Fuentes del News Aggregator
 */
class SourcesController
{
    /**
     * Listar fuentes
     */
    public function index()
    {
        $tenantId = TenantManager::currentTenantId();
        $sources = Source::all($tenantId);

        return View::renderTenantAdmin('plugins.news-aggregator.sources.index', [
            'sources' => $sources
        ]);
    }

    /**
     * Formulario de crear fuente
     */
    public function create()
    {
        return View::renderTenantAdmin('plugins.news-aggregator.sources.form', [
            'source' => null,
            'feeds' => [],
            'action' => 'create'
        ]);
    }

    /**
     * Guardar nueva fuente
     */
    public function store()
    {
        $tenantId = TenantManager::currentTenantId();
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
            'show_attribution' => isset($_POST['show_attribution'])
        ];

        try {
            $id = Source::create($data);

            // Guardar feeds para fuentes RSS
            if (($data['source_type'] === 'rss') && !empty($_POST['feeds'])) {
                Source::saveFeeds($id, $_POST['feeds']);
            } elseif ($data['source_type'] === 'rss' && !empty($data['url'])) {
                // Fuente directa con URL: crear feed automáticamente
                Source::saveFeeds($id, [['name' => $data['name'], 'url' => $data['url']]]);
            }

            $_SESSION['flash_success'] = $lang['sources_created'];
            header('Location: /admin/plugins/news-aggregator/sources');
            exit;
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            header('Location: /admin/plugins/news-aggregator/sources/create');
            exit;
        }
    }

    /**
     * Formulario de editar fuente
     */
    public function edit(int $id)
    {
        $source = Source::find($id);

        if (!$source) {
            header('Location: /admin/plugins/news-aggregator/sources');
            exit;
        }

        $feeds = Source::getFeeds($id);

        return View::renderTenantAdmin('plugins.news-aggregator.sources.form', [
            'source' => $source,
            'feeds' => $feeds,
            'action' => 'edit'
        ]);
    }

    /**
     * Actualizar fuente
     */
    public function update(int $id)
    {
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
            'show_attribution' => isset($_POST['show_attribution'])
        ];

        try {
            Source::update($id, $data);

            // Guardar feeds para fuentes RSS
            if (($data['source_type'] === 'rss') && !empty($_POST['feeds'])) {
                Source::saveFeeds($id, $_POST['feeds']);
            } elseif ($data['source_type'] === 'rss' && !empty($data['url'])) {
                Source::saveFeeds($id, [['name' => $data['name'], 'url' => $data['url']]]);
            }

            $_SESSION['flash_success'] = $lang['sources_updated'];
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: /admin/plugins/news-aggregator/sources');
        exit;
    }

    /**
     * Eliminar fuente
     */
    public function destroy(int $id)
    {
        $lang = $this->loadLang();

        try {
            Source::delete($id);
            $_SESSION['flash_success'] = $lang['sources_deleted'];
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: /admin/plugins/news-aggregator/sources');
        exit;
    }

    /**
     * Ejecutar fetch manualmente
     */
    public function fetch(int $id)
    {
        $tenantId = TenantManager::currentTenantId();
        $source = Source::find($id);
        $lang = $this->loadLang();

        if (!$source || $source->tenant_id != $tenantId) {
            $_SESSION['flash_error'] = $lang['error_source_not_found'];
            header('Location: /admin/plugins/news-aggregator/sources');
            exit;
        }

        $result = FetcherFactory::fetch($tenantId, $source);

        if ($result['success']) {
            $_SESSION['flash_success'] = str_replace(':count', $result['count'], $lang['sources_fetch_success']);
        } else {
            $_SESSION['flash_error'] = str_replace(':error', $result['error'], $lang['sources_fetch_error']);
        }

        header('Location: /admin/plugins/news-aggregator/sources');
        exit;
    }

    /**
     * Cargar traducciones
     */
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
