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

        // Consulta de páginas SOLO del tenant actual (excluyendo papelera)
        $query = Page::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw("(status != ? OR status IS NULL)", ['trash'])
            ->orderBy('updated_at', 'DESC');

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
            \Screenart\Musedock\Models\PageRevision::createFromPage($page, 'initial', 'Versión inicial de la página');
        } catch (\Exception $e) {
            error_log("Error al crear revisión inicial: " . $e->getMessage());
        }

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
        unset($data['is_homepage']);

        // Manejo de checkboxes
        $data['show_slider'] = isset($data['show_slider']) ? 1 : 0;
        $data['hide_title'] = isset($data['hide_title']) ? 1 : 0;

        // Visibilidad
        $data['visibility'] = $data['visibility'] ?? 'public';
        if (!in_array($data['visibility'], ['public', 'private', 'members'])) {
            $data['visibility'] = 'public';
        }

        // Procesar imagen del slider
        $currentSliderImage = $data['current_slider_image'] ?? null;
        $removeImage = $data['remove_slider_image'] ?? '0';
        unset($data['current_slider_image'], $data['remove_slider_image']);

        if ($removeImage === '1' && !empty($currentSliderImage)) {
            $fileName = basename($currentSliderImage);
            $fullPath = APP_ROOT . "/public/assets/uploads/headers/{$fileName}";

            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }

            $data['slider_image'] = null;
        }

        // Nueva imagen
        if ($_FILES && isset($_FILES['slider_image']) && $_FILES['slider_image']['error'] == 0) {
            $uploadResult = $this->processSliderImageUpload($_FILES['slider_image'], $currentSliderImage);
            if (isset($uploadResult['error'])) {
                flash('error', __('pages.error_image_upload') . ': ' . $uploadResult['error']);
                header('Location: ' . admin_url("pages/{$id}/edit"));
                exit;
            }
            $data['slider_image'] = $uploadResult['path'];
        } elseif ($removeImage !== '1') {
            $data['slider_image'] = $currentSliderImage;
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
            $page->update($data);

            // 3. Actualizar is_homepage
            $updateCurrentStmt = $pdo->prepare(
                "UPDATE pages SET is_homepage = ? WHERE id = ?"
            );
            $updateCurrentStmt->execute([$makeHomepage ? 1 : 0, $id]);

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
                if ($oldTitle !== $page->title) $changes[] = 'título';
                if ($oldContent !== $page->content) $changes[] = 'contenido';
                if ($oldStatus !== $data['status']) $changes[] = 'status';

                $summary = !empty($changes)
                    ? 'Modificó: ' . implode(', ', $changes)
                    : 'Actualización de metadatos';

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
            $stmt = $pdo->prepare("INSERT INTO pages_trash (page_id, tenant_id, deleted_by, deleted_by_name, deleted_by_type, deleted_at, scheduled_permanent_delete, ip_address) VALUES (?, ?, ?, ?, 'admin', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), ?)");
            $stmt->execute([
                $pageId,
                $tenantId,
                $user['id'] ?? 0,
                $user['name'] ?? 'Sistema',
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
            'title' => 'Preview: ' . e($revision->title),
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
            echo json_encode(['success' => false, 'message' => 'Sin permiso']);
            exit;
        }

        $tenantId = TenantManager::currentTenantId();
        if (!$tenantId) {
            echo json_encode(['success' => false, 'message' => 'Sesión inválida']);
            exit;
        }

        $page = Page::where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$page) {
            echo json_encode(['success' => false, 'message' => 'Página no encontrada']);
            exit;
        }

        // Obtener datos JSON del body
        $rawData = file_get_contents('php://input');
        $data = json_decode($rawData, true);

        if (!$data) {
            echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
            exit;
        }

        try {
            if (isset($data['title'])) $page->title = $data['title'];
            if (isset($data['content'])) $page->content = $data['content'];
            if (isset($data['excerpt'])) $page->excerpt = $data['excerpt'];

            $page->updated_at = date('Y-m-d H:i:s');
            $page->save();

            // Crear revisión de tipo autosave
            \Screenart\Musedock\Models\PageRevision::createFromPage($page, 'autosave', 'Autoguardado');

            echo json_encode([
                'success' => true,
                'message' => 'Autoguardado exitoso',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            error_log("Error en autosave: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error al autoguardar']);
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
            return ['error' => 'El archivo no es una imagen válida.'];
        }

        // Dimensiones deseadas
        $targetWidth = 1920;
        $targetHeight = 400;

        $uploadDir = APP_ROOT . '/public/assets/uploads/headers/';
        $relativePath = 'uploads/headers/';

        // Crear directorio si no existe
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return ['error' => 'Error al crear el directorio para guardar la imagen.'];
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
                return ['error' => 'Error al mover el archivo subido'];
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
                return ['error' => 'Error al procesar la imagen: ' . $e->getMessage()];
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
}
