<?php

namespace Blog\Controllers\Tenant;

use Screenart\Musedock\View;
use Blog\Models\BlogTag;
use Blog\Requests\BlogTagRequest;
use Screenart\Musedock\Services\TenantManager;
use Screenart\Musedock\Database;
use Screenart\Musedock\Traits\RequiresPermission;

class BlogTagController
{
    use RequiresPermission;

    private function flashValidationErrors(array $errors): void
    {
        $errors = array_values(array_filter(array_map('trim', $errors)));
        $message = !empty($errors) ? implode("\n", $errors) : 'Error de validación.';
        flash('error', $message);
    }

    private function isUniqueViolation(\Throwable $e): bool
    {
        $sqlState = method_exists($e, 'getCode') ? (string) $e->getCode() : '';
        if ($sqlState === '23505' || $sqlState === '23000') {
            return true;
        }
        $message = $e->getMessage();
        return stripos($message, 'duplicate key value') !== false
            || stripos($message, 'unique constraint') !== false
            || stripos($message, 'Duplicate entry') !== false;
    }

    private function flashDuplicateSlug(): void
    {
        flash('error', 'El slug ya está en uso.');
    }

    /**
     * Verificar si el usuario actual tiene un permiso específico
     */
    private function checkPermission(string $permission): void
    {
        if (!userCan($permission)) {
            flash('error', __('blog.tag.error_no_permission'));
            header('Location: ' . admin_url('dashboard'));
            exit;
        }
    }

