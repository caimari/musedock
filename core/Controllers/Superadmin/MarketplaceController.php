<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Services\MarketplaceService;
use Screenart\Musedock\Traits\RequiresPermission;

/**
 * Controlador del Marketplace
 * Permite buscar, instalar y gestionar módulos, plugins y temas
 */
class MarketplaceController
{
    use RequiresPermission;

    /**
     * Verifica si el marketplace está habilitado
     */
    private function checkMarketplaceEnabled(): void
    {
        $enabled = getenv('MARKETPLACE_ENABLED') ?: 'false';

        if ($enabled === 'false' || $enabled === '0' || !$enabled) {
            flash('warning', 'El Marketplace está deshabilitado. Contacta al administrador para habilitarlo.');
            header('Location: /musedock');
            exit;
        }
    }

    /**
     * Página principal del marketplace
     */
    public function index()
    {
        SessionSecurity::startSession();
        $this->checkMarketplaceEnabled();
        $this->checkPermission('modules.manage');

        $type = $_GET['type'] ?? null;
        $featured = MarketplaceService::getFeatured($type, 6);
        $popular = MarketplaceService::getPopular($type, 12);
        $categories = MarketplaceService::getCategories($type);

        return View::renderSuperadmin('marketplace.index', [
            'title' => 'Marketplace',
            'featured' => $featured,
            'popular' => $popular,
            'categories' => $categories,
            'current_type' => $type,
        ]);
    }

    /**
     * Buscar en el marketplace
     */
    public function search()
    {
        SessionSecurity::startSession();
        $this->checkMarketplaceEnabled();
        $this->checkPermission('modules.manage');

        $query = trim($_GET['q'] ?? '');
        $type = $_GET['type'] ?? null;
        $category = $_GET['category'] ?? null;
        $sort = $_GET['sort'] ?? 'popular';
        $page = max(1, (int)($_GET['page'] ?? 1));

        $results = MarketplaceService::search($query, $type, [
            'category' => $category,
            'sort' => $sort,
            'page' => $page,
        ]);

        $categories = MarketplaceService::getCategories($type);

        return View::renderSuperadmin('marketplace.search', [
            'title' => 'Buscar en Marketplace',
            'query' => $query,
            'results' => $results,
            'categories' => $categories,
            'current_type' => $type,
            'current_category' => $category,
            'current_sort' => $sort,
            'current_page' => $page,
        ]);
    }

    /**
     * Ver detalles de un item
     */
    public function show(string $type, string $slug)
    {
        SessionSecurity::startSession();
        $this->checkMarketplaceEnabled();
        $this->checkPermission('modules.manage');

        // Validar tipo
        $allowedTypes = ['module', 'plugin', 'theme'];
        if (!in_array($type, $allowedTypes, true)) {
            flash('error', 'Tipo de item inválido.');
            header('Location: /musedock/marketplace');
            exit;
        }

        // Validar slug
        if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
            flash('error', 'Slug inválido.');
            header('Location: /musedock/marketplace');
            exit;
        }

        $item = MarketplaceService::getItemDetails($slug, $type);

        if (!$item) {
            flash('error', 'Item no encontrado.');
            header('Location: /musedock/marketplace');
            exit;
        }

        // Verificar si ya está instalado
        $installed = MarketplaceService::getInstalled($type);
        $isInstalled = false;
        $installedVersion = null;

        foreach ($installed as $inst) {
            if ($inst['slug'] === $slug) {
                $isInstalled = true;
                $installedVersion = $inst['version'];
                break;
            }
        }

        return View::renderSuperadmin('marketplace.show', [
            'title' => $item['name'],
            'item' => $item,
            'type' => $type,
            'is_installed' => $isInstalled,
            'installed_version' => $installedVersion,
        ]);
    }

    /**
     * Instalar un item (AJAX)
     */
    public function install()
    {
        SessionSecurity::startSession();
        $this->checkMarketplaceEnabled();
        $this->checkPermission('modules.manage');

        header('Content-Type: application/json');

        // Validar CSRF
        if (!verify_csrf_token($_POST['_csrf'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Token inválido']);
            exit;
        }

        $slug = trim($_POST['slug'] ?? '');
        $type = trim($_POST['type'] ?? '');

        // Validaciones
        if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
            echo json_encode(['success' => false, 'error' => 'Slug inválido']);
            exit;
        }

        $allowedTypes = ['module', 'plugin', 'theme'];
        if (!in_array($type, $allowedTypes, true)) {
            echo json_encode(['success' => false, 'error' => 'Tipo inválido']);
            exit;
        }

        $result = MarketplaceService::install($slug, $type);

        echo json_encode($result);
        exit;
    }

    /**
     * Desinstalar un item
     */
    public function uninstall()
    {
        SessionSecurity::startSession();
        $this->checkMarketplaceEnabled();
        $this->checkPermission('modules.manage');

        // Validar CSRF
        if (!verify_csrf_token($_POST['_csrf'] ?? '')) {
            flash('error', 'Token de seguridad inválido.');
            header('Location: /musedock/marketplace/installed');
            exit;
        }

        $slug = trim($_POST['slug'] ?? '');
        $type = trim($_POST['type'] ?? '');

        // Validaciones
        if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
            flash('error', 'Slug inválido.');
            header('Location: /musedock/marketplace/installed');
            exit;
        }

        $allowedTypes = ['module', 'plugin', 'theme'];
        if (!in_array($type, $allowedTypes, true)) {
            flash('error', 'Tipo inválido.');
            header('Location: /musedock/marketplace/installed');
            exit;
        }

        $result = MarketplaceService::uninstall($slug, $type);

        if ($result['success']) {
            flash('success', $result['message']);
        } else {
            flash('error', $result['message']);
        }

        header('Location: /musedock/marketplace/installed');
        exit;
    }

    /**
     * Ver items instalados desde el marketplace
     */
    public function installed()
    {
        SessionSecurity::startSession();
        $this->checkMarketplaceEnabled();
        $this->checkPermission('modules.manage');

        $type = $_GET['type'] ?? null;
        $items = MarketplaceService::getInstalled($type);
        $updates = MarketplaceService::checkItemUpdates();

        return View::renderSuperadmin('marketplace.installed', [
            'title' => 'Items Instalados',
            'items' => $items,
            'updates' => $updates,
            'current_type' => $type,
        ]);
    }

    /**
     * Actualizar un item
     */
    public function update()
    {
        SessionSecurity::startSession();
        $this->checkMarketplaceEnabled();
        $this->checkPermission('modules.manage');

        header('Content-Type: application/json');

        // Validar CSRF
        if (!verify_csrf_token($_POST['_csrf'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Token inválido']);
            exit;
        }

        $slug = trim($_POST['slug'] ?? '');
        $type = trim($_POST['type'] ?? '');

        // Validaciones
        if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
            echo json_encode(['success' => false, 'error' => 'Slug inválido']);
            exit;
        }

        $allowedTypes = ['module', 'plugin', 'theme'];
        if (!in_array($type, $allowedTypes, true)) {
            echo json_encode(['success' => false, 'error' => 'Tipo inválido']);
            exit;
        }

        $result = MarketplaceService::update($slug, $type);

        echo json_encode($result);
        exit;
    }

    /**
     * Página para desarrolladores - Publicar items
     */
    public function developer()
    {
        SessionSecurity::startSession();
        $this->checkMarketplaceEnabled();
        $this->checkPermission('settings.edit');

        return View::renderSuperadmin('marketplace.developer', [
            'title' => 'Centro de Desarrolladores',
        ]);
    }
}
