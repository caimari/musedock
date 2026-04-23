<?php

namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\View;
use Screenart\Musedock\Models\Page;
use Screenart\Musedock\Models\PageMeta;
use Screenart\Musedock\Models\Admin;
use Screenart\Musedock\Services\TenantManager;
use Screenart\Musedock\Requests\PageRequest;
use Screenart\Musedock\Models\PageTranslation;
use Screenart\Musedock\Database;
use Screenart\Musedock\Services\AuditLogger;
use Screenart\Musedock\Cache\HtmlCache;
use Screenart\Musedock\Traits\RequiresPermission;

class PageController
{
    use RequiresPermission;

    /**
     * Verificar si el usuario actual tiene un permiso específico
     * Si no lo tiene, redirige con mensaje de error
     */
    private function checkPermission(string $permission): void
    {
        if (!userCan($permission)) {
            flash('error', __('pages.error_no_permission'));
            header('Location: ' . admin_url('dashboard'));
            exit;
        }
    }

    /**
     * Listado de páginas del tenant
     */
    public function index()
    {
        $this->checkPermission('pages.view');

        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('pages.error_tenant_not_identified'));
            header('Location: ' . admin_url('dashboard'));
            exit;
        }

        // Capturar parámetros de búsqueda y paginación
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        // Limitar longitud de búsqueda a 255 caracteres
        if (strlen($search) > 255) {
            $search = substr($search, 0, 255);
        }

        $perPage = isset($_GET['perPage']) ? intval($_GET['perPage']) : 10;
        $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

        // Capturar parámetros de ordenamiento (por defecto: título ASC como WordPress)
        $orderBy = isset($_GET['orderby']) ? $_GET['orderby'] : 'title';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'DESC' ? 'DESC' : 'ASC';

        // Validar columnas permitidas para ordenamiento
        $allowedColumns = ['title', 'status', 'published_at', 'created_at', 'updated_at'];
        if (!in_array($orderBy, $allowedColumns)) {
            $orderBy = 'title';
        }

        // Consulta de páginas SOLO del tenant actual (excluyendo papelera)
        $query = Page::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw("(status != ? OR status IS NULL)", ['trash'])
            ->orderBy($orderBy, $order);

        // Aplicar búsqueda si existe
        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $query->whereRaw("
                (title LIKE ? OR slug LIKE ? OR content LIKE ?)
            ", [$searchTerm, $searchTerm, $searchTerm]);
        }

        // Paginación
        $maxLimit = 1000;
        if ($perPage == -1) {
            $totalCount = $query->count();
            if ($totalCount > $maxLimit) {
                flash('warning', str_replace(['{totalCount}', '{maxLimit}'], [$totalCount, $maxLimit], __('pages.warning_limit_results')));
                $pages = $query->limit($maxLimit)->get();
            } else {
                $pages = $query->get();
            }
            $pagination = [
                'total' => count($pages),
                'per_page' => count($pages),
                'current_page' => 1,
                'last_page' => 1,
                'from' => 1,
                'to' => count($pages),
                'items' => $pages
            ];
        } else {
            $pagination = $query->paginate($perPage, $currentPage);
            $pages = $pagination['items'] ?? [];
        }

        // Procesar páginas y cargar datos adicionales
        $processedPages = [];

        try {
            $pdo = Database::connect();

            foreach ($pages as $pageData) {
                $page = ($pageData instanceof Page) ? $pageData : new Page((array) $pageData);

                // Cargar visibilidad
                $stmt = $pdo->prepare("SELECT visibility FROM pages WHERE id = ? LIMIT 1");
                $stmt->execute([$page->id]);
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($result && isset($result['visibility'])) {
                    $page->visibility = $result['visibility'];
                }

                // Cargar meta (plantilla)
                $stmt = $pdo->prepare("SELECT meta_key, meta_value FROM page_meta WHERE page_id = ? AND meta_key = 'page_template'");
                $stmt->execute([$page->id]);
                $metaResult = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($metaResult) {
                    $page->meta = $page->meta ?? new \stdClass();
                    $page->meta->page_template = $metaResult['meta_value'];
                }

                $processedPages[] = $page;
            }
        } catch (\Exception $e) {
            error_log("Error al cargar datos adicionales: " . $e->getMessage());
            $processedPages = array_map(fn($row) => ($row instanceof Page) ? $row : new Page((array) $row), $pages);
        }

        // Cargar autores (admins del tenant)
        $authors = [];
        foreach ($processedPages as $page) {
            $userId = $page->user_id ?? null;
            if ($userId && !isset($authors[$userId])) {
                $authors[$userId] = Admin::find($userId);
            }
        }

        // Buscar la página marcada como inicio del tenant
        $homepageId = Page::where('is_homepage', 1)
                          ->where('tenant_id', $tenantId)
                          ->value('id');

        return View::renderTenantAdmin('pages.index', [
            'title'       => __('pages.list_title'),
            'pages'       => $processedPages,
            'authors'     => $authors,
            'search'      => $search,
            'pagination'  => $pagination,
            'homepageId'  => $homepageId,
            'orderBy'     => $orderBy,
            'order'       => $order,
        ]);
    }

    /**
     * Formulario para crear nueva página
     */
    public function create()
    {
        $this->checkPermission('pages.create');

        $availableTemplates = get_page_templates();
        $currentPageTemplate = 'page.blade.php';

        return View::renderTenantAdmin('pages.create', [
            'title' => __('pages.create_title'),
            'Page'  => new Page(),
            'isNew' => true,
            'baseUrl' => $_SERVER['HTTP_HOST'],
            'availableTemplates' => $availableTemplates,
            'currentPageTemplate' => $currentPageTemplate,
        ]);
    }

    /**
     * Guardar nueva página
     */
    public function store()
    {
        $this->checkPermission('pages.create');

        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('pages.error_tenant_not_identified'));
            header('Location: ' . admin_url('pages'));
            exit;
        }

        $data = $_POST;
        unset($data['_token'], $data['_csrf']);

        // Asignar tenant_id actual
        $data['tenant_id'] = $tenantId;

        // Usuario actual del tenant (admin)
        $data['user_id'] = $_SESSION['admin']['id'] ?? null;
        $data['user_type'] = 'admin';

        // Manejo de checkboxes
        $data['show_slider'] = isset($data['show_slider']) ? 1 : 0;
        $data['hide_title'] = isset($data['hide_title']) ? 1 : 0;

        // Visibilidad
        $data['visibility'] = $data['visibility'] ?? 'public';
        if (!in_array($data['visibility'], ['public', 'private', 'members'])) {
            $data['visibility'] = 'public';
        }

        // Procesar subida de imagen del slider
        if ($_FILES && isset($_FILES['slider_image']) && $_FILES['slider_image']['error'] == 0) {
            $uploadResult = $this->processSliderImageUpload($_FILES['slider_image']);
            if (isset($uploadResult['error'])) {
                flash('error', __('pages.error_image_upload') . ': ' . $uploadResult['error']);
                header('Location: ' . admin_url('pages/create'));
                exit;
            }
            $data['slider_image'] = $uploadResult['path'];
        }

        // Estado por defecto
        if (!isset($data['status']) || !in_array($data['status'], ['draft', 'published'])) {
            $data['status'] = 'published';
        }

        $data = self::processFormData($data);

        $errors = PageRequest::validate($data);

        if (!empty($errors)) {
            flash('error', implode('<br>', $errors));
            header('Location: ' . admin_url('pages/create'));
            exit;
        }

        // Verificar si es la primera página (sin homepage existente)
        $pdo = Database::connect();
        $checkHomepage = $pdo->prepare("SELECT COUNT(*) FROM pages WHERE is_homepage = 1 AND tenant_id = ?");
        $checkHomepage->execute([$tenantId]);
        $hasHomepage = (int)$checkHomepage->fetchColumn() > 0;
        $isFirstPage = !$hasHomepage;

        // Crear la página
        $page = Page::create($data);

        // Si es la primera página, marcarla como homepage automáticamente
        if ($isFirstPage) {
            try {
                $setHomepage = $pdo->prepare("UPDATE pages SET is_homepage = 1 WHERE id = ?");
                $setHomepage->execute([$page->id]);
            } catch (\Exception $e) {
                error_log("Error al establecer primera página como homepage: " . $e->getMessage());
            }
        }

        // Audit log
        AuditLogger::log('page.created', 'page', $page->id, [
            'title' => $data['title'] ?? '',
            'slug' => $data['slug'] ?? '',
            'status' => $data['status'] ?? 'draft',
            'tenant_id' => $tenantId
        ]);

        // Crear slug con tenant_id
        try {
            $prefix = $data['prefix'] ?? 'p';
            $insertStmt = $pdo->prepare("INSERT INTO slugs (module, reference_id, slug, tenant_id, prefix) VALUES (?, ?, ?, ?, ?)");
            $insertStmt->execute(['pages', $page->id, $data['slug'], $tenantId, $prefix]);
        } catch (\Exception $e) {
            error_log("ERROR AL CREAR SLUG: " . $e->getMessage());
        }

        // Crear primera revisión de la página
        try {
            \Screenart\Musedock\Models\PageRevision::createFromPage($page, 'initial', __('pages.revision_initial_summary'));
        } catch (\Exception $e) {
            error_log("Error al crear revisión inicial: " . $e->getMessage());
        }

        // Invalidar caché del sitemap
        if (($data['status'] ?? '') === 'published') {
            \Blog\Controllers\Frontend\SitemapController::invalidateCache($tenantId);
        }

        // Invalidar y regenerar HTML cache
        HtmlCache::onPageSaved([
            'slug'        => $data['slug'] ?? '',
            'prefix'      => $data['prefix'] ?? page_prefix(),
            'is_homepage' => $isFirstPage ? 1 : 0,
            'status'      => $data['status'] ?? 'published',
        ], $tenantId);

        flash('success', __('pages.success_created'));
        header('Location: ' . admin_url("pages/{$page->id}/edit"));
        exit;
    }

    /**
     * Formulario para editar página
     */
    public function edit($id)
    {
        $this->checkPermission('pages.edit');

        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('pages.error_tenant_not_identified'));
            header('Location: ' . admin_url('pages'));
            exit;
        }

        // Limpiar datos 'old'
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        unset($_SESSION['_old_input']);

        // Buscar página SOLO del tenant actual
        $page = Page::where('id', $id)
                    ->where('tenant_id', $tenantId)
                    ->first();

        if (!$page) {
            flash('error', __('pages.error_not_found_or_no_permission'));
            header('Location: ' . admin_url('pages'));
            exit;
        }

        // Convertir a objeto Page si es array o stdClass
        if (is_array($page) || $page instanceof \stdClass) {
            $page = new Page($page);
        }

        // Cargar visibilidad
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT visibility FROM pages WHERE id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result && isset($result['visibility'])) {
                $page->visibility = $result['visibility'];
            } else {
                $page->visibility = 'public';
            }
        } catch (\Exception $e) {
            error_log("Error al obtener visibility: " . $e->getMessage());
            $page->visibility = 'public';
        }

        // Formatear fechas
        $page->created_at_formatted = 'Desconocido';
        $page->updated_at_formatted = 'Desconocido';

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT created_at, updated_at FROM pages WHERE id = ?");
            $stmt->execute([$id]);
            $dates = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($dates) {
                $dateFormat = setting('date_format', 'd/m/Y');
                $timeFormat = setting('time_format', 'H:i');
                $dateTimeFormat = $dateFormat . ' ' . $timeFormat;

                if (!empty($dates['created_at'])) {
                    $timestamp_created = strtotime($dates['created_at']);
                    if ($timestamp_created !== false) {
                        $page->created_at_formatted = date($dateTimeFormat, $timestamp_created);
                    }
                }

                if (!empty($dates['updated_at'])) {
                    $timestamp_updated = strtotime($dates['updated_at']);
                    if ($timestamp_updated !== false) {
                        $page->updated_at_formatted = date($dateTimeFormat, $timestamp_updated);
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Error al formatear fechas: " . $e->getMessage());
        }

        // Preparar published_at
        if ($page->published_at && !$page->published_at instanceof \DateTimeInterface) {
            try {
                $page->published_at = new \DateTime($page->published_at);
            } catch (\Exception $e) {
                $page->published_at = null;
            }
        }

        // Traducciones
        $locales = getAvailableLocales();
        $translatedLocales = [];
        $translations = PageTranslation::where('page_id', $id)->get();

        foreach ($translations as $t) {
            $translatedLocales[$t->locale] = true;
        }

        // Plantillas
        $availableTemplates = get_page_templates();
        $currentPageTemplate = PageMeta::getMeta($id, 'page_template', 'page.blade.php');

        return View::renderTenantAdmin('pages.edit', [
            'title'               => __('pages.edit_title') . ': ' . e($page->title),
            'Page'                => $page,
            'locales'             => $locales,
            'translatedLocales'   => $translatedLocales,
            'baseUrl'             => $_SERVER['HTTP_HOST'] ?? 'localhost',
            'availableTemplates'  => $availableTemplates,
            'currentPageTemplate' => $currentPageTemplate,
        ]);
    }

    /**
     * Actualizar página
     */
    public function update($id)
    {
        $this->checkPermission('pages.edit');

        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('pages.error_tenant_not_identified'));
            header('Location: ' . admin_url('pages'));
            exit;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        // Buscar página SOLO del tenant actual
        $page = Page::where('id', $id)
                    ->where('tenant_id', $tenantId)
                    ->first();

        if (!$page) {
            flash('error', __('pages.error_not_found_or_no_permission'));
            header('Location: ' . admin_url('pages'));
            exit;
        }

        // Convertir a objeto Page si es array o stdClass
        if (is_array($page) || $page instanceof \stdClass) {
            $page = new Page($page);
        }

        // Guardar estado anterior para detectar cambios
        $oldTitle = $page->title;
        $oldContent = $page->content;
        $oldStatus = $page->status;

        $rawData = $_POST;
        $data = $rawData;
        unset($data['_token'], $data['_csrf'], $data['_method']);

        // Página de inicio
        $makeHomepage = isset($rawData['is_homepage']) && $rawData['is_homepage'] == '1';
        $data['is_homepage'] = $makeHomepage ? 1 : 0;

        // Manejo de checkboxes
        $data['show_slider'] = isset($data['show_slider']) ? 1 : 0;
        $data['hide_title'] = isset($data['hide_title']) ? 1 : 0;

        // Visibilidad
        $data['visibility'] = $data['visibility'] ?? 'public';
        if (!in_array($data['visibility'], ['public', 'private', 'members'])) {
            $data['visibility'] = 'public';
        }

        // Procesar imagen del slider (ahora usa URL del Media Manager)
        // El campo slider_image viene directamente del input hidden con la URL seleccionada
        $sliderImage = $data['slider_image'] ?? '';

        // Si está vacío, se guarda como null
        if (empty($sliderImage)) {
            $data['slider_image'] = null;
            error_log("SLIDER_IMAGE: Sin imagen (vacío o eliminada)");
        } else {
            // Guardar la URL tal cual viene del Media Manager
            $data['slider_image'] = $sliderImage;
            error_log("SLIDER_IMAGE: URL guardada - " . $sliderImage);
        }

        // Asegurar que tenant_id no cambie
        $data['tenant_id'] = $tenantId;

        $data = self::processFormData($data);

        $newSlug = $data['slug'];
        $prefix = $rawData['prefix'] ?? 'p';

        // Validación
        $errors = PageRequest::validate($data, $id);

        if (!empty($errors)) {
            $_SESSION['_old_input'] = $rawData;
            flash('error', implode('<br>', $errors));
            header('Location: ' . admin_url("pages/{$id}/edit"));
            exit;
        }
        unset($_SESSION['_old_input']);

        $pdo = null;

        try {
            $pdo = Database::connect();
            $pdo->beginTransaction();

            // 1. Desmarcar otras home si es necesario
            if ($makeHomepage) {
                $updateOthersStmt = $pdo->prepare(
                    "UPDATE pages SET is_homepage = 0 WHERE is_homepage = 1 AND id != ? AND tenant_id = ?"
                );
                $updateOthersStmt->execute([$id, $tenantId]);
            }

            // 2. Actualizar datos principales de la página
            unset($data['prefix']);
            error_log("SLIDER_IMAGE: Antes de update - data[slider_image] = " . ($data['slider_image'] ?? 'NO SET'));
            error_log("SLIDER_IMAGE: Antes de update - data keys = " . implode(', ', array_keys($data)));
            $page->update($data);
            error_log("SLIDER_IMAGE: Después de update - page->slider_image = " . ($page->slider_image ?? 'null'));

            // 3. is_homepage ya se actualizó via $page->update($data) arriba

            // === SINCRONIZACIÓN: Actualizar settings de lectura del tenant ===
            $upsertTenantSetting = function($pdo, $tenantId, $key, $value) {
                $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
                if ($driver === 'mysql') {
                    $stmt = $pdo->prepare("INSERT INTO tenant_settings (tenant_id, `key`, `value`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
                    $stmt->execute([$tenantId, $key, $value, $value]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO tenant_settings (tenant_id, \"key\", value) VALUES (?, ?, ?) ON CONFLICT (tenant_id, \"key\") DO UPDATE SET value = EXCLUDED.value");
                    $stmt->execute([$tenantId, $key, $value]);
                }
            };

            if ($makeHomepage) {
                $upsertTenantSetting($pdo, $tenantId, 'page_on_front', $id);
                $upsertTenantSetting($pdo, $tenantId, 'show_on_front', 'page');
            } else {
                // Si se desmarca como homepage, limpiar el setting si coincide con esta página
                $keyCol = Database::qi('key');
                $checkSettingStmt = $pdo->prepare("SELECT value FROM tenant_settings WHERE tenant_id = ? AND {$keyCol} = 'page_on_front'");
                $checkSettingStmt->execute([$tenantId]);
                $currentPageOnFront = $checkSettingStmt->fetchColumn();

                if ($currentPageOnFront == $id) {
                    $upsertTenantSetting($pdo, $tenantId, 'page_on_front', '');
                    $upsertTenantSetting($pdo, $tenantId, 'show_on_front', 'posts');
                }
            }

            // 4. Actualizar slug
            $deleteSlugStmt = $pdo->prepare("DELETE FROM slugs WHERE module = 'pages' AND reference_id = ?");
            $deleteSlugStmt->execute([$id]);

            $insertSlugStmt = $pdo->prepare(
                "INSERT INTO slugs (module, reference_id, slug, tenant_id, prefix) VALUES (?, ?, ?, ?, ?)"
            );
            $insertSlugStmt->execute(['pages', $id, $newSlug, $tenantId, $prefix]);

            // 5. Guardar plantilla seleccionada
            $selectedTemplate = $rawData['page_template'] ?? 'page.blade.php';
            PageMeta::updateOrInsertMeta($id, 'page_template', $selectedTemplate);

            $pdo->commit();

            // Crear revisión después de actualizar exitosamente
            try {
                $changes = [];
                if ($oldTitle !== $page->title) $changes[] = __('pages.change_title');
                if ($oldContent !== $page->content) $changes[] = __('pages.change_content');
                if ($oldStatus !== $data['status']) $changes[] = __('pages.change_status');

                $summary = !empty($changes)
                    ? __('pages.revision_changes_summary', ['changes' => implode(', ', $changes)])
                    : __('pages.revision_metadata_updated');

                \Screenart\Musedock\Models\PageRevision::createFromPage($page, 'manual', $summary);
            } catch (\Exception $revError) {
                error_log("Error al crear revisión: " . $revError->getMessage());
            }

            // Audit log
            AuditLogger::log('page.updated', 'page', $id, [
                'title' => $data['title'] ?? '',
                'slug' => $data['slug'] ?? '',
                'status' => $data['status'] ?? '',
                'is_homepage' => $makeHomepage ? 1 : 0,
                'tenant_id' => $tenantId
            ]);

            // Invalidar caché del sitemap (la página podría haber cambiado de status)
            \Blog\Controllers\Frontend\SitemapController::invalidateCache($tenantId);

            // Invalidar y regenerar HTML cache
            HtmlCache::onPageSaved([
                'slug'        => $newSlug,
                'prefix'      => $prefix,
                'is_homepage' => $makeHomepage ? 1 : 0,
                'status'      => $data['status'] ?? 'published',
            ], $tenantId);

        } catch (\Exception $e) {
            if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
            error_log("ERROR en transacción update página {$id}: " . $e->getMessage());
            $_SESSION['_old_input'] = $rawData;
            flash('error', __('pages.error_update_failed') . ': ' . $e->getMessage());
            header('Location: ' . admin_url("pages/{$id}/edit"));
            exit;
        }

        flash('success', __('pages.success_updated'));
        header('Location: ' . admin_url("pages/{$id}/edit"));
        exit;
    }

    /**
     * Mover página a papelera
     */
    public function destroy($id)
    {
        $this->checkPermission('pages.delete');

        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('pages.error_tenant_not_identified'));
            header('Location: ' . admin_url('pages'));
            exit;
        }

        try {
            $pdo = Database::connect();
            $user = $_SESSION['admin'] ?? null;
            $pageId = (int)$id;

            // UPDATE directo para cambiar status a trash
            $stmt = $pdo->prepare("UPDATE pages SET status = 'trash', updated_at = NOW() WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$pageId, $tenantId]);
            $affectedRows = $stmt->rowCount();

            if ($affectedRows === 0) {
                flash('error', __('pages.error_not_found_or_no_permission'));
                header('Location: ' . admin_url('pages'));
                exit;
            }

            // Verificar que el UPDATE funcionó
            $checkStmt = $pdo->prepare("SELECT id, title, status FROM pages WHERE id = ?");
            $checkStmt->execute([$pageId]);
            $result = $checkStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$result || $result['status'] !== 'trash') {
                flash('error', __('pages.error_trash_status_update_failed'));
                header('Location: ' . admin_url('pages'));
                exit;
            }

            // Registrar en tabla pages_trash
            $deletedAt = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
            $scheduledPermanentDelete = (new \DateTimeImmutable('now'))->modify('+30 days')->format('Y-m-d H:i:s');
            $stmt = $pdo->prepare("INSERT INTO pages_trash (page_id, tenant_id, deleted_by, deleted_by_name, deleted_by_type, deleted_at, scheduled_permanent_delete, ip_address) VALUES (?, ?, ?, ?, 'admin', ?, ?, ?)");
            $stmt->execute([
                $pageId,
                $tenantId,
                $user['id'] ?? 0,
                $user['name'] ?? 'Sistema',
                $deletedAt,
                $scheduledPermanentDelete,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            // Cargar el modelo Page para crear la revisión
            $page = Page::find($pageId);
            if ($page) {
                $page->status = 'trash';
                try {
                    \Screenart\Musedock\Models\PageRevision::createFromPage($page, 'manual', 'Movido a papelera');
                } catch (\Exception $revError) {
                    error_log("Error al crear revisión de papelera: " . $revError->getMessage());
                }
            }

            // Audit log
            AuditLogger::log('page.trashed', 'page', $id, [
                'title' => $result['title'] ?? '',
                'tenant_id' => $tenantId
            ]);

            // Invalidar HTML cache de la página eliminada
            try {
                $slugStmt = $pdo->prepare("SELECT slug, prefix FROM slugs WHERE module = 'pages' AND reference_id = ? LIMIT 1");
                $slugStmt->execute([$pageId]);
                $slugRow = $slugStmt->fetch(\PDO::FETCH_ASSOC);
                if ($slugRow) {
                    HtmlCache::onPageSaved([
                        'slug'        => $slugRow['slug'],
                        'prefix'      => $slugRow['prefix'] ?? page_prefix(),
                        'is_homepage' => false,
                        'status'      => 'trash',
                    ], $tenantId);
                }
            } catch (\Exception $cacheErr) {
                error_log("HtmlCache: Error invalidating on destroy: " . $cacheErr->getMessage());
            }

            flash('success', __('pages.success_trashed'));

        } catch (\Exception $e) {
            error_log("Error al mover página a papelera: " . $e->getMessage());
            flash('error', __('pages.error_delete_failed') . ': ' . $e->getMessage());
        }

        header('Location: ' . admin_url('pages'));
        exit;
    }

    /**
     * Acciones masivas
     */
    public function bulk()
    {
        $action = $_POST['action'] ?? null;
        $selected = $_POST['selected'] ?? [];

        if (empty($action) || empty($selected)) {
            flash('error', __('pages.error_bulk_no_selection'));
            header('Location: ' . admin_url('pages'));
            exit;
        }

        // Verificar permisos según la acción
        if ($action === 'delete') {
            $this->checkPermission('pages.delete');
        } else {
            $this->checkPermission('pages.edit');
        }

        $tenantId = TenantManager::currentTenantId();
        if ($tenantId === null) {
            flash('error', __('pages.error_tenant_not_identified'));
            header('Location: ' . admin_url('pages'));
            exit;
        }

        // Límite de 100 elementos en acciones masivas
        if (count($selected) > 100) {
            flash('error', __('pages.error_bulk_limit_exceeded'));
            header('Location: ' . admin_url('pages'));
            exit;
        }

        if ($action === 'delete') {
            $deletedCount = 0;
            $deletedImages = 0;
            $pdo = Database::connect();

            foreach ($selected as $id) {
                // Verificar que la página pertenece al tenant
                $page = Page::where('id', $id)->where('tenant_id', $tenantId)->first();

                if ($page) {
                    // Verificar slider_image desde SQL
                    $stmt = $pdo->prepare("SELECT slider_image FROM pages WHERE id = ?");
                    $stmt->execute([$id]);
                    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                    $imagePath = $result['slider_image'] ?? $page->slider_image ?? null;

                    // Si hay imagen, intentar eliminarla
                    if (!empty($imagePath)) {
                        $fileName = basename($imagePath);
                        $fullPath = APP_ROOT . "/public/assets/uploads/headers/{$fileName}";

                        if (file_exists($fullPath)) {
                            if (@unlink($fullPath)) {
                                $deletedImages++;
                            }
                        }
                    }

                    // Eliminar traducciones
                    try {
                        $deleteStmt = $pdo->prepare("DELETE FROM page_translations WHERE page_id = ?");
                        $deleteStmt->execute([$id]);
                    } catch (\Exception $e) {
                        error_log("Error al eliminar traducciones: " . $e->getMessage());
                    }

                    // Eliminar slug
                    try {
                        $deleteStmt = $pdo->prepare("DELETE FROM slugs WHERE module = 'pages' AND reference_id = ?");
                        $deleteStmt->execute([$id]);
                    } catch (\Exception $e) {
                        error_log("Error al eliminar slug: " . $e->getMessage());
                        continue;
                    }

                    // Eliminar metadatos
                    try {
                        $deleteStmt = $pdo->prepare("DELETE FROM page_meta WHERE page_id = ?");
                        $deleteStmt->execute([$id]);
                    } catch (\Exception $e) {
                        error_log("Error al eliminar metadatos: " . $e->getMessage());
                    }

                    // Eliminar página
                    try {
                        $deleteStmt = $pdo->prepare("DELETE FROM pages WHERE id = ? AND tenant_id = ?");
                        $deleteStmt->execute([$id, $tenantId]);
                        $deletedCount++;
                    } catch (\Exception $e) {
                        error_log("Error al eliminar página: " . $e->getMessage());
                        continue;
                    }
                }
            }

            $message = str_replace('{count}', $deletedCount, __('pages.success_bulk_deleted'));
            if ($deletedImages > 0) {
                $message .= ' ' . str_replace('{count}', $deletedImages, __('pages.info_images_deleted'));
            }
            flash('success', $message);

            header('Location: ' . admin_url('pages'));
            exit;
        }

        if ($action === 'edit') {
            $_SESSION['selected_bulk_ids'] = $selected;
            header('Location: ' . admin_url('pages/bulk-edit'));
            exit;
        }

        if (in_array($action, ['draft', 'published'])) {
            foreach ($selected as $id) {
                $page = Page::where('id', $id)->where('tenant_id', $tenantId)->first();
                if ($page) {
                    $page->status = $action;
                    $page->save();
                }
            }

            flash('success', __('pages.success_bulk_status_updated'));
            header('Location: ' . admin_url('pages'));
            exit;
        }

        // Opciones para cambiar la visibilidad en masa
        if (in_array($action, ['public', 'private', 'members'])) {
            foreach ($selected as $id) {
                $page = Page::where('id', $id)->where('tenant_id', $tenantId)->first();
                if ($page) {
                    $page->visibility = $action;
                    $page->save();
                }
            }

            flash('success', __('pages.success_bulk_visibility_updated'));
            header('Location: ' . admin_url('pages'));
            exit;
        }

        flash('error', __('pages.error_invalid_action'));
        header('Location: ' . admin_url('pages'));
        exit;
    }

    /**
     * Formulario de edición masiva
     */
    public function bulkEditForm()
    {
        $this->checkPermission('pages.edit');

        $selectedIds = $_SESSION['selected_bulk_ids'] ?? [];

        if (empty($selectedIds)) {
            flash('error', __('pages.error_no_pages_selected'));
            header('Location: ' . admin_url('pages'));
            exit;
        }

        $tenantId = TenantManager::currentTenantId();

        // Cargar todas las páginas seleccionadas
        $selectedPages = [];
        foreach ($selectedIds as $id) {
            $page = Page::where('id', $id)->where('tenant_id', $tenantId)->first();
            if ($page) {
                $selectedPages[] = $page;
            }
        }

        return View::renderTenantAdmin('pages.bulk_edit', [
            'title' => __('pages.bulk_edit_title'),
            'selectedIds' => $selectedIds,
            'selectedPages' => $selectedPages
        ]);
    }

    /**
     * Actualizar páginas en masa
     */
    public function bulkUpdate()
    {
        $this->checkPermission('pages.edit');

        $selected = $_POST['selected'] ?? [];
        $status = $_POST['status'] ?? '';
        $visibility = $_POST['visibility'] ?? '';
        $publishedAt = $_POST['published_at'] ?? '';

        if (empty($selected)) {
            flash('error', __('pages.error_no_pages_selected'));
            header('Location: ' . admin_url('pages'));
            exit;
        }

        $tenantId = TenantManager::currentTenantId();

        // Límite de 100 elementos en acciones masivas
        if (count($selected) > 100) {
            flash('error', __('pages.error_bulk_limit_exceeded'));
            header('Location: ' . admin_url('pages'));
            exit;
        }

        $updatedCount = 0;

        foreach ($selected as $id) {
            $page = Page::where('id', $id)->where('tenant_id', $tenantId)->first();
            if (!$page) continue;

            $updateData = [];

            if (!empty($status)) {
                $updateData['status'] = $status;
            }

            if (!empty($visibility)) {
                $updateData['visibility'] = $visibility;
            }

            if (!empty($publishedAt)) {
                $updateData['published_at'] = $publishedAt;
            }

            if (!empty($updateData)) {
                $page->update($updateData);
                $updatedCount++;
            }
        }

        flash('success', str_replace('{count}', $updatedCount, __('pages.success_bulk_updated')));
        header('Location: ' . admin_url('pages'));
        exit;
    }

    /**
     * Formulario para editar traducción
     */
    public function editTranslation($id, $locale)
    {
        $this->checkPermission('pages.edit');

        $tenantId = TenantManager::currentTenantId();

        $page = Page::where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$page) {
            flash('error', __('pages.error_base_page_not_found'));
            header('Location: ' . admin_url('pages'));
            exit;
        }

        // Intentar encontrar la traducción existente
        $translation = PageTranslation::where('page_id', $id)
            ->where('locale', $locale)
            ->first();

        // Si no existe, creamos una instancia vacía para el formulario
        $isNewTranslation = false;
        if (!$translation) {
            $translation = new PageTranslation([
                'page_id' => $id,
                'locale' => $locale,
                'tenant_id' => $page->tenant_id,
            ]);
            $isNewTranslation = true;
        }

        // Obtener el nombre del idioma
        $localeName = getAvailableLocales()[$locale] ?? strtoupper($locale);

        return View::renderTenantAdmin('pages.translation_edit', [
            'title'       => $isNewTranslation
                                ? __('pages.create_translation') . " ({$localeName}) - \"{$page->title}\""
                                : __('pages.edit_translation') . " ({$localeName}) - \"{$page->title}\"",
            'Page'        => $page,
            'translation' => $translation,
            'locale'      => $locale,
            'localeName'  => $localeName
        ]);
    }

    /**
     * Guardar traducción
     */
    public function updateTranslation($id, $locale)
    {
        $this->checkPermission('pages.edit');

        $tenantId = TenantManager::currentTenantId();

        $page = Page::where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$page) {
            flash('error', __('pages.error_base_page_not_found'));
            header('Location: ' . admin_url('pages'));
            exit;
        }

        $data = $_POST;
        unset($data['_token'], $data['_method'], $data['_csrf']);

        $data['page_id'] = $id;
        $data['locale'] = $locale;

        // Limpiar campos opcionales vacíos
        $optionalFields = [
            'content',
            'seo_title', 'seo_description', 'seo_keywords', 'seo_image',
            'canonical_url', 'robots_directive', 'twitter_title',
            'twitter_description', 'twitter_image'
        ];
        foreach ($optionalFields as $field) {
            if (isset($data[$field]) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        // Validar robots_directive
        if (isset($data['robots_directive']) && !in_array($data['robots_directive'], ['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow'])) {
            $data['robots_directive'] = null;
        }

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT * FROM page_translations WHERE page_id = ? AND locale = ? LIMIT 1");
            $stmt->execute([$id, $locale]);
            $existingTranslation = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($existingTranslation) {
                // Actualizar traducción existente
                $allowedColumns = [
                    'title', 'content', 'seo_title', 'seo_description',
                    'seo_keywords', 'seo_image', 'canonical_url',
                    'robots_directive', 'twitter_title', 'twitter_description',
                    'twitter_image'
                ];

                $setClauses = [];
                $params = [];

                foreach ($data as $key => $value) {
                    if (in_array($key, $allowedColumns)) {
                        $setClauses[] = "{$key} = ?";
                        $params[] = $value;
                    }
                }

                $setClauses[] = "updated_at = NOW()";
                $setString = implode(', ', $setClauses);

                $updateStmt = $pdo->prepare("UPDATE page_translations SET {$setString} WHERE page_id = ? AND locale = ?");
                $params[] = $id;
                $params[] = $locale;
                $updateStmt->execute($params);

                flash('success', __('pages.success_translation_updated'));
            } else {
                // Crear nueva traducción
                $allowedColumns = [
                    'page_id', 'locale', 'tenant_id', 'title', 'content',
                    'seo_title', 'seo_description', 'seo_keywords', 'seo_image',
                    'canonical_url', 'robots_directive', 'twitter_title',
                    'twitter_description', 'twitter_image', 'created_at', 'updated_at'
                ];

                $insertData = [
                    'page_id' => $id,
                    'locale' => $locale,
                    'tenant_id' => $tenantId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                foreach ($data as $key => $value) {
                    if (in_array($key, $allowedColumns)) {
                        $insertData[$key] = $value;
                    }
                }

                $columns = implode(', ', array_keys($insertData));
                $placeholders = implode(', ', array_fill(0, count($insertData), '?'));

                $insertStmt = $pdo->prepare("INSERT INTO page_translations ({$columns}) VALUES ({$placeholders})");
                $insertStmt->execute(array_values($insertData));

                flash('success', __('pages.success_translation_created'));
            }
        } catch (\Exception $e) {
            error_log("Error al guardar traducción: " . $e->getMessage());
            flash('error', __('pages.error_translation_save_failed') . ': ' . $e->getMessage());
            header('Location: ' . admin_url("pages/{$id}/translations/{$locale}"));
            exit;
        }

        header('Location: ' . admin_url("pages/{$id}/translations/{$locale}"));
        exit;
    }

    // ================================================================
    // SISTEMA DE VERSIONES/REVISIONES
    // ================================================================

    /**
     * Mostrar historial de revisiones de una página
     */
    public function revisions($id)
    {
        $this->checkPermission('pages.view');

        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $tenantId = TenantManager::currentTenantId();
        if (!$tenantId) {
            flash('error', __('pages.error_invalid_session'));
            header('Location: /login');
            exit;
        }

        $page = Page::where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$page) {
            flash('error', __('pages.error_not_found'));
            header('Location: ' . admin_url('pages'));
            exit;
        }

        $revisions = \Screenart\Musedock\Models\PageRevision::getPageRevisions($id, 100);

        return View::renderTenantAdmin('pages.revisions', [
            'title' => __('pages.revisions_title') . ': ' . e($page->title),
            'page' => $page,
            'revisions' => $revisions,
        ]);
    }

    /**
     * Restaurar una revisión específica
     */
    public function restoreRevision($pageId, $revisionId)
    {
        $this->checkPermission('pages.edit');

        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $tenantId = TenantManager::currentTenantId();
        if (!$tenantId) {
            flash('error', __('pages.error_invalid_session'));
            header('Location: /login');
            exit;
        }

        $revision = \Screenart\Musedock\Models\PageRevision::findWithTenant((int)$revisionId, $tenantId);

        if (!$revision || $revision->page_id != $pageId) {
            flash('error', __('pages.error_revision_not_found'));
            header('Location: ' . admin_url("pages/{$pageId}/revisions"));
            exit;
        }

        if ($revision->restore()) {
            flash('success', str_replace('{date}', $revision->created_at, __('pages.success_revision_restored')));
        } else {
            flash('error', __('pages.error_revision_restore_failed'));
        }

        header('Location: ' . admin_url("pages/{$pageId}/edit"));
        exit;
    }

    /**
     * Acciones masivas sobre revisiones de una página
     * Acciones soportadas:
     * - delete_selected: elimina revisiones seleccionadas
     * - delete_all: elimina todas las revisiones de la página
     */
    public function bulkRevisions($pageId)
    {
        $this->checkPermission('pages.edit');

        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $tenantId = TenantManager::currentTenantId();
        if (!$tenantId) {
            flash('error', __('pages.error_invalid_session'));
            header('Location: /login');
            exit;
        }

        $page = Page::where('id', (int)$pageId)->where('tenant_id', (int)$tenantId)->first();
        if (!$page) {
            flash('error', __('pages.error_not_found'));
            header('Location: ' . admin_url('pages'));
            exit;
        }

        $action = $_POST['action'] ?? '';

        try {
            $pdo = Database::connect();
            $pdo->beginTransaction();

            $deletedCount = 0;

            if ($action === 'delete_selected') {
                $revisionIds = $_POST['revision_ids'] ?? [];
                if (!is_array($revisionIds)) {
                    $revisionIds = [];
                }

                $revisionIds = array_values(array_unique(array_filter(array_map('intval', $revisionIds), fn($v) => $v > 0)));

                if (empty($revisionIds)) {
                    $pdo->rollBack();
                    flash('error', __('pages.error_bulk_revision_no_selection'));
                    header('Location: ' . admin_url("pages/{$pageId}/revisions"));
                    exit;
                }

                $placeholders = implode(',', array_fill(0, count($revisionIds), '?'));
                $params = array_merge($revisionIds, [(int)$pageId, (int)$tenantId]);
                $deleteStmt = $pdo->prepare("
                    DELETE FROM page_revisions
                    WHERE id IN ({$placeholders}) AND page_id = ? AND tenant_id = ?
                ");
                $deleteStmt->execute($params);
                $deletedCount = (int)$deleteStmt->rowCount();
            } elseif ($action === 'delete_all') {
                $deleteStmt = $pdo->prepare("DELETE FROM page_revisions WHERE page_id = ? AND tenant_id = ?");
                $deleteStmt->execute([(int)$pageId, (int)$tenantId]);
                $deletedCount = (int)$deleteStmt->rowCount();
            } else {
                $pdo->rollBack();
                flash('error', __('pages.error_bulk_revision_invalid_action'));
                header('Location: ' . admin_url("pages/{$pageId}/revisions"));
                exit;
            }

            $updateCountStmt = $pdo->prepare("
                UPDATE pages
                SET revision_count = (
                    SELECT COUNT(*)
                    FROM page_revisions
                    WHERE page_id = ? AND tenant_id = ?
                )
                WHERE id = ? AND tenant_id = ?
            ");
            $updateCountStmt->execute([(int)$pageId, (int)$tenantId, (int)$pageId, (int)$tenantId]);

            $pdo->commit();

            flash('success', __('pages.success_bulk_revisions_deleted', ['count' => $deletedCount]));
        } catch (\Throwable $e) {
            if (isset($pdo) && $pdo instanceof \PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error en bulkRevisions pages tenant {$tenantId} page {$pageId}: " . $e->getMessage());
            flash('error', __('pages.error_revision_delete_failed'));
        }

        header('Location: ' . admin_url("pages/{$pageId}/revisions"));
        exit;
    }

    /**
     * Vista previa de una revisión
     */
    public function previewRevision($pageId, $revisionId)
    {
        $this->checkPermission('pages.view');

        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $tenantId = TenantManager::currentTenantId();
        if (!$tenantId) {
            flash('error', __('pages.error_invalid_session'));
            header('Location: /login');
            exit;
        }

        $revision = \Screenart\Musedock\Models\PageRevision::findWithTenant((int)$revisionId, $tenantId);

        if (!$revision || $revision->page_id != $pageId) {
            flash('error', __('pages.error_revision_not_found'));
            header('Location: ' . admin_url("pages/{$pageId}/revisions"));
            exit;
        }

        return View::renderTenantAdmin('pages.preview-revision', [
            'title' => __('pages.preview_title_with_page', ['title' => e($revision->title)]),
            'revision' => $revision,
            'page' => Page::where('id', $pageId)->where('tenant_id', $tenantId)->first(),
        ]);
    }

    /**
     * Comparar dos revisiones
     */
    public function compareRevisions($pageId, $id1, $id2)
    {
        $this->checkPermission('pages.view');

        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $tenantId = TenantManager::currentTenantId();
        if (!$tenantId) {
            flash('error', __('pages.error_invalid_session'));
            header('Location: /login');
            exit;
        }

        $revision1 = \Screenart\Musedock\Models\PageRevision::findWithTenant((int)$id1, $tenantId);
        $revision2 = \Screenart\Musedock\Models\PageRevision::findWithTenant((int)$id2, $tenantId);

        if (!$revision1 || !$revision2 || $revision1->page_id != $pageId || $revision2->page_id != $pageId) {
            flash('error', __('pages.error_revisions_not_found'));
            header('Location: ' . admin_url("pages/{$pageId}/revisions"));
            exit;
        }

        $diff = $revision1->diffWith($revision2);

        return View::renderTenantAdmin('pages.compare-revisions', [
            'title' => __('pages.compare_revisions_title'),
            'page' => Page::where('id', $pageId)->where('tenant_id', $tenantId)->first(),
            'revision1' => $revision1,
            'revision2' => $revision2,
            'diff' => $diff,
        ]);
    }

    /**
     * Mostrar papelera de páginas
     */
    public function trash()
    {
        $this->checkPermission('pages.view');

        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $tenantId = TenantManager::currentTenantId();
        if (!$tenantId) {
            flash('error', __('pages.error_invalid_session'));
            header('Location: /login');
            exit;
        }

        $pages = Page::where('status', 'trash')
                        ->where('tenant_id', $tenantId)
                        ->orderBy('updated_at', 'DESC')
                        ->get();

        $pdo = Database::connect();
        $trashInfo = [];
        foreach ($pages as $page) {
            $stmt = $pdo->prepare("SELECT * FROM pages_trash WHERE page_id = ? LIMIT 1");
            $stmt->execute([$page->id]);
            $trashInfo[$page->id] = $stmt->fetch(\PDO::FETCH_ASSOC);
        }

        return View::renderTenantAdmin('pages.trash', [
            'title' => __('pages.trash_title'),
            'pages' => $pages,
            'trashInfo' => $trashInfo,
        ]);
    }

    /**
     * Restaurar una página desde la papelera
     */
    public function restoreFromTrash($id)
    {
        $this->checkPermission('pages.edit');

        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $tenantId = TenantManager::currentTenantId();
        if (!$tenantId) {
            flash('error', __('pages.error_invalid_session'));
            header('Location: /login');
            exit;
        }

        $pdo = Database::connect();
        $pageId = (int)$id;

        // Verificar que la página existe en papelera
        $stmt = $pdo->prepare("SELECT id FROM pages WHERE id = ? AND tenant_id = ? AND status = 'trash'");
        $stmt->execute([$pageId, $tenantId]);
        $page = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$page) {
            flash('error', __('pages.error_not_found_in_trash'));
            header('Location: ' . admin_url('pages/trash'));
            exit;
        }

        // Restaurar
        $updateStmt = $pdo->prepare("UPDATE pages SET status = 'draft', updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$pageId]);

        // Eliminar de papelera
        $stmt = $pdo->prepare("DELETE FROM pages_trash WHERE page_id = ?");
        $stmt->execute([$pageId]);

        // Crear revisión
        $pageModel = Page::find($pageId);
        if ($pageModel) {
            \Screenart\Musedock\Models\PageRevision::createFromPage($pageModel, 'restored', 'Restaurado desde papelera');
        }

        flash('success', __('pages.success_restored'));
        header('Location: ' . admin_url("pages/{$pageId}/edit"));
        exit;
    }

    /**
     * Eliminar permanentemente una página
     */
    public function forceDelete($id)
    {
        $this->checkPermission('pages.delete');

        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $tenantId = TenantManager::currentTenantId();
        if (!$tenantId) {
            flash('error', __('pages.error_invalid_session'));
            header('Location: /login');
            exit;
        }

        $page = Page::where('id', $id)
                        ->where('tenant_id', $tenantId)
                        ->where('status', 'trash')
                        ->first();

        if (!$page) {
            flash('error', __('pages.error_not_found_in_trash'));
            header('Location: ' . admin_url('pages/trash'));
            exit;
        }

        try {
            $pdo = Database::connect();

            // Eliminar revisiones
            $stmt = $pdo->prepare("DELETE FROM page_revisions WHERE page_id = ?");
            $stmt->execute([$id]);

            // Eliminar de papelera
            $stmt = $pdo->prepare("DELETE FROM pages_trash WHERE page_id = ?");
            $stmt->execute([$id]);

            // Eliminar traducciones
            $stmt = $pdo->prepare("DELETE FROM page_translations WHERE page_id = ?");
            $stmt->execute([$id]);

            // Eliminar metadatos
            $stmt = $pdo->prepare("DELETE FROM page_meta WHERE page_id = ?");
            $stmt->execute([$id]);

            // Eliminar slugs
            $stmt = $pdo->prepare("DELETE FROM slugs WHERE module = 'pages' AND reference_id = ?");
            $stmt->execute([$id]);

            // Eliminar página
            $stmt = $pdo->prepare("DELETE FROM pages WHERE id = ?");
            $stmt->execute([$id]);

            // Audit log
            AuditLogger::log('page.permanently_deleted', 'page', $id, [
                'title' => $page->title ?? '',
                'tenant_id' => $tenantId
            ]);

            flash('success', __('pages.success_permanently_deleted'));
        } catch (\Exception $e) {
            error_log("Error al eliminar permanentemente la página: " . $e->getMessage());
            flash('error', __('pages.error_delete_failed'));
        }

        header('Location: ' . admin_url('pages/trash'));
        exit;
    }

    /**
     * Autoguardar una página (AJAX endpoint)
     */
    public function autosave($id)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        header('Content-Type: application/json');

        // Verificar permiso sin redirigir
        if (!userCan('pages.edit')) {
            echo json_encode(['success' => false, 'message' => __('pages.error_no_permission')]);
            exit;
        }

        $tenantId = TenantManager::currentTenantId();
        if (!$tenantId) {
            echo json_encode(['success' => false, 'message' => __('pages.error_invalid_session')]);
            exit;
        }

        $page = Page::where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$page) {
            echo json_encode(['success' => false, 'message' => __('pages.error_not_found')]);
            exit;
        }

        // Obtener datos JSON del body
        $rawData = file_get_contents('php://input');
        $data = json_decode($rawData, true);

        if (!$data) {
            echo json_encode(['success' => false, 'message' => __('pages.error_invalid_payload')]);
            exit;
        }

        try {
            if (isset($data['title'])) $page->title = $data['title'];
            if (isset($data['content'])) $page->content = $data['content'];
            if (isset($data['excerpt'])) $page->excerpt = $data['excerpt'];

            $page->updated_at = date('Y-m-d H:i:s');
            $page->save();

            // Crear revisión de tipo autosave
            \Screenart\Musedock\Models\PageRevision::createFromPage($page, 'autosave', __('pages.revision_autosave_summary'));

            echo json_encode([
                'success' => true,
                'message' => __('pages.autosave_success'),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            error_log("Error en autosave: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => __('pages.error_autosave_failed')]);
        }

        exit;
    }

    // ================================================================
    // MÉTODOS AUXILIARES
    // ================================================================

    /**
     * Procesar datos del formulario
     */
    private static function processFormData($data)
    {
        // Gestionar campos opcionales
        $optionalFields = [
            'content', 'excerpt', 'seo_title', 'seo_description',
            'seo_keywords', 'canonical_url', 'robots_directive',
            'twitter_title', 'twitter_description'
        ];

        foreach ($optionalFields as $field) {
            if (isset($data[$field]) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        // Dar formato a la fecha de publicación si existe
        if (isset($data['published_at']) && !empty($data['published_at'])) {
            try {
                $date = new \DateTime($data['published_at']);
                $data['published_at'] = $date->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                // Si hay error, mantener como está
            }
        } else {
            $data['published_at'] = null;
        }

        // Establecer valores predeterminados
        $data['status'] = $data['status'] ?? 'published';
        $data['visibility'] = $data['visibility'] ?? 'public';

        // Validar visibility
        if (!in_array($data['visibility'], ['public', 'private', 'members'])) {
            $data['visibility'] = 'public';
        }

        return $data;
    }

    /**
     * Procesar subida de imagen del slider
     */
    private function processSliderImageUpload($file, $currentImage = null)
    {
        $fileInfo = getimagesize($file['tmp_name']);
        if ($fileInfo === false) {
            return ['error' => __('pages.error_image_invalid_file')];
        }

        // Dimensiones deseadas
        $targetWidth = 1920;
        $targetHeight = 400;

        $uploadDir = APP_ROOT . '/public/assets/uploads/headers/';
        $relativePath = 'uploads/headers/';

        // Crear directorio si no existe
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return ['error' => __('pages.error_image_directory_create')];
            }
        }

        // Generar nombre único
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = uniqid('header_') . '.' . $extension;
        $fullPath = $uploadDir . $filename;

        // Verificar si es un formato no compatible
        $isUnsupportedFormat = !in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);

        if ($isUnsupportedFormat) {
            if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                return ['error' => __('pages.error_image_move_uploaded')];
            }
        } else {
            try {
                // Crear imagen temporal
                switch ($extension) {
                    case 'jpg':
                    case 'jpeg':
                        $sourceImage = imagecreatefromjpeg($file['tmp_name']);
                        break;
                    case 'png':
                        $sourceImage = imagecreatefrompng($file['tmp_name']);
                        break;
                    case 'gif':
                        $sourceImage = imagecreatefromgif($file['tmp_name']);
                        break;
                    case 'webp':
                        $sourceImage = imagecreatefromwebp($file['tmp_name']);
                        break;
                }

                $sourceWidth = imagesx($sourceImage);
                $sourceHeight = imagesy($sourceImage);

                $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);

                // Mantener transparencia para PNG
                if ($extension == 'png') {
                    imagealphablending($targetImage, false);
                    imagesavealpha($targetImage, true);
                    $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
                    imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $transparent);
                }

                // Calcular proporciones
                $sourceRatio = $sourceWidth / $sourceHeight;
                $targetRatio = $targetWidth / $targetHeight;

                if ($sourceRatio > $targetRatio) {
                    $newHeight = $sourceHeight;
                    $newWidth = $sourceHeight * $targetRatio;
                    $srcX = ($sourceWidth - $newWidth) / 2;
                    $srcY = 0;
                } else {
                    $newWidth = $sourceWidth;
                    $newHeight = $sourceWidth / $targetRatio;
                    $srcX = 0;
                    $srcY = ($sourceHeight - $newHeight) / 2;
                }

                // Redimensionar
                imagecopyresampled(
                    $targetImage, $sourceImage,
                    0, 0, $srcX, $srcY,
                    $targetWidth, $targetHeight, $newWidth, $newHeight
                );

                // Guardar
                switch ($extension) {
                    case 'jpg':
                    case 'jpeg':
                        imagejpeg($targetImage, $fullPath, 90);
                        break;
                    case 'png':
                        imagepng($targetImage, $fullPath, 9);
                        break;
                    case 'gif':
                        imagegif($targetImage, $fullPath);
                        break;
                    case 'webp':
                        imagewebp($targetImage, $fullPath, 90);
                        break;
                }

                imagedestroy($sourceImage);
                imagedestroy($targetImage);

            } catch (\Exception $e) {
                return ['error' => __('pages.error_image_process') . ': ' . $e->getMessage()];
            }
        }

        // Eliminar imagen anterior si existe
        if ($currentImage && !empty($currentImage) && strpos($currentImage, 'themes/default/img/hero') === false) {
            $oldPath = APP_ROOT . '/public/' . $currentImage;
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }

        return ['path' => $relativePath . $filename];
    }

    /**
     * GET /admin/api/editor-styles.css
     * Generates dynamic CSS for TinyMCE content_css based on tenant typography options.
     */
    public function editorStylesCss()
    {
        // No requiere checkPermission — es un recurso CSS cargado por TinyMCE
        header('Content-Type: text/css; charset=utf-8');
        header('Cache-Control: public, max-age=3600');

        // Google Fonts map — ALL curated fonts for the editor selector
        $googleFonts = [
            "'Playfair Display', serif" => 'Playfair+Display:wght@400;700',
            "'Montserrat', sans-serif" => 'Montserrat:wght@400;500;600;700',
            "'Roboto', sans-serif" => 'Roboto:wght@400;500;700',
            "'Open Sans', sans-serif" => 'Open+Sans:wght@400;600;700',
            "'Lato', sans-serif" => 'Lato:wght@400;700',
            "'Poppins', sans-serif" => 'Poppins:wght@400;500;600;700',
            "'Oswald', sans-serif" => 'Oswald:wght@400;500;600;700',
            "'Raleway', sans-serif" => 'Raleway:wght@400;500;600;700',
            "'Merriweather', serif" => 'Merriweather:wght@400;700',
            "'Nunito', sans-serif" => 'Nunito:wght@400;600;700',
            "'Quicksand', sans-serif" => 'Quicksand:wght@400;500;600;700',
            "'Inter', sans-serif" => 'Inter:wght@400;500;600;700',
            "'Work Sans', sans-serif" => 'Work+Sans:wght@400;500;600;700',
            "'Source Sans 3', sans-serif" => 'Source+Sans+3:wght@400;600;700',
            "'DM Sans', sans-serif" => 'DM+Sans:wght@400;500;700',
            "'Lora', serif" => 'Lora:wght@400;700',
            "'PT Serif', serif" => 'PT+Serif:wght@400;700',
            "'Libre Baskerville', serif" => 'Libre+Baskerville:wght@400;700',
            "'Crimson Text', serif" => 'Crimson+Text:wght@400;700',
            "'JetBrains Mono', monospace" => 'JetBrains+Mono:wght@400;700',
            "'Fira Code', monospace" => 'Fira+Code:wght@400;700',
            "'Source Code Pro', monospace" => 'Source+Code+Pro:wght@400;700',
            "'Bebas Neue', sans-serif" => 'Bebas+Neue',
        ];

        // Read tenant typography options
        $headingFont = function_exists('themeOption') ? themeOption('typography.content_heading_font', 'inherit') : 'inherit';
        $bodyFont = function_exists('themeOption') ? themeOption('typography.content_body_font', 'inherit') : 'inherit';
        $scale = function_exists('themeOption') ? themeOption('typography.content_type_scale', 'normal') : 'normal';
        $textColor = function_exists('themeOption') ? themeOption('typography.content_text_color', '#334155') : '#334155';
        $headingColor = function_exists('themeOption') ? themeOption('typography.content_heading_color', '#0f172a') : '#0f172a';
        $linkColor = function_exists('themeOption') ? themeOption('typography.content_link_color', '#3b82f6') : '#3b82f6';

        // Resolve scale sizes
        $scales = [
            'compact' => ['h1' => '28px', 'h2' => '24px', 'h3' => '20px', 'h4' => '18px', 'h5' => '16px', 'h6' => '14px', 'body' => '15px', 'lh' => '1.6'],
            'normal'  => ['h1' => '36px', 'h2' => '28px', 'h3' => '24px', 'h4' => '20px', 'h5' => '18px', 'h6' => '16px', 'body' => '16px', 'lh' => '1.7'],
            'large'   => ['h1' => '48px', 'h2' => '36px', 'h3' => '28px', 'h4' => '24px', 'h5' => '20px', 'h6' => '18px', 'body' => '17px', 'lh' => '1.8'],
        ];
        $s = $scales[$scale] ?? $scales['normal'];

        // Build font-family stacks
        $systemStack = "system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
        $bodyFontStack = ($bodyFont !== 'inherit' && $bodyFont !== '') ? "{$bodyFont}, {$systemStack}" : $systemStack;
        $headingFontStack = ($headingFont !== 'inherit' && $headingFont !== '') ? "{$headingFont}, {$systemStack}" : $bodyFontStack;

        // Load ALL curated Google Fonts in the editor iframe (admin only, not frontend)
        $fontFamilies = array_map(fn($f) => 'family=' . $f, array_values($googleFonts));
        $imports = "@import url('https://fonts.googleapis.com/css2?" . implode('&', $fontFamilies) . "&display=swap');\n";

        $css = $imports;
        $css .= <<<CSS

body {
  font-family: {$bodyFontStack};
  font-size: {$s['body']};
  line-height: {$s['lh']};
  color: {$textColor};
  padding: 12px 16px;
  max-width: 820px;
  margin: 0;
}

h1 { font-family: {$headingFontStack}; font-size: {$s['h1']}; font-weight: 700; color: {$headingColor}; margin: 0 0 16px; line-height: 1.2; }
h2 { font-family: {$headingFontStack}; font-size: {$s['h2']}; font-weight: 700; color: {$headingColor}; margin: 24px 0 12px; line-height: 1.3; }
h3 { font-family: {$headingFontStack}; font-size: {$s['h3']}; font-weight: 600; color: {$headingColor}; margin: 20px 0 10px; line-height: 1.4; }
h4 { font-family: {$headingFontStack}; font-size: {$s['h4']}; font-weight: 600; color: {$headingColor}; margin: 16px 0 8px; line-height: 1.4; }
h5 { font-family: {$headingFontStack}; font-size: {$s['h5']}; font-weight: 600; color: {$headingColor}; margin: 12px 0 6px; line-height: 1.4; }
h6 { font-family: {$headingFontStack}; font-size: {$s['h6']}; font-weight: 600; color: {$headingColor}; margin: 12px 0 6px; line-height: 1.4; }

p { margin: 0 0 1rem; }
a { color: {$linkColor}; }
img { max-width: 100%; height: auto; }
strong { font-weight: 700; color: #333; }
blockquote { border-left: 3px solid #e2e8f0; padding: 8px 0 8px 16px; margin: 1rem 0; color: #64748b; font-style: italic; }
code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 0.9em; font-family: 'SFMono-Regular', Consolas, monospace; }
pre { background: #1e293b; color: #e2e8f0; padding: 16px; border-radius: 8px; overflow-x: auto; margin: 1rem 0; }
pre code { background: none; padding: 0; color: inherit; font-size: 14px; }
ul, ol { margin: 1rem 0; padding-left: 2rem; }
li { margin-bottom: 0.4rem; line-height: 1.6; }
table { border-collapse: collapse; width: 100%; margin: 1rem 0; }
td, th { border: 1px solid #e2e8f0; padding: 8px 12px; text-align: left; }
th { background: #f8fafc; font-weight: 600; }
hr { border: none; border-top: 1px solid #e2e8f0; margin: 1.5rem 0; }
CSS;

        echo $css;
        exit;
    }
}
