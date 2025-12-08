<?php

namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\View;
use Screenart\Musedock\Models\Page;
use Screenart\Musedock\Models\PageMeta;
use Screenart\Musedock\Models\User;
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
     * Listado de páginas del tenant
     */
    public function index()
    {
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('pages.error_tenant_not_identified'));
            header('Location: /admin/dashboard');
            exit;
        }

        // Capturar parámetros de búsqueda y paginación
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
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
        if ($perPage == -1) {
            $pages = $query->get();
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
                $page = new Page((array) $pageData);

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
            $processedPages = array_map(fn($row) => new Page((array) $row), $pages);
        }

        // Cargar autores (usuarios del tenant)
        $authors = [];
        foreach ($processedPages as $page) {
            $userId = $page->user_id ?? null;
            if ($userId && !isset($authors[$userId])) {
                $authors[$userId] = User::find($userId);
            }
        }

        // Buscar la página marcada como inicio del tenant
        $homepageId = Page::where('is_homepage', 1)
                          ->where('tenant_id', $tenantId)
                          ->value('id');

        return View::renderTenantAdmin('pages.index', [
            'title'       => 'Listado de páginas',
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
        $availableTemplates = get_page_templates();
        $currentPageTemplate = 'page.blade.php';

        return View::renderTenantAdmin('pages.create', [
            'title' => 'Crear Página',
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
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('pages.error_tenant_not_identified'));
            header('Location: /admin/pages');
            exit;
        }

        $data = $_POST;
        unset($data['_token'], $data['_csrf']);

        // Asignar tenant_id actual
        $data['tenant_id'] = $tenantId;

        // Usuario actual del tenant
        $data['user_id'] = $_SESSION['user']['id'] ?? null;
        $data['user_type'] = 'user';

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
                header("Location: /admin/pages/create");
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
            header("Location: /admin/pages/create");
            exit;
        }

        // Crear la página
        $page = Page::create($data);

        // 🔒 SECURITY: Audit log - registrar creación de página
        AuditLogger::log('page.created', 'page', $page->id, [
            'title' => $data['title'] ?? '',
            'slug' => $data['slug'] ?? '',
            'status' => $data['status'] ?? 'draft',
            'tenant_id' => $tenantId
        ]);

        // Crear slug con tenant_id
        try {
            $pdo = Database::connect();
            $prefix = $data['prefix'] ?? 'p';

            $insertStmt = $pdo->prepare("INSERT INTO slugs (module, reference_id, slug, tenant_id, prefix) VALUES (?, ?, ?, ?, ?)");
            $insertStmt->execute(['pages', $page->id, $data['slug'], $tenantId, $prefix]);

        } catch (\Exception $e) {
            error_log("ERROR AL CREAR SLUG: " . $e->getMessage());
        }

        // ✅ Crear primera revisión de la página
        try {
            \Screenart\Musedock\Models\PageRevision::createFromPage($page, 'initial', 'Versión inicial de la página');
        } catch (\Exception $e) {
            error_log("Error al crear revisión inicial: " . $e->getMessage());
        }

        flash('success', __('pages.success_created'));
        header("Location: /admin/pages/{$page->id}/edit");
        exit;
    }

    /**
     * Formulario para editar página
     */
    public function edit($id)
    {
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('pages.error_tenant_not_identified'));
            header('Location: /admin/pages');
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
            header('Location: /admin/pages');
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
            'title'               => 'Editar página: ' . e($page->title),
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
        error_log("UPDATE PÁGINA INICIADO - ID: {$id}, Method: " . $_SERVER['REQUEST_METHOD']);
        error_log("POST data: " . json_encode($_POST));

        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            error_log("UPDATE ERROR: No se pudo identificar el tenant");
            flash('error', __('pages.error_tenant_not_identified'));
            header('Location: /admin/pages');
            exit;
        }

        error_log("UPDATE: Tenant ID: {$tenantId}");

        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        // Buscar página SOLO del tenant actual
        $page = Page::where('id', $id)
                    ->where('tenant_id', $tenantId)
                    ->first();

        if (!$page) {
            flash('error', __('pages.error_not_found_or_no_permission'));
            header("Location: /admin/pages");
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
                header("Location: /admin/pages/{$id}/edit");
                exit;
            }
            $data['slider_image'] = $uploadResult['path'];
        } elseif ($removeImage !== '1') {
            $data['slider_image'] = $currentSliderImage;
        }

        // Asegurar que tenant_id no cambie
        $data['tenant_id'] = $tenantId;

        $data = self::processFormData($data);

        // IMPORTANTE: Pasar el ID para excluirlo de la validación de slug duplicado
        $errors = PageRequest::validate($data, $id);

        if (!empty($errors)) {
            error_log("UPDATE: Errores de validación: " . implode(', ', $errors));
            flash('error', implode('<br>', $errors));
            header("Location: /admin/pages/{$id}/edit");
            exit;
        }

        error_log("UPDATE: Datos a actualizar: " . json_encode($data));
        error_log("UPDATE: Llamando a page->update()");

        // Actualizar página
        $updateResult = $page->update($data);

        error_log("UPDATE: Resultado de update(): " . ($updateResult ? 'true' : 'false'));

        // Si se marca como homepage
        if ($makeHomepage) {
            try {
                $pdo = Database::connect();

                // Quitar homepage de otras páginas del tenant
                $stmt = $pdo->prepare("UPDATE pages SET is_homepage = 0 WHERE tenant_id = ? AND id != ?");
                $stmt->execute([$tenantId, $id]);

                // Marcar esta como homepage
                $stmt = $pdo->prepare("UPDATE pages SET is_homepage = 1 WHERE id = ?");
                $stmt->execute([$id]);

            } catch (\Exception $e) {
                error_log("Error al establecer homepage: " . $e->getMessage());
            }
        }

        // Guardar metadatos (plantilla, SEO, etc.)
        $this->savePageMeta($id, $rawData);

        // ✅ Crear revisión después de actualizar exitosamente
        try {
            // Detectar cambios
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

        // 🔒 SECURITY: Audit log - registrar actualización de página
        AuditLogger::log('page.updated', 'page', $id, [
            'title' => $data['title'] ?? '',
            'slug' => $data['slug'] ?? '',
            'status' => $data['status'] ?? '',
            'is_homepage' => $makeHomepage ? 1 : 0,
            'tenant_id' => $tenantId
        ]);

        flash('success', __('pages.success_updated'));

        // Construir URL de redirección usando admin_path()
        $redirectUrl = '/' . admin_path() . "/pages/{$id}/edit";
        error_log("REDIRECT UPDATE: Redirigiendo a: {$redirectUrl}");

        header("Location: {$redirectUrl}");
        exit;
    }

    /**
     * Mover página a papelera
     */
    public function delete($id)
    {
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('pages.error_tenant_not_identified'));
            header('Location: /admin/pages');
            exit;
        }

        try {
            $pdo = Database::connect();
            $user = $_SESSION['admin'] ?? null;
            $pageId = (int)$id;

            // UPDATE directo para cambiar status a trash
            $updateSql = "UPDATE pages SET status = 'trash', updated_at = NOW() WHERE id = {$pageId} AND tenant_id = {$tenantId}";
            $affectedRows = $pdo->exec($updateSql);

            if ($affectedRows === 0) {
                flash('error', __('pages.error_not_found_or_no_permission'));
                header('Location: /admin/pages');
                exit;
            }

            // Verificar que el UPDATE funcionó
            $checkSql = "SELECT id, status FROM pages WHERE id = {$pageId}";
            $result = $pdo->query($checkSql)->fetch(\PDO::FETCH_ASSOC);

            if (!$result || $result['status'] !== 'trash') {
                flash('error', __('pages.error_trash_failed'));
                header('Location: /admin/pages');
                exit;
            }

            // Registrar en tabla pages_trash usando prepared statement
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

            // 🔒 SECURITY: Audit log - registrar movimiento a papelera
            AuditLogger::log('page.trashed', 'page', $id, [
                'title' => $page->title ?? '',
                'tenant_id' => $tenantId
            ]);

            flash('success', __('pages.success_trashed'));

        } catch (\Exception $e) {
            error_log("Error al mover página a papelera: " . $e->getMessage());
            flash('error', __('pages.error_trash_failed') . ': ' . $e->getMessage());
        }

        header('Location: /admin/pages');
        exit;
    }

    /**
     * Procesar datos del formulario
     */
    private static function processFormData($data)
    {
        // Aquí puedes agregar procesamiento adicional si es necesario
        return $data;
    }

    /**
     * Guardar metadatos de la página
     */
    private function savePageMeta($pageId, $data)
    {
        // Plantilla
        if (isset($data['page_template'])) {
            PageMeta::updateOrInsertMeta($pageId, 'page_template', $data['page_template']);
        }

        // Campos SEO
        $seoFields = [
            'seo_title', 'seo_description', 'seo_keywords', 'seo_image',
            'canonical_url', 'robots_directive',
            'twitter_title', 'twitter_description', 'twitter_image'
        ];

        foreach ($seoFields as $field) {
            if (isset($data[$field])) {
                PageMeta::updateOrInsertMeta($pageId, $field, $data[$field]);
            }
        }
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

        $uploadDir = 'assets/uploads/headers/';
        $publicPath = APP_ROOT . '/public/';

        if (!file_exists($publicPath . $uploadDir)) {
            if (!mkdir($publicPath . $uploadDir, 0755, true)) {
                return ['error' => 'Error al crear el directorio para guardar la imagen.'];
            }
        }

        // 🔒 SECURITY: Validación completa de archivos subidos
        $validation = \Screenart\Musedock\Helpers\FileUploadValidator::validateImage($file);
        if (!$validation['valid']) {
            return ['error' => $validation['error']];
        }

        // Generar nombre seguro
        $filename = \Screenart\Musedock\Helpers\FileUploadValidator::generateSecureFilename($validation['extension'], 'header');
        $fullPath = $publicPath . $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            return ['error' => 'Error al guardar la imagen.'];
        }

        // Eliminar imagen anterior si existe
        if ($currentImage && strpos($currentImage, 'themes/default/img/hero/') === false) {
            $oldPath = $publicPath . $currentImage;
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }

        return ['path' => $uploadDir . $filename];
    }

    // ================================================================
    // 📚 SISTEMA DE VERSIONES/REVISIONES
    // ================================================================

    /**
     * Mostrar historial de revisiones de una página
     */
    public function revisions($id)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $tenantId = getTenantId();
        if (!$tenantId) {
            flash('error', __('pages.error_invalid_session'));
            header("Location: /login");
            exit;
        }

        $page = Page::where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$page) {
            flash('error', __('pages.error_not_found'));
            $adminPath = $_SESSION['admin']['tenant_url'] ?? 'admin';
            header("Location: /{$adminPath}/pages");
            exit;
        }

        // Obtener revisiones
        $revisions = \Screenart\Musedock\Models\PageRevision::getPageRevisions($id, 100);

        return View::renderTenant('pages.revisions', [
            'title' => 'Historial de revisiones: ' . e($page->title),
            'page' => $page,
            'revisions' => $revisions,
        ]);
    }

    /**
     * Restaurar una revisión específica
     */
    public function restoreRevision($pageId, $revisionId)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $tenantId = getTenantId();
        if (!$tenantId) {
            flash('error', __('pages.error_invalid_session'));
            header("Location: /login");
            exit;
        }

        $adminPath = $_SESSION['admin']['tenant_url'] ?? 'admin';
        $revision = \Screenart\Musedock\Models\PageRevision::findWithTenant((int)$revisionId, $tenantId);

        if (!$revision || $revision->page_id != $pageId) {
            flash('error', __('pages.error_revision_not_found'));
            header("Location: /{$adminPath}/pages/{$pageId}/revisions");
            exit;
        }

        if ($revision->restore()) {
            flash('success', str_replace('{date}', $revision->created_at, __('pages.success_revision_restored')));
        } else {
            flash('error', __('pages.error_revision_restore_failed'));
        }

        header("Location: /{$adminPath}/pages/{$pageId}/edit");
        exit;
    }

    /**
     * Vista previa de una revisión
     */
    public function previewRevision($pageId, $revisionId)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $tenantId = getTenantId();
        if (!$tenantId) {
            flash('error', __('pages.error_invalid_session'));
            header("Location: /login");
            exit;
        }

        $revision = \Screenart\Musedock\Models\PageRevision::findWithTenant((int)$revisionId, $tenantId);

        if (!$revision || $revision->page_id != $pageId) {
            flash('error', __('pages.error_revision_not_found'));
            $adminPath = $_SESSION['admin']['tenant_url'] ?? 'admin';
            header("Location: /{$adminPath}/pages/{$pageId}/revisions");
            exit;
        }

        return View::renderTenant('pages.preview-revision', [
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
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $tenantId = getTenantId();
        if (!$tenantId) {
            flash('error', __('pages.error_invalid_session'));
            header("Location: /login");
            exit;
        }

        $revision1 = \Screenart\Musedock\Models\PageRevision::findWithTenant((int)$id1, $tenantId);
        $revision2 = \Screenart\Musedock\Models\PageRevision::findWithTenant((int)$id2, $tenantId);

        if (!$revision1 || !$revision2 || $revision1->page_id != $pageId || $revision2->page_id != $pageId) {
            flash('error', __('pages.error_revisions_not_found'));
            $adminPath = $_SESSION['admin']['tenant_url'] ?? 'admin';
            header("Location: /{$adminPath}/pages/{$pageId}/revisions");
            exit;
        }

        $diff = $revision1->diffWith($revision2);

        return View::renderTenant('pages.compare-revisions', [
            'title' => 'Comparar revisiones',
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
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $tenantId = getTenantId();
        if (!$tenantId) {
            flash('error', 'Sesión inválida.');
            header("Location: /login");
            exit;
        }

        // Obtener páginas en papelera
        $pages = Page::where('status', 'trash')
                        ->where('tenant_id', $tenantId)
                        ->orderBy('updated_at', 'DESC')
                        ->get();

        // Obtener info de papelera
        $pdo = Database::connect();
        $trashInfo = [];
        foreach ($pages as $page) {
            $stmt = $pdo->prepare("SELECT * FROM pages_trash WHERE page_id = ? LIMIT 1");
            $stmt->execute([$page->id]);
            $trashInfo[$page->id] = $stmt->fetch(\PDO::FETCH_ASSOC);
        }

        return View::renderTenant('pages.trash', [
            'title' => 'Papelera de páginas',
            'pages' => $pages,
            'trashInfo' => $trashInfo,
        ]);
    }

    /**
     * Restaurar una página desde la papelera
     */
    public function restoreFromTrash($id)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $tenantId = getTenantId();
        if (!$tenantId) {
            flash('error', __('pages.error_invalid_session'));
            header("Location: /login");
            exit;
        }

        $pdo = Database::connect();
        $pageId = (int)$id;
        $adminPath = $_SESSION['admin']['tenant_url'] ?? 'admin';

        // Verificar que la página existe en papelera
        $stmt = $pdo->prepare("SELECT id FROM pages WHERE id = ? AND tenant_id = ? AND status = 'trash'");
        $stmt->execute([$pageId, $tenantId]);
        $page = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$page) {
            flash('error', __('pages.error_not_found_in_trash'));
            header("Location: /{$adminPath}/pages/trash");
            exit;
        }

        // Restaurar usando SQL directo
        $updateSql = "UPDATE pages SET status = 'draft', updated_at = NOW() WHERE id = {$pageId}";
        $pdo->exec($updateSql);

        // Eliminar de papelera
        $stmt = $pdo->prepare("DELETE FROM pages_trash WHERE page_id = ?");
        $stmt->execute([$pageId]);

        // Crear revisión - cargar el modelo Page para esto
        $pageModel = Page::find($pageId);
        if ($pageModel) {
            \Screenart\Musedock\Models\PageRevision::createFromPage($pageModel, 'restored', 'Restaurado desde papelera');
        }

        flash('success', __('pages.success_restored'));
        header("Location: /{$adminPath}/pages/{$pageId}/edit");
        exit;
    }

    /**
     * Eliminar permanentemente una página
     */
    public function forceDelete($id)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $tenantId = getTenantId();
        if (!$tenantId) {
            flash('error', __('pages.error_invalid_session'));
            header("Location: /login");
            exit;
        }

        $adminPath = $_SESSION['admin']['tenant_url'] ?? 'admin';
        $page = Page::where('id', $id)
                        ->where('tenant_id', $tenantId)
                        ->where('status', 'trash')
                        ->first();

        if (!$page) {
            flash('error', __('pages.error_not_found_in_trash'));
            header("Location: /{$adminPath}/pages/trash");
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

            // Eliminar página
            $stmt = $pdo->prepare("DELETE FROM pages WHERE id = ?");
            $stmt->execute([$id]);

            flash('success', __('pages.success_permanently_deleted'));
        } catch (\Exception $e) {
            error_log("Error al eliminar permanentemente la página: " . $e->getMessage());
            flash('error', __('pages.error_delete_failed'));
        }

        header("Location: /{$adminPath}/pages/trash");
        exit;
    }

    /**
     * Autoguardar una página (AJAX endpoint)
     */
    public function autosave($id)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        header('Content-Type: application/json');

        $tenantId = getTenantId();
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
            // Actualizar campos de la página
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
}
