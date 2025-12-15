<?php

namespace Blog\Controllers\Superadmin;

use Screenart\Musedock\View;
use Blog\Models\BlogTag;
use Blog\Requests\BlogTagRequest;
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

    public function index()
    {
        $this->checkPermission('blog.view');
        // Capturamos parámetros de búsqueda y paginación
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';

        // Obtener registros por página (10 por defecto, -1 para todos)
        $perPage = isset($_GET['perPage']) ? intval($_GET['perPage']) : 10;
        $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

        // Consulta de las etiquetas
        $query = BlogTag::query()
            ->whereRaw('(tenant_id IS NULL OR tenant_id = 0)')
            ->orderBy('name', 'ASC');

        // Aplicar búsqueda si existe
        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $query->whereRaw("
                (name LIKE ? OR slug LIKE ? OR description LIKE ?)
            ", [$searchTerm, $searchTerm, $searchTerm]);
        }

        // Si queremos todos los registros
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
            // Paginamos con el número solicitado
            $pagination = $query->paginate($perPage, $currentPage);
            $tags = $pagination['items'] ?? [];
        }

        // Procesamos los objetos BlogTag
        $processedTags = array_map(fn($row) => ($row instanceof BlogTag) ? $row : new BlogTag((array) $row), $tags);

        // Renderizamos la vista
        return View::renderSuperadmin('blog.tags.index', [
            'title'      => 'Listado de etiquetas',
            'tags'       => $processedTags,
            'search'     => $search,
            'pagination' => $pagination,
        ]);
    }

    public function create()
    {
        $this->checkPermission('blog.create');
        return View::renderSuperadmin('blog.tags.create', [
            'title' => 'Crear Etiqueta',
            'tag' => new BlogTag(),
            'isNew' => true,
        ]);
    }

    public function store()
    {
        $this->checkPermission('blog.create');
        $data = $_POST;
        unset($data['_token'], $data['_csrf']);

        // Eliminamos tenant_id del conjunto de datos inicial
        unset($data['tenant_id']);

        $data = self::processFormData($data);

        $errors = BlogTagRequest::validate($data);

        if (!empty($errors)) {
            $this->flashValidationErrors($errors);
            header("Location: /musedock/blog/tags/create");
            exit;
        }

        // Creamos la etiqueta
        try {
            $tag = BlogTag::create($data);
        } catch (\Throwable $e) {
            if ($this->isUniqueViolation($e)) {
                $this->flashDuplicateSlug();
                header("Location: /musedock/blog/tags/create");
                exit;
            }
            throw $e;
        }

        // Actualizamos específicamente el tenant_id a NULL después de crear
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("UPDATE blog_tags SET tenant_id = NULL WHERE id = ?");
            $stmt->execute([$tag->id]);
        } catch (\Exception $e) {
            error_log("ERROR AL ACTUALIZAR TENANT_ID EN STORE: " . $e->getMessage());
        }

        flash('success', __('blog.tag.success_created'));
        header("Location: /musedock/blog/tags/{$tag->id}/edit");
        exit;
    }

    public function edit($id)
    {
        $this->checkPermission('blog.edit');
        // Limpiar datos 'old' al inicio
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        unset($_SESSION['_old_input']);

        // Buscar la etiqueta
        $tag = BlogTag::find($id);
        if (!$tag) {
            flash('error', __('blog.tag.error_not_found'));
            header('Location: /musedock/blog/tags');
            exit;
        }

        // Obtener y formatear fechas
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
            error_log("Error al obtener/formatear fechas para etiqueta {$id}: " . $e->getMessage());
        }

        return View::renderSuperadmin('blog.tags.edit', [
            'title' => 'Editar etiqueta: ' . e($tag->name),
            'tag'   => $tag,
        ]);
    }

    public function update($id)
    {
        $this->checkPermission('blog.edit');
        // Iniciar sesión si es necesario
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $tag = BlogTag::find($id);
        if (!$tag) {
            flash('error', __('blog.tag.error_not_found'));
            header("Location: /musedock/blog/tags");
            exit;
        }

        $rawData = $_POST;
        $data = $rawData;
        unset($data['_token'], $data['_csrf'], $data['_method']);

        // Validación (usar datos procesados para validar el slug real)
        $validationData = self::processFormData($data);
        $errors = BlogTagRequest::validate($validationData, $id);
        if (!empty($errors)) {
            $_SESSION['_old_input'] = $rawData;
            $this->flashValidationErrors($errors);
            header("Location: /musedock/blog/tags/{$id}/edit");
            exit;
        }
        unset($_SESSION['_old_input']);

        // Procesar datos
        $data = $validationData;
        unset($data['tenant_id']);

        try {
            $pdo = Database::connect();
            $pdo->beginTransaction();

            // Actualizar datos principales
            $tag->update($data);

            // Actualizar tenant_id
            $updateStmt = $pdo->prepare("UPDATE blog_tags SET tenant_id = NULL WHERE id = ?");
            $updateStmt->execute([$id]);

            $pdo->commit();

        } catch (\Throwable $e) {
            if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
            if ($this->isUniqueViolation($e)) {
                $this->flashDuplicateSlug();
                header("Location: /musedock/blog/tags/{$id}/edit");
                exit;
            }
            error_log("ERROR en transacción update etiqueta {$id}: " . $e->getMessage());
            $_SESSION['_old_input'] = $rawData;
            flash('error', __('blog.tag.error_update', ['error' => $e->getMessage()]));
            header("Location: /musedock/blog/tags/{$id}/edit");
            exit;
        }

        flash('success', __('blog.tag.success_updated'));
        header("Location: /musedock/blog/tags/{$id}/edit");
        exit;
    }

    /**
     * Procesa los datos del formulario antes de guardarlos
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

    public function destroy($id)
    {
        $this->checkPermission('blog.delete');
        $tag = BlogTag::find($id);
        if (!$tag) {
            flash('error', __('blog.tag.error_not_found'));
            header('Location: /musedock/blog/tags');
            exit;
        }

        try {
            $pdo = Database::connect();

            // 1. Verificar si tiene posts asociados
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM blog_post_tags WHERE tag_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result['count'] > 0) {
                flash('error', __('blog.tag.error_has_posts'));
                header('Location: /musedock/blog/tags');
                exit;
            }

            // 2. Eliminar la etiqueta
            $tag->delete();

            flash('success', __('blog.tag.success_deleted'));
        } catch (\Exception $e) {
            error_log("Error al eliminar etiqueta: " . $e->getMessage());
            flash('error', __('blog.tag.error_delete', ['error' => $e->getMessage()]));
        }

        header('Location: /musedock/blog/tags');
        exit;
    }

    public function bulk()
    {
        $this->checkPermission('blog.delete');
        $action = $_POST['action'] ?? null;
        $selected = $_POST['selected'] ?? [];

        if (empty($action) || empty($selected)) {
            flash('error', __('blog.tag.error_bulk_no_selection'));
            header('Location: /musedock/blog/tags');
            exit;
        }

        if ($action === 'delete') {
            $deletedCount = 0;

            foreach ($selected as $id) {
                $tag = BlogTag::find($id);

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
            header('Location: /musedock/blog/tags');
            exit;
        }

        flash('error', __('blog.tag.error_bulk_invalid_action'));
        header('Location: /musedock/blog/tags');
        exit;
    }
}
