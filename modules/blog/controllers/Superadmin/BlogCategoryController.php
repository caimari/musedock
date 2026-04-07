<?php

namespace Blog\Controllers\Superadmin;

use Screenart\Musedock\View;
use Blog\Models\BlogCategory;
use Blog\Requests\BlogCategoryRequest;
use Screenart\Musedock\Database;
use Screenart\Musedock\Helpers\FileUploadValidator;
use Screenart\Musedock\Traits\RequiresPermission;

class BlogCategoryController
{
    use RequiresPermission;
    use \Blog\Traits\CrossPublisherScope;

    /**
     * Build a flat list sorted as a tree with depth for visual nesting.
     */
    private function buildCategoryTree(array $categories, $parentId = null, int $depth = 0): array
    {
        $result = [];
        foreach ($categories as $cat) {
            $catParentId = $cat->parent_id ?? null;
            if ($catParentId == $parentId) {
                $cat->depth = $depth;
                $result[] = $cat;
                $children = $this->buildCategoryTree($categories, $cat->id, $depth + 1);
                $result = array_merge($result, $children);
            }
        }
        return $result;
    }

    private function updateAllCategoryCounts(): void
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
                UPDATE blog_categories c
                SET post_count = (
                    SELECT COUNT(*)
                    FROM blog_post_categories pc
                    WHERE pc.category_id = c.id
                )
            ");
            $stmt->execute();
        } catch (\Exception $e) {
            error_log("Error al actualizar contadores de categorías: " . $e->getMessage());
        }
    }

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
        $this->updateAllCategoryCounts();

        // Resolver scope del cross-publisher
        $scope = $this->resolveTenantScope();

        // Capturamos parámetros de búsqueda y paginación
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';

        // Obtener registros por página (10 por defecto, -1 para todos)
        $perPage = isset($_GET['perPage']) ? intval($_GET['perPage']) : 10;
        $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

        // Consulta de las categorías según el scope
        $query = BlogCategory::query()
            ->orderBy('order', 'ASC')
            ->orderBy('name', 'ASC');

        if ($scope['mode'] === 'mine') {
            $query->whereRaw('(tenant_id IS NULL OR tenant_id = 0)');
        } else {
            $placeholders = implode(',', array_fill(0, count($scope['tenantIds']), '?'));
            $query->whereRaw("tenant_id IN ({$placeholders})", $scope['tenantIds']);
        }

        // Aplicar búsqueda si existe
        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $query->whereRaw("
                (name LIKE ? OR slug LIKE ? OR description LIKE ?)
            ", [$searchTerm, $searchTerm, $searchTerm]);
        }

        // For tree view (no search): load all categories and build hierarchy
        // For search: paginate flat results
        if (empty($search)) {
            $categories = $query->get();
            $pagination = [
                'total' => count($categories),
                'per_page' => count($categories),
                'current_page' => 1,
                'last_page' => 1,
                'from' => 1,
                'to' => count($categories),
                'items' => $categories
            ];
        } elseif ($perPage == -1) {
            $categories = $query->get();
            $pagination = [
                'total' => count($categories),
                'per_page' => count($categories),
                'current_page' => 1,
                'last_page' => 1,
                'from' => 1,
                'to' => count($categories),
                'items' => $categories
            ];
        } else {
            $pagination = $query->paginate($perPage, $currentPage);
            $categories = $pagination['items'] ?? [];
        }

        // Procesamos los objetos BlogCategory
        $processedCategories = array_map(fn($row) => ($row instanceof BlogCategory) ? $row : new BlogCategory((array) $row), $categories);

        // Build tree structure with depth for visual nesting (only when not searching)
        if (empty($search)) {
            $processedCategories = $this->buildCategoryTree($processedCategories);
        }

        // Mapa de tenants para mostrar dominio
        $tenantMap = ($scope['mode'] !== 'mine') ? $this->buildTenantMap($scope['tenantIds']) : [];

        // Renderizamos la vista
        return View::renderSuperadmin('blog.categories.index', array_merge([
            'title'       => 'Listado de categorías',
            'categories'  => $processedCategories,
            'search'      => $search,
            'pagination'  => $pagination,
            'scope'       => $scope,
            'tenantMap'   => $tenantMap,
        ], $this->getCrossPublisherFilterData()));
    }

    public function create()
    {
        $this->checkPermission('blog.create');

        // Detectar si se crea categoría para un tenant específico
        $targetTenantId = null;
        $targetTenant = null;
        if (!empty($_GET['tenant_id']) && is_cross_publisher_active()) {
            $tenantId = (int) $_GET['tenant_id'];
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT id, name, domain FROM tenants WHERE id = ? AND group_id IS NOT NULL");
            $stmt->execute([$tenantId]);
            $targetTenant = $stmt->fetch(\PDO::FETCH_OBJ);
            if ($targetTenant) {
                $targetTenantId = $targetTenant->id;
            }
        }

        // Cargar categorías padre del tenant o globales
        if ($targetTenantId) {
            $categories = BlogCategory::where('tenant_id', $targetTenantId)->orderBy('name', 'ASC')->get();
        } else {
            $categories = BlogCategory::whereRaw('(tenant_id IS NULL OR tenant_id = 0)')->orderBy('name', 'ASC')->get();
        }

        $processedCats = array_map(fn($row) => ($row instanceof BlogCategory) ? $row : new BlogCategory((array) $row), $categories);
        $parentCategories = $this->buildCategoryTree($processedCats);

        return View::renderSuperadmin('blog.categories.create', [
            'title' => 'Crear Categoría',
            'category' => new BlogCategory(),
            'categories' => $categories,
            'parentCategories' => $parentCategories,
            'isNew' => true,
            'targetTenantId' => $targetTenantId,
            'targetTenant' => $targetTenant,
        ]);
    }

    public function store()
    {
        $this->checkPermission('blog.create');
        $data = $_POST;
        unset($data['_token'], $data['_csrf']);

        // Detectar si se crea categoría para un tenant específico
        $targetTenantId = null;
        if (!empty($data['target_tenant_id']) && is_cross_publisher_active()) {
            $targetTenantId = (int) $data['target_tenant_id'];
        }
        unset($data['target_tenant_id'], $data['tenant_id']);

        // Procesar la subida de imagen si existe
        if ($_FILES && isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $uploadResult = $this->processCategoryImageUpload($_FILES['image']);
            if (isset($uploadResult['error'])) {
                flash('error', __('blog.category.error_upload_image', ['error' => $uploadResult['error']]));
                header("Location: /musedock/blog/categories/create");
                exit;
            }
            $data['image'] = $uploadResult['path'];
        }

        $data = self::processFormData($data);

        $errors = BlogCategoryRequest::validate($data);

        if (!empty($errors)) {
            $this->flashValidationErrors($errors);
            header("Location: /musedock/blog/categories/create");
            exit;
        }

        // Creamos la categoría
        try {
            $category = BlogCategory::create($data);
        } catch (\Throwable $e) {
            if ($this->isUniqueViolation($e)) {
                $this->flashDuplicateSlug();
                header("Location: /musedock/blog/categories/create");
                exit;
            }
            throw $e;
        }

        // Establecer tenant_id correcto
        try {
            $pdo = Database::connect();
            if ($targetTenantId) {
                $stmt = $pdo->prepare("UPDATE blog_categories SET tenant_id = ? WHERE id = ?");
                $stmt->execute([$targetTenantId, $category->id]);
            } else {
                $stmt = $pdo->prepare("UPDATE blog_categories SET tenant_id = NULL WHERE id = ?");
                $stmt->execute([$category->id]);
            }
        } catch (\Exception $e) {
            error_log("ERROR AL ACTUALIZAR TENANT_ID EN STORE: " . $e->getMessage());
        }

        flash('success', __('blog.category.success_created'));
        header("Location: /musedock/blog/categories/{$category->id}/edit");
        exit;
    }

    public function edit($id)
    {
        $this->checkPermission('blog.edit');
        // Limpiar datos 'old' al inicio
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        unset($_SESSION['_old_input']);

        // Buscar la categoría
        $category = BlogCategory::find($id);
        if (!$category) {
            flash('error', __('blog.category.error_not_found'));
            header('Location: /musedock/blog/categories');
            exit;
        }

        // Detectar si la categoría pertenece a un tenant
        $editingTenant = null;
        if ($category->tenant_id && is_cross_publisher_active()) {
            $pdo2 = Database::connect();
            $stmt2 = $pdo2->prepare("SELECT id, name, domain FROM tenants WHERE id = ?");
            $stmt2->execute([$category->tenant_id]);
            $editingTenant = $stmt2->fetch(\PDO::FETCH_OBJ);
        }

        // Obtener categorías padre: del tenant si es categoría de tenant, o globales
        if ($editingTenant) {
            $categories = BlogCategory::where('tenant_id', $category->tenant_id)
                ->where('id', '!=', $id)
                ->orderBy('name', 'ASC')
                ->get();
        } else {
            $categories = BlogCategory::whereRaw('(tenant_id IS NULL OR tenant_id = 0)')
                ->where('id', '!=', $id)
                ->orderBy('name', 'ASC')
                ->get();
        }

        // Obtener y formatear fechas
        $category->created_at_formatted = 'Desconocido';
        $category->updated_at_formatted = 'Desconocido';
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT created_at, updated_at FROM blog_categories WHERE id = ?");
            $stmt->execute([$id]);
            $dates = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($dates) {
                $dateFormat = setting('date_format', 'd/m/Y');
                $timeFormat = setting('time_format', 'H:i');
                $dateTimeFormat = $dateFormat . ' ' . $timeFormat;
                if (!empty($dates['created_at'])) {
                    $timestamp_created = strtotime($dates['created_at']);
                    if ($timestamp_created !== false) {
                        $category->created_at_formatted = date($dateTimeFormat, $timestamp_created);
                    }
                }
                if (!empty($dates['updated_at'])) {
                    $timestamp_updated = strtotime($dates['updated_at']);
                    if ($timestamp_updated !== false) {
                        $category->updated_at_formatted = date($dateTimeFormat, $timestamp_updated);
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Error al obtener/formatear fechas para categoría {$id}: " . $e->getMessage());
        }

        // Build tree with depth for the parent selector
        $processedCategories = array_map(fn($row) => ($row instanceof BlogCategory) ? $row : new BlogCategory((array) $row), $categories);
        $parentCategories = $this->buildCategoryTree($processedCategories);

        return View::renderSuperadmin('blog.categories.edit', [
            'title'      => 'Editar categoría: ' . e($category->name),
            'category'   => $category,
            'categories' => $categories,
            'parentCategories' => $parentCategories,
            'editingTenant' => $editingTenant,
        ]);
    }

    public function update($id)
    {
        $this->checkPermission('blog.edit');
        // Iniciar sesión si es necesario
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $category = BlogCategory::find($id);
        if (!$category) {
            flash('error', __('blog.category.error_not_found'));
            header("Location: /musedock/blog/categories");
            exit;
        }

        $rawData = $_POST;
        $data = $rawData;
        unset($data['_token'], $data['_csrf'], $data['_method']);

        // Procesar imagen
        $currentImage = $data['current_image'] ?? null;
        $removeImage = $data['remove_image'] ?? '0';

        unset($data['current_image'], $data['remove_image']);

        // Si marca eliminar imagen
        if ($removeImage === '1' && !empty($currentImage)) {
            $fileName = basename($currentImage);
            $fullPath = APP_ROOT . "/public/assets/uploads/blog/categories/{$fileName}";

            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }

            $data['image'] = null;
        }

        // Procesar la nueva imagen si se sube una
        if ($_FILES && isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $uploadResult = $this->processCategoryImageUpload($_FILES['image'], $currentImage);
            if (isset($uploadResult['error'])) {
                flash('error', __('blog.category.error_upload_image', ['error' => $uploadResult['error']]));
                header("Location: /musedock/blog/categories/{$id}/edit");
                exit;
            }
            $data['image'] = $uploadResult['path'];
        } elseif ($removeImage !== '1') {
            $data['image'] = $currentImage;
        }

        // Validación (usar datos procesados para validar el slug real)
        $validationData = self::processFormData($data);
        $errors = BlogCategoryRequest::validate($validationData, $id);
        if (!empty($errors)) {
            $_SESSION['_old_input'] = $rawData;
            $this->flashValidationErrors($errors);
            header("Location: /musedock/blog/categories/{$id}/edit");
            exit;
        }
        unset($_SESSION['_old_input']);

        // Preservar tenant_id original
        $originalTenantId = $category->tenant_id;
        $data = $validationData;
        unset($data['tenant_id']);

        try {
            $pdo = Database::connect();
            $pdo->beginTransaction();

            // Actualizar datos principales
            $category->update($data);

            // Preservar tenant_id original
            if ($originalTenantId) {
                $updateStmt = $pdo->prepare("UPDATE blog_categories SET tenant_id = ? WHERE id = ?");
                $updateStmt->execute([$originalTenantId, $id]);
            } else {
                $updateStmt = $pdo->prepare("UPDATE blog_categories SET tenant_id = NULL WHERE id = ?");
                $updateStmt->execute([$id]);
            }

            $pdo->commit();

        } catch (\Throwable $e) {
            if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
            if ($this->isUniqueViolation($e)) {
                $this->flashDuplicateSlug();
                header("Location: /musedock/blog/categories/{$id}/edit");
                exit;
            }
            error_log("ERROR en transacción update categoría {$id}: " . $e->getMessage());
            $_SESSION['_old_input'] = $rawData;
            flash('error', __('blog.category.error_update', ['error' => $e->getMessage()]));
            header("Location: /musedock/blog/categories/{$id}/edit");
            exit;
        }

        flash('success', __('blog.category.success_updated'));
        header("Location: /musedock/blog/categories/{$id}/edit");
        exit;
    }

    /**
     * Procesa los datos del formulario antes de guardarlos
     */
    private static function processFormData($data)
    {
        // Gestionar campos opcionales
        $optionalFields = ['description', 'seo_title', 'seo_description', 'seo_keywords', 'parent_id'];

        foreach ($optionalFields as $field) {
            if (isset($data[$field]) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        // Asegurar que order sea un número
        if (!isset($data['order']) || $data['order'] === '') {
            $data['order'] = 0;
        }

        return $data;
    }

    /**
     * Procesa la subida de imagen de categoría
     */
    private function processCategoryImageUpload($file, $currentImage = null)
    {
        // 🔒 SECURITY: Validación completa de archivos subidos
        $validation = FileUploadValidator::validateImage($file);
        if (!$validation['valid']) {
            return ['error' => $validation['error']];
        }

        $fileInfo = getimagesize($file['tmp_name']);
        if ($fileInfo === false) {
            return ['error' => 'El archivo no es una imagen válida.'];
        }

        $targetWidth = 400;
        $targetHeight = 400;

        $uploadDir = APP_ROOT . '/public/assets/uploads/blog/categories/';
        $relativePath = 'uploads/blog/categories/';

        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return ['error' => "Error al crear el directorio: " . $uploadDir];
            }
        }

        // Generar nombre seguro usando el helper
        $filename = FileUploadValidator::generateSecureFilename($validation['extension'], 'category');
        $fullPath = $uploadDir . $filename;
        $extension = $validation['extension'];

        $isUnsupportedFormat = !in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);

        if ($isUnsupportedFormat) {
            if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                return ['error' => "Error al mover el archivo subido"];
            }
        } else {
            try {
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

                if ($extension == 'png') {
                    imagealphablending($targetImage, false);
                    imagesavealpha($targetImage, true);
                    $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
                    imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $transparent);
                }

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

                imagecopyresampled(
                    $targetImage, $sourceImage,
                    0, 0, $srcX, $srcY,
                    $targetWidth, $targetHeight, $newWidth, $newHeight
                );

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
                return ['error' => "Error al procesar la imagen: " . $e->getMessage()];
            }
        }

        if ($currentImage && !empty($currentImage)) {
            $oldPath = APP_ROOT . '/public/' . $currentImage;
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }

        return ['path' => $relativePath . $filename];
    }

    public function destroy($id)
    {
        $this->checkPermission('blog.delete');
        $category = BlogCategory::find($id);
        if (!$category) {
            flash('error', __('blog.category.error_not_found'));
            header('Location: /musedock/blog/categories');
            exit;
        }

        try {
            $pdo = Database::connect();

            // 1. Verificar si tiene posts asociados
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM blog_post_categories WHERE category_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result['count'] > 0) {
                flash('error', __('blog.category.error_has_posts'));
                header('Location: /musedock/blog/categories');
                exit;
            }

            // 2. Eliminar imagen si existe
            if (!empty($category->image)) {
                $fileName = basename($category->image);
                $fullPath = APP_ROOT . "/public/assets/uploads/blog/categories/{$fileName}";
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
            }

            // 3. Eliminar la categoría
            $category->delete();

            flash('success', __('blog.category.success_deleted'));
        } catch (\Exception $e) {
            error_log("Error al eliminar categoría: " . $e->getMessage());
            flash('error', __('blog.category.error_delete', ['error' => $e->getMessage()]));
        }

        header('Location: /musedock/blog/categories');
        exit;
    }

    public function bulk()
    {
        $this->checkPermission('blog.delete');
        $action = $_POST['action'] ?? null;
        $selected = $_POST['selected'] ?? [];

        if (empty($action) || empty($selected)) {
            flash('error', __('blog.category.error_bulk_no_selection'));
            header('Location: /musedock/blog/categories');
            exit;
        }

        if ($action === 'delete') {
            $deletedCount = 0;

            foreach ($selected as $id) {
                $category = BlogCategory::find($id);

                if ($category) {
                    try {
                        $pdo = Database::connect();

                        // Verificar si tiene posts asociados
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM blog_post_categories WHERE category_id = ?");
                        $stmt->execute([$id]);
                        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

                        if ($result['count'] > 0) {
                            continue; // Saltar esta categoría
                        }

                        // Eliminar imagen
                        if (!empty($category->image)) {
                            $fileName = basename($category->image);
                            $fullPath = APP_ROOT . "/public/assets/uploads/blog/categories/{$fileName}";
                            if (file_exists($fullPath)) {
                                @unlink($fullPath);
                            }
                        }

                        // Eliminar categoría
                        $category->delete();
                        $deletedCount++;
                    } catch (\Exception $e) {
                        error_log("Error al eliminar categoría #{$id}: " . $e->getMessage());
                        continue;
                    }
                }
            }

            flash('success', __('blog.category.success_bulk_deleted', ['count' => $deletedCount]));
            header('Location: /musedock/blog/categories');
            exit;
        }

        flash('error', __('blog.category.error_bulk_invalid_action'));
        header('Location: /musedock/blog/categories');
        exit;
    }
}