    /**
     * Listado de etiquetas del tenant
     */
    public function index()
    {
        $this->checkPermission('blog.tags.view');
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('blog.tag.error_tenant_not_identified'));
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }

        // Capturar parámetros de búsqueda y paginación
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $perPage = isset($_GET['perPage']) ? intval($_GET['perPage']) : 10;
        $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

        // Consulta de etiquetas SOLO del tenant actual
        $query = BlogTag::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name', 'ASC');

        // Aplicar búsqueda si existe
        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $query->whereRaw("
                (name LIKE ? OR slug LIKE ? OR description LIKE ?)
            ", [$searchTerm, $searchTerm, $searchTerm]);
        }

        // Paginación
        if ($perPage == -1) {
            $tags = $query->get();
            $pagination = [
                'total' => count($tags),
                'per_page' => count($tags),
                'current_page' => 1,
                'last_page' => 1,
                'from' => 1,
                'to' => count($tags),
                'items' => $tags
            ];
        } else {
            $pagination = $query->paginate($perPage, $currentPage);
            $tags = $pagination['items'] ?? [];
        }

        // Procesar etiquetas
        $processedTags = array_map(fn($row) => ($row instanceof BlogTag) ? $row : new BlogTag((array) $row), $tags);

        return View::renderTenantAdmin('blog.tags.index', [
            'title'      => 'Listado de etiquetas',
            'tags'       => $processedTags,
            'search'     => $search,
            'pagination' => $pagination,
        ]);
    }

    /**
     * Formulario para crear nueva etiqueta
     */
    public function create()
    {
        $this->checkPermission('blog.tags.create');
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('blog.tag.error_tenant_not_identified'));
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }

        return View::renderTenantAdmin('blog.tags.create', [
            'title' => 'Crear Etiqueta',
            'tag' => new BlogTag(),
            'isNew' => true,
        ]);
    }

    /**
     * Guardar nueva etiqueta
     */
    public function store()
    {
        $this->checkPermission('blog.tags.create');
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('blog.tag.error_tenant_not_identified'));
            header('Location: /' . admin_path() . '/blog/tags');
            exit;
        }

        $data = $_POST;
        unset($data['_token'], $data['_csrf']);

        // Asignar tenant_id actual
        $data['tenant_id'] = $tenantId;

        $data = self::processFormData($data);

        $errors = BlogTagRequest::validate($data);

        if (!empty($errors)) {
            $this->flashValidationErrors($errors);
            header("Location: /" . admin_path() . "/blog/tags/create");
            exit;
        }

        // Crear la etiqueta
        try {
            $tag = BlogTag::create($data);
        } catch (\Throwable $e) {
            if ($this->isUniqueViolation($e)) {
                $this->flashDuplicateSlug();
                header("Location: /" . admin_path() . "/blog/tags/create");
                exit;
            }
            throw $e;
        }

        flash('success', __('blog.tag.success_created'));
        header("Location: /" . admin_path() . "/blog/tags/{$tag->id}/edit");
        exit;
    }

    /**
     * Formulario para editar etiqueta
     */
    public function edit($id)
    {
        $this->checkPermission('blog.tags.edit');
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('blog.tag.error_tenant_not_identified'));
            header('Location: /' . admin_path() . '/blog/tags');
            exit;
        }

        // Limpiar datos 'old'
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        unset($_SESSION['_old_input']);

        // Buscar etiqueta SOLO del tenant actual
        $tag = BlogTag::where('id', $id)
                    ->where('tenant_id', $tenantId)
                    ->first();

        if (!$tag) {
            flash('error', __('blog.tag.error_not_found_or_no_permission'));
            header('Location: /' . admin_path() . '/blog/tags');
            exit;
        }

        // Formatear fechas
        $tag->created_at_formatted = 'Desconocido';
        $tag->updated_at_formatted = 'Desconocido';

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT created_at, updated_at FROM blog_tags WHERE id = ?");
            $stmt->execute([$id]);
            $dates = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($dates) {
                $dateFormat = setting('date_format', 'd/m/Y');
                $timeFormat = setting('time_format', 'H:i');
                $dateTimeFormat = $dateFormat . ' ' . $timeFormat;

                if (!empty($dates['created_at'])) {
                    $timestamp_created = strtotime($dates['created_at']);
                    if ($timestamp_created !== false) {
                        $tag->created_at_formatted = date($dateTimeFormat, $timestamp_created);
                    }
                }

                if (!empty($dates['updated_at'])) {
                    $timestamp_updated = strtotime($dates['updated_at']);
                    if ($timestamp_updated !== false) {
                        $tag->updated_at_formatted = date($dateTimeFormat, $timestamp_updated);
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Error al formatear fechas: " . $e->getMessage());
        }

        return View::renderTenantAdmin('blog.tags.edit', [
            'title' => 'Editar etiqueta: ' . e($tag->name),
            'tag'   => $tag,
        ]);
    }

    /**
     * Actualizar etiqueta
     */
    public function update($id)
    {
        $this->checkPermission('blog.tags.edit');
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('blog.tag.error_tenant_not_identified'));
            header('Location: /' . admin_path() . '/blog/tags');
            exit;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        // Buscar etiqueta SOLO del tenant actual
        $tag = BlogTag::where('id', $id)
                    ->where('tenant_id', $tenantId)
                    ->first();

        if (!$tag) {
            flash('error', __('blog.tag.error_not_found_or_no_permission'));
            header("Location: /" . admin_path() . "/blog/tags");
            exit;
        }

        $rawData = $_POST;
        $data = $rawData;
        unset($data['_token'], $data['_csrf'], $data['_method']);

        // Asegurar que tenant_id no cambie
        $data['tenant_id'] = $tenantId;

        // Validación (usar datos procesados para validar el slug real)
        $validationData = self::processFormData($data);
        $errors = BlogTagRequest::validate($validationData, $id);
        if (!empty($errors)) {
            $_SESSION['_old_input'] = $rawData;
            $this->flashValidationErrors($errors);
            header("Location: /" . admin_path() . "/blog/tags/{$id}/edit");
            exit;
        }
        unset($_SESSION['_old_input']);

        // Procesar datos
        $data = $validationData;

        try {
            $pdo = Database::connect();
            $pdo->beginTransaction();

            // Actualizar datos principales
            $tag->update($data);

            $pdo->commit();

        } catch (\Throwable $e) {
            if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
            if ($this->isUniqueViolation($e)) {
                $this->flashDuplicateSlug();
                header("Location: /" . admin_path() . "/blog/tags/{$id}/edit");
                exit;
            }
            error_log("ERROR en transacción update etiqueta {$id}: " . $e->getMessage());
            $_SESSION['_old_input'] = $rawData;
            flash('error', __('blog.tag.error_update', ['error' => $e->getMessage()]));
            header("Location: /" . admin_path() . "/blog/tags/{$id}/edit");
            exit;
        }

        flash('success', __('blog.tag.success_updated'));
        header("Location: /" . admin_path() . "/blog/tags/{$id}/edit");
        exit;
    }

    /**
     * Eliminar etiqueta
     */
    public function destroy($id)
    {
        $this->checkPermission('blog.tags.delete');
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('blog.tag.error_tenant_not_identified'));
            header('Location: /' . admin_path() . '/blog/tags');
            exit;
        }

        // Buscar etiqueta SOLO del tenant actual
        $tag = BlogTag::where('id', $id)
                    ->where('tenant_id', $tenantId)
                    ->first();

        if (!$tag) {
            flash('error', __('blog.tag.error_not_found_or_no_permission'));
            header('Location: /' . admin_path() . '/blog/tags');
            exit;
        }

        try {
            $pdo = Database::connect();

            // Verificar si tiene posts asociados
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM blog_post_tags WHERE tag_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result['count'] > 0) {
                flash('error', __('blog.tag.error_has_posts'));
                header('Location: /' . admin_path() . '/blog/tags');
                exit;
            }

            // Eliminar la etiqueta
            $tag->delete();

            flash('success', __('blog.tag.success_deleted'));
        } catch (\Exception $e) {
            error_log("Error al eliminar etiqueta: " . $e->getMessage());
            flash('error', __('blog.tag.error_delete', ['error' => $e->getMessage()]));
        }

        header('Location: /' . admin_path() . '/blog/tags');
        exit;
    }

    /**
     * Acciones masivas
     */
    public function bulk()
    {
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('blog.tag.error_tenant_not_identified'));
            header('Location: /' . admin_path() . '/blog/tags');
            exit;
        }

        $action = $_POST['action'] ?? null;
        $selected = $_POST['selected'] ?? [];

        if (empty($action) || empty($selected)) {
            flash('error', __('blog.tag.error_bulk_no_selection'));
            header('Location: /' . admin_path() . '/blog/tags');
            exit;
        }

        // Verificar permisos según la acción
        if ($action === 'delete') {
            $this->checkPermission('blog.tags.delete');
        }

        if ($action === 'delete') {
            $deletedCount = 0;

            foreach ($selected as $id) {
                // Verificar que la etiqueta pertenezca al tenant
                $tag = BlogTag::where('id', $id)
                            ->where('tenant_id', $tenantId)
                            ->first();

                if ($tag) {
                    try {
                        $pdo = Database::connect();

                        // Verificar si tiene posts asociados
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM blog_post_tags WHERE tag_id = ?");
                        $stmt->execute([$id]);
                        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

                        if ($result['count'] > 0) {
                            continue; // Saltar esta etiqueta
                        }

                        // Eliminar etiqueta
                        $tag->delete();
                        $deletedCount++;
                    } catch (\Exception $e) {
                        error_log("Error al eliminar etiqueta #{$id}: " . $e->getMessage());
                        continue;
                    }
                }
            }

            flash('success', __('blog.tag.success_bulk_deleted', ['count' => $deletedCount]));
            header('Location: /' . admin_path() . '/blog/tags');
            exit;
        }

        flash('error', __('blog.tag.error_bulk_invalid_action'));
        header('Location: /' . admin_path() . '/blog/tags');
        exit;
    }

    /**
     * Procesar datos del formulario
     */
    private static function processFormData($data)
    {
        // Gestionar campos opcionales
        $optionalFields = ['description'];

        foreach ($optionalFields as $field) {
            if (isset($data[$field]) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        return $data;
    }
}
